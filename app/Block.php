<?php
declare(strict_types=1);

namespace Core;

/**
 * Wird an jeden Block-Renderer übergeben ($b). $d enthält die Daten mit Defaults.
 */
final class Block
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $data,
        public readonly array $tunes,
        public readonly array $def,
        public readonly ?Block $prev = null,
    ) {}

    /** Erster Block einer Reihe (Theme::renderRow): Abschnitt bekommt die Klasse sec--row */
    public bool $rowLead = false;

    public function tune(string $key): mixed
    {
        return $this->tunes[$key] ?? null;
    }

    /**
     * Steht der Block neben dem vorigen (Tune „row“: auto, 1-2, 1-3, 2-3, 1-4, 3-4)? Dann gibt Theme::renderRow() nur seinen
     * Inhalt in einer Zelle des Abschnitts des ersten Blocks aus – z. B. für eine kleinere Überschrift (h3 statt h2).
     * Im Editor (Vorschau je Block) gilt die Einstellung, auch wenn sie beim ersten Block der Seite ignoriert wird.
     */
    public function inRow(): bool
    {
        return ($this->tunes['row'] ?? '') !== '';
    }

    /** Kopie mit geänderten Abschnitts-Optionen */
    public function withTunes(array $tunes): self
    {
        return new self($this->id, $this->type, $this->data, array_replace($this->tunes, $tunes), $this->def, $this->prev);
    }

    public function variant(): string
    {
        return (string) ($this->data['variant'] ?? '');
    }

    /**
     * Hintergrund des Abschnitts. Mit Hintergrundbild und Abdunkelung gilt ein dunkler Hintergrund des Themes
     * (helle Schrift), mit Aufhellung ein heller – so bleiben Textfarben und Kontraste stimmig.
     */
    public function bg(): string
    {
        $bg = (string) $this->tunes['background'];
        $ov = !empty($this->tunes['bgImage']) ? ($this->tunes['overlay'] ?? 'none') : 'none';
        if ($ov === 'none') return $bg;
        $theme = app()->theme;
        $dark = $theme->def['dark_backgrounds'] ?? [];
        if ($ov === 'dark' && !in_array($bg, $dark, true) && $dark) {
            return in_array('dark', $dark, true) ? 'dark' : (string) $dark[array_key_last($dark)];
        }
        if ($ov === 'light' && in_array($bg, $dark, true)) {
            return (string) (array_values(array_diff(array_keys($theme->backgrounds()), $dark))[0] ?? $bg);
        }
        return $bg;
    }

    /** Dunkler Hintergrund? (für Akzentfarben) */
    public function dark(): bool
    {
        return in_array($this->bg(), app()->theme->def['dark_backgrounds'] ?? [], true);
    }

    public function domId(): string
    {
        return $this->tunes['anchor'] ?: 'b-' . $this->id;
    }

    public function titleId(): string
    {
        return $this->domId() . '-title';
    }

    /**
     * Im Bearbeitungsmodus: macht ein Textfeld direkt im Frontend editierbar.
     * Pfad mit Punkten für Listen: 'items.0.title'
     */
    public function edit(string $path, string $mode = 'plain'): string
    {
        if (!app()->editing) {
            // Detailseite außerhalb des Vorlagen-Editors: gebundene Felder bearbeiten den Eintrag selbst
            return app()->entryEdit ? \Core\Data\EntryEdit::blockAttr($this, $path) : '';
        }
        // An den Datensatz gebunden → nicht direkt bearbeitbar, sondern gekennzeichnet
        if (isset($this->data['_bind'][explode('.', $path)[0]])) {
            return ' data-bound="' . e((string) $this->data['_bind'][explode('.', $path)[0]]) . '" title="Aus dem Datensatz"';
        }
        return ' data-edit="' . e($path) . '"' . ($mode !== 'plain' ? ' data-edit-mode="' . e($mode) . '"' : '');
    }

    /** Markiert zentral gepflegte Inhalte (Einstellungen des Themes) im Bearbeitungsmodus. */
    public function central(?string $label = null): string
    {
        return app()->editing ? ' data-central="' . e($label ?? app()->theme->settingsTitle()) . '"' : '';
    }

    public function sectionClass(): string
    {
        $c = ['sec', 'sec--' . str_replace('_', '-', $this->type), 'bg-' . $this->bg()];
        if ($this->tunes['spaceTop'] !== 'normal') $c[] = 'pt-' . $this->tunes['spaceTop'];
        if ($this->tunes['spaceBottom'] !== 'normal') $c[] = 'pb-' . $this->tunes['spaceBottom'];
        if ($this->tunes['divider']) $c[] = 'sec--divider';
        if ($this->rowLead) $c[] = 'sec--row';
        if (!$this->tunes['visible']) $c[] = 'is-hidden-block';
        if ($this->variant()) $c[] = 'v-' . $this->variant();
        // Vollbild-Abschnitt (min. 100svh, Inhalt vertikal ausgerichtet) und Hintergrundbild
        if (($this->tunes['height'] ?? 'auto') === 'screen') {
            $c[] = 'sec--screen';
            if (($this->tunes['align'] ?? 'center') !== 'center') $c[] = 'sec--align-' . $this->tunes['align'];
        }
        if ($this->sectionBg() !== '') {
            $c[] = 'sec--has-bg';
            if (($this->tunes['overlay'] ?? 'none') !== 'none') $c[] = 'sec--ov-' . $this->tunes['overlay'];
        }
        return implode(' ', $c);
    }

    private ?string $bgHtml = null;

    /**
     * Hintergrundbild des Abschnitts: absolut positioniertes <picture> hinter dem Inhalt (keine Inline-Styles, CSP).
     * Wird von partials/section.php direkt nach dem öffnenden <section> ausgegeben.
     */
    public function sectionBg(): string
    {
        if ($this->bgHtml !== null) return $this->bgHtml;
        $id = (int) ($this->tunes['bgImage'] ?? 0);
        $pic = $id ? Media::picture($id, '100vw', ['alt' => '', 'eager' => $this->prev === null]) : '';
        return $this->bgHtml = $pic === '' ? '' : '<div class="sec__bg" aria-hidden="true">' . $pic . '</div>';
    }
}
