<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Fields;
use Core\Lang;
use Core\Http\Request;
use Core\Http\Response;

/** Zentrales Einstellungsformular des Themes (Titel und Felder vom Theme). */
final class SettingsController extends AdminController
{
    /** Bearbeitete Sprache: leer = Standardsprache, sonst Übersetzung (gespeichert als „feld@en“) */
    private function lang(Request $r): string
    {
        $l = (string) ($r->query['lang'] ?? $r->post['_lang'] ?? '');
        return Lang::multi() && Lang::valid($l) && $l !== Lang::default() ? $l : '';
    }

    public function edit(Request $r, array $errors = [], ?array $values = null): Response
    {
        $this->auth($r, 'settings.edit');
        $theme = app()->theme;
        $lang = $this->lang($r);
        $groups = $theme->settingsGroups();
        $source = [];
        foreach ($theme->settingsFields() as $f) {
            if (isset($f['name'])) $source[$f['name']] = app()->settings->get($f['name']);
        }
        if ($lang !== '') {
            // Übersetzung: nur übersetzbare Felder; leere Felder zeigen später den Text der Standardsprache
            $groups = array_values(array_filter(array_map(fn($g) => ['fields' => Fields::forTranslation($g['fields'], $source)] + $g, $groups), fn($g) => $g['fields']));
            if ($values === null) {
                $values = [];
                foreach ($groups as $g) {
                    foreach ($g['fields'] as $f) {
                        if (!isset($f['name'])) continue;
                        $v = app()->settings->get($f['name'] . '@' . $lang);
                        // Listen (z. B. Hero-Themen) mit der Standardsprache vorbelegen, damit man sie direkt übersetzen kann
                        $values[$f['name']] = $v ?? (($f['type'] ?? '') === 'repeater' ? ($source[$f['name']] ?? []) : null);
                    }
                }
            }
        } elseif ($values === null) {
            $values = $source;
        }
        return $this->view('settings', [
            'title' => $theme->settingsTitle(), 'groups' => $groups, 'lang' => $lang,
            'values' => $values, 'errors' => $errors, 'action' => '/admin/settings',
        ], $errors ? 422 : 200);
    }

    /**
     * Live-Vorschau: Startseite (bzw. ?page=ID) mit den ungespeicherten Formularwerten – nur im Speicher.
     * Mit repeater + index wird nur dieser Eintrag gezeigt (aktiv, ohne Zeitraum), z. B. ein einzelnes Hero-Thema.
     */
    public function preview(Request $r): Response
    {
        $this->auth($r, 'settings.edit');
        $lang = $this->lang($r);
        $fields = app()->theme->settingsFields();
        if ($lang !== '') {
            $fields = array_values(array_filter(Fields::forTranslation($fields), fn($f) => isset($f['name'])));
        }
        [$values] = Fields::sanitize($fields, (array) ($r->post['f'] ?? []));
        // Einzelnen Listeneintrag zuerst zeigen (sichtbar gemacht, Zeitraum ignoriert); Themes lesen preview_focus()
        $rep = (string) ($r->post['_repeater'] ?? '');
        if ($rep !== '' && isset($values[$rep]) && is_array($values[$rep])) {
            $list = array_values($values[$rep]);
            $idx = (int) ($r->post['_index'] ?? 0);
            if (isset($list[$idx])) {
                $def = current(array_filter($fields, fn($f) => ($f['name'] ?? '') === $rep));
                foreach ((array) ($def['fields'] ?? []) as $sf) {
                    if (($sf['type'] ?? '') === 'date') $list[$idx][$sf['name']] = '';
                    if (($sf['type'] ?? '') === 'bool' && in_array($sf['name'], ['aktiv', 'active', 'sichtbar', 'visible'], true)) $list[$idx][$sf['name']] = true;
                }
                $values[$rep] = $list;
                app()->previewFocus[$rep] = $idx;
            }
        }
        $over = [];
        foreach ($values as $k => $v) {
            $over[$lang !== '' ? "$k@$lang" : $k] = $lang !== '' && ($v === '' || $v === [] || $v === null) ? null : $v;
        }
        app()->settings->override($over);
        if ($lang !== '') app()->lang = $lang;
        $page = ctype_digit((string) ($r->post['_page'] ?? '')) ? \Core\Pages::find((int) $r->post['_page']) : \Core\Pages::home($lang !== '' ? $lang : null);
        if (!$page) {
            return Response::json(['ok' => false, 'error' => __('Keine Startseite gefunden.')], 404);
        }
        return Response::json(['ok' => true, 'html' => (new \Core\Http\Controllers\SiteController())->previewHtml($page)]);
    }

    public function save(Request $r): Response
    {
        $this->auth($r, 'settings.edit');
        $lang = $this->lang($r);
        $fields = app()->theme->settingsFields();
        if ($lang !== '') {
            $fields = array_values(array_filter(Fields::forTranslation($fields), fn($f) => isset($f['name'])));
        }
        [$values, $errors] = Fields::sanitize($fields, (array) ($r->post['f'] ?? []));
        if ($errors) {
            app()->session->flash('error', __('Bitte prüfen Sie die markierten Felder – es wurde nichts gespeichert.'));
            return $this->edit($r, $errors, $values);
        }
        if ($lang !== '') {
            foreach ($values as $k => $v) {
                $empty = $v === null || $v === '' || $v === [] || $v == app()->settings->get($k);
                $empty ? app()->settings->delete($k . '@' . $lang) : app()->settings->set($k . '@' . $lang, $v);
            }
        } else {
            app()->settings->setMany($values);
        }
        $this->changed();
        $q = $lang !== '' ? '?lang=' . $lang : '';
        return $this->back('/admin/settings' . $q . ($r->str('_tab') ? '#' . preg_replace('~[^\w\-]~', '', $r->str('_tab')) : ''),
            'success', __('{title} gespeichert.', ['title' => app()->theme->settingsTitle() . ($lang !== '' ? ' (' . Lang::all()[$lang] . ')' : '')]));
    }
}
