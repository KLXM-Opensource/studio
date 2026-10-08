<?php
declare(strict_types=1);

namespace Core\Search;

use Core\AI\Ai;
use Core\Features;
use Core\Lang;

/**
 * Grundeinstellungen → „Suche“ und „KI“: Felder der Website (über SystemSchema gespeichert) plus Status-/Test-Kästen
 * (app/Admin/views/system/_search.php, _ai.php). Verbindungen und Verwendung der Installation (Core\AI\Profiles: Schlüssel,
 * Modelle je Zweck) pflegt nur die Agentur/Netzwerk-Administration (Features::integrator()) – gespeichert in storage/ai/config.json.
 */
final class AdminSettings
{
    public static function groups(): array
    {
        $out = [];
        if (Features::on('search')) {
            $tables = [];
            foreach (\Core\Data\Tables::content() as $t) {
                if (($t['settings']['route'] ?? '') !== '' && !empty($t['settings']['detail_page_id'])) $tables[$t['handle']] = $t['name'];
            }
            $fields = [
                ['type' => 'heading', 'label' => __('Website-Suche'),
                    'help' => __('Besucher suchen unter {path} in Seiten, Einträgen mit Detailseite und – wenn gewünscht – PDF-Dokumenten. Tippfehler und Umlaute sind egal. Der Index aktualisiert sich nach jeder Änderung automatisch.', ['path' => '/' . Search::slug(Lang::default())])],
                ['name' => 'sys.search_exclude', 'label' => __('Diese Datentabellen nicht durchsuchen'), 'type' => 'multiselect', 'options' => $tables,
                    'help' => $tables ? __('Standard: alle Tabellen mit Detailseite. Neue Tabellen sind automatisch dabei.') : __('Keine Datentabelle hat eine Detailseite.')],
                ['name' => 'sys.search_media', 'label' => __('PDF-Dokumente der Mediathek finden (Titel, Alt-Text, Dateiname)'), 'type' => 'bool', 'default' => false],
                ['name' => 'sys.search_boost_title', 'label' => __('Gewichtung Titel'), 'type' => 'number', 'width' => 'half', 'default' => 3,
                    'help' => __('0–10: wie stark Treffer im Titel nach oben rücken.')],
                ['name' => 'sys.search_boost_headings', 'label' => __('Gewichtung Überschriften'), 'type' => 'number', 'width' => 'half', 'default' => 2,
                    'help' => __('0–10: Treffer in Zwischenüberschriften, Beschreibungen, FAQ-Fragen.')],
            ];
            foreach (Lang::all() as $code => $label) {
                $fields[] = ['name' => 'sys.search_syn_' . $code, 'label' => __('Synonyme ({lang})', ['lang' => $label]), 'type' => 'textarea', 'rows' => 3,
                    'placeholder' => $code === 'de' ? "Öffnungszeiten, Sprechzeiten, geöffnet\nAnfahrt, Wegbeschreibung, Parken" : "opening hours, office hours, open",
                    'help' => __('Eine Zeile je Gruppe, Begriffe mit Komma getrennt. Wer einen Begriff sucht, findet auch die anderen.')];
            }
            $fields[] = ['name' => 'sys.search_semantic', 'label' => __('Semantische Suche (KI): findet auch Inhalte mit anderen Worten'), 'type' => 'bool', 'default' => false,
                'help' => __('Braucht KI mit Embeddings (Reiter „KI“). Bei einem externen Anbieter wird der Suchtext der Besucher dorthin übermittelt – Hinweis in der Datenschutzerklärung nötig.')];
            $fields[] = ['name' => 'sys.search_answer', 'label' => __('KI-Antwort über den Treffern'), 'type' => 'select', 'default' => 'question',
                'options' => ['question' => __('Automatisch bei Fragen, sonst auf Klick'), 'auto' => __('Immer automatisch'), 'click' => __('Nur auf Klick („Antwort erzeugen“)'), 'off' => __('Aus')],
                'help' => __('Beantwortet Fragen wie „Was kostet die Teilnahme?“ direkt aus den Inhalten der Website, mit Quellen. Braucht KI für Texte (Reiter „KI“); zählt zum Tageslimit des Besucher-Chats.')];
            $fields[] = ['name' => 'sys.search_misses', 'label' => __('Suchbegriffe ohne Treffer zählen (anonym, für die Redaktion)'), 'type' => 'bool', 'default' => true,
                'help' => __('Nur Begriff und Anzahl – keine IP-Adresse, keine Uhrzeit. Begriffe mit E-Mail-Adressen oder langen Zahlen werden nie gespeichert.')];
            $out[] = ['id' => 'suche', 'label' => __('Suche'), 'fields' => $fields];
        }
        if (Features::on('ai')) {
            $out[] = ['id' => 'ki', 'label' => __('KI'), 'fields' => [
                ['type' => 'heading', 'label' => __('Diese Website: KI-Funktionen'), 'collapse' => 'open',
                    'summary' => app()->settings->get('sys.ai_enabled', false) ? __('eingeschaltet') : __('ausgeschaltet'),
                    'help' => __('Texte schreiben und übersetzen, SEO-Vorschläge, Alt-Texte für Bilder und semantische Suche. Welche Verbindung und welches Modell je Zweck genutzt wird, steht oben unter „Verwendung“.')],
                ['name' => 'sys.ai_enabled', 'label' => __('KI-Funktionen auf dieser Website einschalten'), 'type' => 'bool', 'default' => false],
                ['name' => 'sys.ai_text', 'label' => __('Texte: schreiben, kürzen, übersetzen, SEO'), 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'sys.ai_vision', 'label' => __('Bilder: Alt-Texte vorschlagen'), 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'sys.ai_embed', 'label' => __('Embeddings: semantische Suche'), 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'sys.ai_transcribe', 'label' => __('Sprache → Text: Untertitel für Videos und Audio'), 'type' => 'bool', 'default' => true, 'width' => 'half'],
                // KI-Assistent der Redaktion (Core\AI\Assist, Anweisungen in app/AI/Prompts.php)
                ['type' => 'heading', 'label' => __('KI-Assistent der Redaktion'), 'collapse' => true,
                    'help' => __('Die KI macht nur Vorschläge – sie werden erst gespeichert, wenn jemand sie prüft und übernimmt. Sie erfindet keine Fakten, sondern setzt „[bitte ergänzen: …]“.'),
                    'links' => [['label' => __('SEO-Übersicht öffnen'), 'url' => '/admin/ai/seo']]],
                ['name' => 'sys.ai_daily_cap', 'label' => __('Tageslimit: KI-Aufrufe je Tag (Texte + Bilder)'), 'type' => 'number', 'width' => 'half', 'default' => \Core\AI\Assist::DEFAULT_DAILY_CAP,
                    'help' => __('0 = unbegrenzt. Die Suche zählt nicht mit; der heutige Stand steht unten unter „Nutzung“.')],
                ['name' => 'sys.ai_notes', 'label' => __('Hinweise für die KI (Zielgruppe, Anrede, Stil)'), 'type' => 'textarea', 'rows' => 3, 'max' => 1500,
                    'placeholder' => __('z. B. Wir sprechen Besucherinnen und Besucher mit „Sie“ an, schreiben freundlich und ohne Fachjargon.')],
                ['name' => 'sys.ai_glossary', 'label' => __('Glossar: nicht übersetzen / feste Übersetzung'), 'type' => 'textarea', 'rows' => 4,
                    'placeholder' => "Musterfirma GmbH\nStadtfest Musterstadt\nÖffnungszeiten = opening hours",
                    'help' => __('Eine Zeile je Begriff. „Begriff“ bleibt in jeder Sprache gleich (z. B. Marken- und Eigennamen), „Begriff = Übersetzung“ wird immer so übersetzt.')],
                ...(Features::on('chat.assistant') ? [['name' => 'sys.ai_assistant', 'label' => __('Assistent-Chat für die Redaktion (Fragen zur Bedienung, Aktionen mit Bestätigung)'), 'type' => 'bool', 'default' => true,
                    'help' => __('Überall in der Verwaltung erreichbar (Seitenleiste, Suche ⌘K, Tastenkürzel). Zählt zum Tageslimit oben; Aktionen laufen mit den Rechten der Person und – falls eingestellt – über „Eingereicht“.')]] : []),
                ...self::chatFields(),
            ]];
        }
        return $out;
    }

    /** Felder „Besucher-Chat“ (Core\AI\VisitorChat) – nur, wenn die Agentur die Funktion „chat.visitor“ freigegeben hat */
    private static function chatFields(): array
    {
        if (!Features::on('chat.visitor')) return [];
        return [
            ['type' => 'heading', 'label' => __('Besucher-Chat'), 'collapse' => true,
                'summary' => app()->settings->get('sys.chat_enabled', false) ? __('auf der Website sichtbar') : __('aus'),
                'help' => __('Ein Chat-Knopf auf der Website beantwortet Fragen von Besuchern – ausschließlich aus den Inhalten dieser Website (Suchindex, FAQ, Einträge, Kontakt und Öffnungszeiten) und mit Links zu den Quellen. Findet er nichts, sagt er das und bietet den Kontakt an. Fragen und Antworten werden nicht gespeichert, es gibt keine Cookies.')],
            ['name' => 'sys.chat_enabled', 'label' => __('Besucher-Chat auf der Website zeigen'), 'type' => 'bool', 'default' => false,
                'help' => __('Braucht KI für Texte und die Website-Suche. Vorher den Datenschutz-Absatz (unten) in die Datenschutzerklärung übernehmen.')],
            ['name' => 'sys.chat_greeting', 'label' => __('Begrüßung'), 'type' => 'textarea', 'rows' => 2, 'max' => 300,
                'placeholder' => __('Leer = Standardtext in der Sprache der Seite'),
                'help' => __('Eigene Texte (Begrüßung, Fragen, Hinweise) gelten für die Standardsprache; in weiteren Sprachen erscheinen die übersetzten Standardtexte.')],
            ['name' => 'sys.chat_suggestions', 'label' => __('Vorgeschlagene Fragen (eine je Zeile, höchstens 4)'), 'type' => 'textarea', 'rows' => 3, 'max' => 600,
                'placeholder' => __("Wann haben Sie geöffnet?\nWie bekomme ich ein Rezept?")],
            ['name' => 'sys.chat_contact_text', 'label' => __('Text, wenn der Chat keine Antwort findet'), 'type' => 'textarea', 'rows' => 2, 'max' => 400,
                'placeholder' => __('Leer = Standardtext („Dazu finde ich auf dieser Website leider keine verlässliche Angabe …“)')],
            ['name' => 'sys.chat_contact_url', 'label' => __('Link „Anfrage senden“ (Kontaktseite oder Formular)'), 'type' => 'link', 'width' => 'half',
                'help' => __('Optional. Telefon und E-Mail kommen aus den zentralen Angaben.')],
            ['name' => 'sys.chat_privacy_url', 'label' => __('Link zur Datenschutzerklärung'), 'type' => 'link', 'width' => 'half'],
            ['name' => 'sys.chat_privacy', 'label' => __('Datenschutz-Hinweis vor der ersten Frage'), 'type' => 'textarea', 'rows' => 3, 'max' => 800,
                'placeholder' => __('Leer = Standardtext (je nach Anbieter: eigener Server oder KI-Dienst)')],
            ['name' => 'sys.chat_daily', 'label' => __('Tageslimit: Antworten je Tag'), 'type' => 'number', 'width' => 'half', 'default' => \Core\AI\VisitorChat::DEFAULT_DAILY,
                'help' => __('0 = unbegrenzt. Zählt getrennt vom Tageslimit der Redaktion. Zusätzlich gilt je Besucher: {m} Fragen pro Minute, {d} pro Tag.', ['m' => \Core\AI\VisitorChat::PER_MINUTE, 'd' => \Core\AI\VisitorChat::PER_DAY])],
            ['name' => 'sys.chat_position', 'label' => __('Position des Chat-Knopfs'), 'type' => 'select', 'width' => 'half', 'default' => 'right',
                'options' => ['right' => __('unten rechts'), 'left' => __('unten links')]],
            // Seitenauswahl (Core\PagePicker): IDs wie bisher, „12*“ = mit Unterseiten
            ['name' => 'sys.chat_exclude', 'label' => __('Auf diesen Seiten keinen Chat zeigen'), 'type' => 'pages', 'store' => 'ids',
                'help' => __('„mit Unterseiten“ blendet den Chat auch auf allen Seiten darunter aus.')],
        ];
    }

    /** Text für die Datenschutzerklärung (je nach Anbieter) */
    public static function privacyText(): string
    {
        $c = Ai::capability('embed');
        $t = Ai::capability('text');
        $name = Ai::PROVIDERS[$c['provider'] ?: $t['provider']] ?? __('KI-Dienstleister');
        $ext = $c['external'] || $t['external'];
        return $ext
            ? __('Website-Suche: Wenn Sie unsere Suche nutzen, wird Ihr Suchbegriff zur Verbesserung der Ergebnisse an {name} übermittelt und dort in einen Zahlenvektor umgerechnet (Art. 6 Abs. 1 lit. f DSGVO, berechtigtes Interesse an einer hilfreichen Suche). Ihre IP-Adresse und weitere Daten werden dabei nicht übermittelt; wir speichern Suchbegriffe nicht personenbezogen. Mit dem Anbieter besteht ein Vertrag zur Auftragsverarbeitung.', ['name' => $name])
            : __('Website-Suche: Suchbegriffe werden ausschließlich auf unserem eigenen Server verarbeitet (auch die KI-gestützte Suche) und nicht an Dritte übermittelt. Wir speichern Suchbegriffe nicht personenbezogen.');
    }
}
