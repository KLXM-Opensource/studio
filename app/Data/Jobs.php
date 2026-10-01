<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Media;
use Core\Pages;
use Core\Sanitizer;
use Core\StructuredData;

/**
 * Stellenangebote (Google for Jobs, schema.org JobPosting) – Vorlage „Stellenangebote“ + Eingang „Bewerbungen“.
 *
 * Eine Inhaltstabelle ist eine Stellen-Tabelle, sobald ihr schema.org-Typ „JobPosting“ ist (settings.schema_type). Die Felder
 * der Vorlage tragen feste Namen (MAP); einzelne lassen sich über settings.jobs.map auf andere Felder umstellen. Dann gilt:
 *  - Detailseite: genau ein JobPosting-Knoten im @graph (StructuredData::entryNode → jsonLd()), Beschreibung als HTML,
 *    datePosted (ISO-Datum), validThrough (Ende des Tages, ISO mit Zeitzone), Arbeitsort (eigene Felder, sonst Adresse der
 *    Organisation aus dem Kit), TELECOMMUTE + applicantLocationRequirements nur bei „vollständig remote“, baseSalary nur mit Angaben.
 *  - Abgelaufen (Gültig bis < heute): kein JSON-LD, noindex, Hinweis „nicht mehr ausgeschrieben“ (Block job_facts), kein
 *    Formular; aus Listen und Sitemap verschwindet die Stelle (Entries::where → currentSql, nicht in der Verwaltung).
 *  - Bewerbung: Eingang (settings.jobs.form, z. B. „bewerbungen“) mit Feld settings.jobs.field („stelle“). Auf der Detailseite
 *    zeigt der Block job_apply das Formular mit diesem Feld gesperrt und vorbelegt; der Server setzt den Wert selbst aus `_job`
 *    (applyPost) – der Betreff der E-Mail nennt die Stelle (Delivery::message).
 */
final class Jobs
{
    public const TYPE = 'JobPosting';
    /** Feld „Stelle“ im Bewerbungs-Eingang */
    public const FIELD = 'stelle';
    public const DEFAULTS = ['form' => '', 'field' => self::FIELD, 'map' => []];
    /** Rolle → Feldname der Vorlage */
    public const MAP = [
        'title' => 'titel', 'teaser' => 'kurzbeschreibung', 'description' => 'beschreibung', 'date_posted' => 'veroeffentlicht_am',
        'valid_through' => 'gueltig_bis', 'employment' => 'beschaeftigungsart', 'start' => 'beginn', 'workplace' => 'arbeitsweise',
        'street' => 'strasse', 'zip' => 'plz', 'city' => 'ort', 'region' => 'bundesland', 'country' => 'land',
        'salary_min' => 'gehalt_von', 'salary_max' => 'gehalt_bis', 'salary_unit' => 'gehalt_pro', 'employer' => 'arbeitgeber',
        'identifier' => 'kennung', 'contact' => 'ansprechperson', 'contact_email' => 'kontakt_e_mail', 'contact_phone' => 'kontakt_telefon',
        'image' => 'bild',
    ];
    /**
     * Beschäftigungsarten: Kurzname → [Text, Google-Wert]. Google kennt nur FULL_TIME, PART_TIME, CONTRACTOR, TEMPORARY, INTERN,
     * VOLUNTEER, PER_DIEM, OTHER: Minijob und Werkstudium sind Teilzeit, eine Ausbildung ist kein Praktikum (INTERN) → OTHER.
     */
    public const EMPLOYMENT = [
        'vollzeit' => ['Vollzeit', 'FULL_TIME'], 'teilzeit' => ['Teilzeit', 'PART_TIME'], 'minijob' => ['Minijob', 'PART_TIME'],
        'befristet' => ['Befristet / Aushilfe', 'TEMPORARY'], 'ausbildung' => ['Ausbildung', 'OTHER'], 'praktikum' => ['Praktikum', 'INTERN'],
        'werkstudium' => ['Werkstudium', 'PART_TIME'], 'freie_mitarbeit' => ['Freie Mitarbeit', 'CONTRACTOR'], 'ehrenamt' => ['Ehrenamt', 'VOLUNTEER'],
        'sonstiges' => ['Sonstiges', 'OTHER'],
    ];
    public const GOOGLE_TYPES = ['FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'INTERN', 'VOLUNTEER', 'PER_DIEM', 'OTHER'];
    public const UNITS = ['HOUR' => 'Stunde', 'DAY' => 'Tag', 'WEEK' => 'Woche', 'MONTH' => 'Monat', 'YEAR' => 'Jahr'];
    public const WORKPLACE = ['vor_ort' => 'Vor Ort', 'hybrid' => 'Vor Ort und Homeoffice', 'remote' => 'Vollständig remote (Homeoffice)'];

    // ================================================================= Vorlagen (Daten → Neue Tabelle aus Vorlage, data:template)

    /** Vorlage „Stellenangebote“ (Inhaltstabelle) im Format von DataController::PRESETS */
    public static function preset(): array
    {
        $opt = fn(array $o) => implode("\n", array_map(fn($k, $v) => "$k=" . (is_array($v) ? $v[0] : $v), array_keys($o), $o));
        return ['name' => 'Stellenangebote', 'singular' => 'Stelle', 'icon' => 'briefcase', 'route' => 'stellen', 'sort' => ['veroeffentlicht_am', 'desc'],
            'schema_type' => self::TYPE, 'jobs' => ['form' => '_new'], 'fields' => [
                ['label' => 'Titel', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'searchable' => 1, 'labels' => ['en' => 'Job title'],
                    'help' => 'Nur die Berufsbezeichnung, z. B. „Medizinische Fachangestellte (m/w/d)“ – ohne Ort, Gehalt oder „Wir suchen“ (Vorgabe von Google).'],
                ['label' => 'Kurzbeschreibung', 'type' => 'textarea', 'searchable' => 1, 'labels' => ['en' => 'Summary'],
                    'help' => 'Ein bis zwei Sätze für die Übersicht und Suchergebnisse.'],
                ['label' => 'Beschreibung', 'type' => 'richtext', 'required' => 1, 'searchable' => 1, 'labels' => ['en' => 'Description'],
                    'help' => 'Die vollständige Anzeige: Aufgaben, Profil, Angebot, Kontakt. Google zeigt genau diesen Text.'],
                ['label' => 'Veröffentlicht am', 'type' => 'date', 'required' => 1, 'in_list' => 1, 'width' => 'half', 'labels' => ['en' => 'Posted on'],
                    'help' => 'Tag der Ausschreibung (für Google „datePosted“).'],
                ['label' => 'Gültig bis', 'type' => 'date', 'in_list' => 1, 'width' => 'half', 'labels' => ['en' => 'Apply by'],
                    'help' => 'Danach verschwindet die Stelle aus Listen, Sitemap und Google for Jobs. Leer = bis auf Weiteres – dann bei Besetzung auf Entwurf stellen.'],
                ['label' => 'Beschäftigungsart', 'type' => 'multiselect', 'in_list' => 1, 'options' => $opt(self::EMPLOYMENT), 'labels' => ['en' => 'Employment type'],
                    'options_i18n' => ['en' => ['vollzeit' => 'Full-time', 'teilzeit' => 'Part-time', 'minijob' => 'Mini-job', 'befristet' => 'Temporary',
                        'ausbildung' => 'Apprenticeship', 'praktikum' => 'Internship', 'werkstudium' => 'Working student', 'freie_mitarbeit' => 'Freelance',
                        'ehrenamt' => 'Volunteer', 'sonstiges' => 'Other']]],
                ['label' => 'Beginn', 'type' => 'text', 'width' => 'half', 'labels' => ['en' => 'Start'], 'help' => 'z. B. „ab sofort“ oder „1. Januar 2027“'],
                ['label' => 'Arbeitsweise', 'type' => 'select', 'width' => 'half', 'options' => $opt(self::WORKPLACE), 'labels' => ['en' => 'Workplace'],
                    'options_i18n' => ['en' => ['vor_ort' => 'On site', 'hybrid' => 'On site and remote', 'remote' => 'Fully remote']],
                    'help' => 'Nur „Vollständig remote“ meldet die Stelle Google als Homeoffice-Stelle.'],
                ['label' => 'Straße', 'type' => 'text', 'width' => 'half', 'labels' => ['en' => 'Street'], 'help' => 'Arbeitsort – leer = Adresse aus den Einstellungen der Website.'],
                ['label' => 'PLZ', 'type' => 'text', 'width' => 'half', 'labels' => ['en' => 'Postcode']],
                ['label' => 'Ort', 'type' => 'text', 'width' => 'half', 'labels' => ['en' => 'City']],
                ['label' => 'Bundesland', 'type' => 'text', 'width' => 'half', 'labels' => ['en' => 'Region'], 'help' => 'Von Google empfohlen, z. B. „NRW“.'],
                ['label' => 'Land', 'type' => 'text', 'width' => 'half', 'labels' => ['en' => 'Country'], 'help' => 'Ländercode, leer = DE.'],
                ['label' => 'Gehalt von', 'type' => 'number', 'width' => 'half', 'labels' => ['en' => 'Salary from'], 'help' => 'Optional, in Euro. Leer = keine Angabe.'],
                ['label' => 'Gehalt bis', 'type' => 'number', 'width' => 'half', 'labels' => ['en' => 'Salary to']],
                ['label' => 'Gehalt pro', 'type' => 'select', 'width' => 'half', 'options' => $opt(self::UNITS), 'labels' => ['en' => 'Salary per'],
                    'options_i18n' => ['en' => ['HOUR' => 'hour', 'DAY' => 'day', 'WEEK' => 'week', 'MONTH' => 'month', 'YEAR' => 'year']]],
                ['label' => 'Arbeitgeber', 'type' => 'text', 'width' => 'half', 'labels' => ['en' => 'Employer'], 'help' => 'Leer = Name der Website bzw. Organisation.'],
                ['label' => 'Kennung', 'type' => 'text', 'width' => 'half', 'labels' => ['en' => 'Reference'], 'help' => 'Eigene Stellennummer – leer = automatisch.'],
                ['label' => 'Ansprechperson', 'type' => 'text', 'width' => 'half', 'labels' => ['en' => 'Contact person']],
                ['label' => 'Kontakt E-Mail', 'type' => 'email', 'width' => 'half', 'labels' => ['en' => 'Contact e-mail']],
                ['label' => 'Kontakt Telefon', 'type' => 'tel', 'width' => 'half', 'labels' => ['en' => 'Contact phone']],
                ['label' => 'Bild', 'type' => 'media', 'labels' => ['en' => 'Image']],
            ], 'title' => 'titel', 'image' => 'bild', 'desc' => 'kurzbeschreibung'];
    }

    /** Vorlage „Bewerbungen“ (Eingang, verschlüsselt bzw. nur per E-Mail) – Dateien nur mit Zustellung per E-Mail */
    public static function applicationsPreset(): array
    {
        $files = Delivery::available();
        $fields = [
            ['label' => 'Stelle', 'type' => 'text', 'labels' => ['en' => 'Position'], 'help' => 'Auf der Seite einer Stelle ist das Feld schon ausgefüllt.'],
            ['label' => 'Vorname', 'type' => 'text', 'required' => 1, 'width' => 'half', 'labels' => ['en' => 'First name']],
            ['label' => 'Nachname', 'type' => 'text', 'required' => 1, 'width' => 'half', 'labels' => ['en' => 'Last name']],
            ['label' => 'E-Mail', 'type' => 'email', 'required' => 1, 'width' => 'half', 'labels' => ['en' => 'E-mail']],
            ['label' => 'Telefon', 'type' => 'tel', 'width' => 'half', 'labels' => ['en' => 'Phone']],
            ['label' => 'Nachricht', 'type' => 'textarea', 'labels' => ['en' => 'Message']],
        ];
        if ($files) {
            $fields[] = ['label' => 'Lebenslauf', 'type' => 'file', 'accept' => ['pdf', 'docx', 'odt'], 'width' => 'half', 'labels' => ['en' => 'CV']];
            $fields[] = ['label' => 'Weitere Unterlagen', 'type' => 'file', 'accept' => ['pdf', 'docx', 'odt', 'image'], 'width' => 'half',
                'labels' => ['en' => 'Further documents'], 'help' => 'z. B. Zeugnisse – am besten in einer PDF-Datei.'];
        }
        return ['name' => 'Bewerbungen', 'singular' => 'Bewerbung', 'icon' => 'envelope-simple', 'route' => '', 'sort' => ['created_at', 'desc'], 'kind' => 'inbox',
            'fields' => $fields, 'title' => '', 'image' => '', 'desc' => '',
            'inbox' => ['title' => 'Bewerbung', 'intro' => '', 'retention_days' => 90],
            'delivery' => ['mode' => $files ? 'mail' : 'system', 'to' => $files ? (string) setting('sys.mail_to', '') : ''],
            'form' => ['success' => 'Vielen Dank für Ihre Bewerbung – wir melden uns bei Ihnen.', 'submit' => 'Bewerbung absenden', 'upload_mb' => 10]];
    }

    // ================================================================= Tabelle & Einstellungen

    public static function is(?array $t): bool
    {
        return $t !== null && !Tables::isInbox($t) && ($t['settings']['schema_type'] ?? '') === self::TYPE;
    }

    /** Stellen-Tabellen dieser Website */
    public static function tables(): array
    {
        return array_values(array_filter(Tables::content(), [self::class, 'is']));
    }

    public static function config(array $t): array
    {
        return (array) ($t['settings']['jobs'] ?? []) + self::DEFAULTS;
    }

    /** settings.jobs prüfen (Tables::validate): Bewerbungs-Eingang (Kurzname, „_new“ = beim Anlegen erzeugen) und Feld „Stelle“ */
    public static function validateSettings(array $s, array $fields, ?array $existing): array
    {
        $out = (array) ($existing ?? []) + self::DEFAULTS;
        if (array_key_exists('form', $s)) {
            $form = (string) $s['form'];
            $out['form'] = $form === '_new' || $form === '' || (($x = Tables::find($form)) && Tables::isInbox($x)) ? $form : '';
        }
        if (array_key_exists('field', $s)) $out['field'] = Tables::normName((string) $s['field']) ?: self::FIELD;
        $names = array_column($fields, 'name');
        $map = [];
        foreach ((array) ($s['map'] ?? $out['map']) as $role => $name) {
            if (isset(self::MAP[$role]) && in_array((string) $name, $names, true)) $map[$role] = (string) $name;
        }
        $out['map'] = $map;
        return $out;
    }

    /** Feldname einer Rolle in dieser Tabelle ('' = Feld gibt es nicht) */
    public static function f(array $t, string $role): string
    {
        $name = (string) (self::config($t)['map'][$role] ?? self::MAP[$role] ?? '');
        return $name !== '' && Tables::field($t, $name) ? $name : '';
    }

    /** Rohwert einer Rolle */
    public static function v(array $t, array $e, string $role): mixed
    {
        $f = self::f($t, $role);
        return $f === '' ? null : ($e[$f] ?? null);
    }

    private static function s(array $t, array $e, string $role): string
    {
        $v = self::v($t, $e, $role);
        return is_scalar($v) ? trim(strip_tags((string) $v)) : '';
    }

    /** Heute in der Zeitzone der Website (JJJJ-MM-TT) */
    public static function today(): string
    {
        return (new \DateTimeImmutable('now', Calendar::tz()))->format('Y-m-d');
    }

    /** Abgelaufen: „Gültig bis“ liegt vor heute */
    public static function expired(array $t, array $e): bool
    {
        $until = self::s($t, $e, 'valid_through');
        return $until !== '' && preg_match('~^\d{4}-\d{2}-\d{2}~', $until) && substr($until, 0, 10) < self::today();
    }

    /** SQL-Bedingung „noch ausgeschrieben“ für Entries::where (null = Tabelle hat kein Feld „Gültig bis“) */
    public static function currentSql(array $t, array &$params): ?string
    {
        $f = self::f($t, 'valid_through');
        if ($f === '') return null;
        $params[] = self::today();
        return "($f IS NULL OR $f = '' OR $f >= ?)";
    }

    /** Bewerbungs-Eingang der Tabelle (nur wenn er Einsendungen annimmt) */
    public static function formTable(array $t): ?array
    {
        $h = (string) self::config($t)['form'];
        if ($h === '' || $h === '_new') return null;
        $x = Tables::find($h);
        return $x && Tables::isInbox($x) && DataForms::enabled($x) ? $x : null;
    }

    /** Feld „Stelle“ eines Bewerbungs-Eingangs (für Betreff und gesperrtes Feld) – null, wenn keine Stellen-Tabelle ihn nutzt */
    public static function fieldFor(array $inbox): ?string
    {
        foreach (self::tables() as $t) {
            $c = self::config($t);
            if ($c['form'] === $inbox['handle'] && Tables::field($inbox, (string) $c['field'])) return (string) $c['field'];
        }
        return null;
    }

    // ================================================================= Werte für Ausgabe und JSON-LD

    /** Stellennummer: eigene Kennung oder automatisch {tabelle}-{id} */
    public static function identifier(array $t, array $e): string
    {
        $own = self::s($t, $e, 'identifier');
        return $own !== '' ? $own : $t['handle'] . '-' . (int) $e['id'];
    }

    /** Text im gesperrten Feld „Stelle“ des Formulars: Titel, mit eigener Kennung in Klammern */
    public static function label(array $t, array $e): string
    {
        $own = self::s($t, $e, 'identifier');
        return mb_substr(Entries::title($t, $e) . ($own !== '' ? ' (' . $own . ')' : ''), 0, 250);
    }

    /** Beschäftigungsart: [Texte (Sprache der Seite), Google-Werte] */
    public static function employment(array $t, array $e): array
    {
        $f = self::f($t, 'employment');
        $fd = $f !== '' ? Tables::field($t, $f) : null;
        $labels = [];
        $types = [];
        foreach ((array) ($e[$f] ?? []) as $k) {
            $k = (string) $k;
            if ($fd) $labels[] = Tables::optionLabel($fd, $k);
            $g = self::EMPLOYMENT[$k][1] ?? (in_array(strtoupper($k), self::GOOGLE_TYPES, true) ? strtoupper($k) : 'OTHER');
            if (!in_array($g, $types, true)) $types[] = $g;
        }
        return [$labels, $types];
    }

    public static function workplace(array $t, array $e): string
    {
        $w = self::s($t, $e, 'workplace');
        return isset(self::WORKPLACE[$w]) ? $w : 'vor_ort';
    }

    public static function country(array $t, array $e): string
    {
        $c = strtoupper(self::s($t, $e, 'country'));
        return preg_match('~^[A-Z]{2}$~', $c) ? $c : strtoupper((string) app()->config->get('country', 'DE'));
    }

    /**
     * Arbeitsort als PostalAddress-Werte: eigene Felder (sobald Straße, PLZ oder Ort ausgefüllt ist), sonst die Adresse der
     * Organisation (Kit → jsonld). ['streetAddress', 'postalCode', 'addressLocality', 'addressRegion', 'addressCountry'] ohne leere.
     */
    public static function address(array $t, array $e): array
    {
        $own = array_filter(['streetAddress' => self::s($t, $e, 'street'), 'postalCode' => self::s($t, $e, 'zip'), 'addressLocality' => self::s($t, $e, 'city')]);
        if (!$own && !Shared::isForeign($t, $e)) {
            $org = (array) (StructuredData::organization()['address'] ?? []);
            $own = array_filter(['streetAddress' => (string) ($org['streetAddress'] ?? ''), 'postalCode' => (string) ($org['postalCode'] ?? ''),
                'addressLocality' => (string) ($org['addressLocality'] ?? ''), 'addressRegion' => (string) ($org['addressRegion'] ?? '')]);
            if (!empty($org['addressCountry']) && self::s($t, $e, 'country') === '') $own['addressCountry'] = (string) (is_array($org['addressCountry']) ? ($org['addressCountry']['name'] ?? '') : $org['addressCountry']);
        }
        if (!$own) return [];
        if (($r = self::s($t, $e, 'region')) !== '') $own['addressRegion'] = $r;
        $own['addressCountry'] = ($own['addressCountry'] ?? '') !== '' ? $own['addressCountry'] : self::country($t, $e);
        return $own;
    }

    /** Gehalt: ['min', 'max', 'unit', 'currency'] oder null (keine Angabe) */
    public static function salary(array $t, array $e): ?array
    {
        $num = fn($v) => is_numeric($v) && (float) $v > 0 ? (float) $v : null;
        $min = $num(self::v($t, $e, 'salary_min'));
        $max = $num(self::v($t, $e, 'salary_max'));
        if ($min === null && $max === null) return null;
        if ($min !== null && $max !== null && $max < $min) [$min, $max] = [$max, $min];
        $unit = strtoupper(self::s($t, $e, 'salary_unit'));
        return ['min' => $min, 'max' => $max, 'unit' => isset(self::UNITS[$unit]) ? $unit : 'MONTH', 'currency' => (string) app()->config->get('currency', 'EUR')];
    }

    public static function salaryText(array $s): string
    {
        $fmt = fn(float $n) => number_format($n, fmod($n, 1.0) ? 2 : 0, ',', '.') . ' ' . ($s['currency'] === 'EUR' ? '€' : $s['currency']);
        $range = $s['min'] !== null && $s['max'] !== null && $s['min'] !== $s['max'] ? $fmt($s['min']) . ' – ' . $fmt($s['max'])
            : ($s['min'] !== null && $s['max'] !== null ? $fmt($s['min']) : ($s['min'] !== null ? lt('ab {amount}', ['amount' => $fmt($s['min'])]) : lt('bis {amount}', ['amount' => $fmt((float) $s['max'])])));
        return $range . ' ' . lt('pro {unit}', ['unit' => lt(self::UNITS[$s['unit']] ?? 'Monat')]);
    }

    /** Kurzzeile für Listen: „Vollzeit, Teilzeit · Moers“ */
    public static function summary(array $t, array $e): string
    {
        [$labels] = self::employment($t, $e);
        $parts = [];
        if ($labels) $parts[] = implode(', ', $labels);
        $a = self::address($t, $e);
        $w = self::workplace($t, $e);
        if ($w === 'remote') $parts[] = lt('Remote');
        elseif (!empty($a['addressLocality'])) $parts[] = $a['addressLocality'] . ($w === 'hybrid' ? ' / ' . lt('Homeoffice möglich') : '');
        return implode(' · ', $parts);
    }

    /** Eckdaten für den Block job_facts: [[Beschriftung, HTML], …] in der Sprache der Seite */
    public static function facts(array $t, array $e): array
    {
        $out = [];
        [$labels] = self::employment($t, $e);
        if ($labels) $out[] = [lt('Beschäftigungsart'), e(implode(', ', $labels))];
        $w = self::workplace($t, $e);
        $a = self::address($t, $e);
        $place = trim(implode(', ', array_filter([$a['streetAddress'] ?? '', trim(($a['postalCode'] ?? '') . ' ' . ($a['addressLocality'] ?? ''))])));
        if ($w === 'remote') $out[] = [lt('Arbeitsort'), e(lt('Vollständig remote (Homeoffice)') . ($place !== '' ? ' · ' . $place : ''))];
        elseif ($place !== '') $out[] = [lt('Arbeitsort'), e($place) . ($w === 'hybrid' ? '<br><span class="jf-sub">' . e(lt('Homeoffice teilweise möglich')) . '</span>' : '')];
        if (($start = self::s($t, $e, 'start')) !== '') $out[] = [lt('Beginn'), e($start)];
        if (($s = self::salary($t, $e))) $out[] = [lt('Gehalt'), e(self::salaryText($s))];
        $posted = self::datePosted($t, $e);
        if ($posted !== '') $out[] = [lt('Ausgeschrieben seit'), e(date_local($posted, 'long'))];
        $until = self::s($t, $e, 'valid_through');
        if ($until !== '' && !self::expired($t, $e)) $out[] = [lt('Bewerbung bis'), e(date_local($until, 'long'))];
        $c = array_filter([
            self::s($t, $e, 'contact') !== '' ? e(self::s($t, $e, 'contact')) : '',
            ($m = self::s($t, $e, 'contact_email')) !== '' ? '<a href="mailto:' . e($m) . '">' . e($m) . '</a>' : '',
            ($p = self::s($t, $e, 'contact_phone')) !== '' ? '<a href="' . e((string) tel_href($p)) . '">' . e($p) . '</a>' : '',
        ]);
        if ($c) $out[] = [lt('Ansprechperson'), implode('<br>', $c)];
        return $out;
    }

    /** Veröffentlicht am (JJJJ-MM-TT): Feld, sonst Veröffentlichung des Eintrags */
    public static function datePosted(array $t, array $e): string
    {
        $d = self::s($t, $e, 'date_posted');
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', substr($d, 0, 10))) return substr($d, 0, 10);
        $p = (string) ($e['published_at'] ?? $e['created_at'] ?? '');
        return $p !== '' ? substr($p, 0, 10) : '';
    }

    /** Arbeitgeber: [Name, eigener Name?] */
    private static function employer(array $t, array $e): array
    {
        $own = self::s($t, $e, 'employer');
        if ($own !== '') return [$own, true];
        if (Shared::isForeign($t, $e)) return [(string) (StructuredData::orgOf($t, $e)['name'] ?? site_name()), true];
        $org = StructuredData::organization();
        return [trim((string) ($org['name'] ?? '')) ?: site_name(), false];
    }

    /**
     * JobPosting für die Detailseite (StructuredData::entryNode). null = abgelaufen (keine strukturierten Daten).
     * Google: title, description (HTML), datePosted, hiringOrganization, jobLocation (oder TELECOMMUTE mit applicantLocationRequirements);
     * empfohlen: validThrough, employmentType, baseSalary, identifier, directApply.
     */
    public static function jsonLd(array $t, array $e, string $url): ?array
    {
        if (self::expired($t, $e)) return null;
        [$name, $ownEmployer] = self::employer($t, $e);
        $org = ['@type' => 'Organization', 'name' => $name];
        if (!$ownEmployer) {
            $o = StructuredData::organization();
            $org['sameAs'] = (string) ($o['url'] ?? '') ?: abs_url(url('/'));
            $logo = $o['logo'] ?? null;
            if (is_array($logo)) $logo = $logo['url'] ?? null;
            if (is_string($logo) && $logo !== '') $org['logo'] = $logo;
        }
        $desc = self::v($t, $e, 'description');
        $html = trim(Sanitizer::block((string) $desc));
        $html = (string) preg_replace(['~<p>(\s|&nbsp;|<br\s*/?>)*</p>~i', '~\s+~u'], ['', ' '], $html);
        $n = ['@type' => self::TYPE, 'title' => Entries::title($t, $e), 'description' => trim($html), 'datePosted' => self::datePosted($t, $e)];
        $until = self::s($t, $e, 'valid_through');
        if ($until !== '' && ($d = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', substr($until, 0, 10) . ' 23:59:59', Calendar::tz()))) {
            $n['validThrough'] = $d->format('c');
        }
        [, $types] = self::employment($t, $e);
        if ($types) $n['employmentType'] = count($types) === 1 ? $types[0] : $types;
        $n['hiringOrganization'] = $org;
        $addr = self::address($t, $e);
        if ($addr) $n['jobLocation'] = ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress'] + $addr];
        if (self::workplace($t, $e) === 'remote') {
            $n['jobLocationType'] = 'TELECOMMUTE';
            $n['applicantLocationRequirements'] = ['@type' => 'Country', 'name' => self::country($t, $e)];
        }
        if ($s = self::salary($t, $e)) {
            $val = ['@type' => 'QuantitativeValue', 'unitText' => $s['unit']];
            if ($s['min'] !== null && $s['max'] !== null && $s['min'] !== $s['max']) $val += ['minValue' => $s['min'], 'maxValue' => $s['max']];
            else $val['value'] = $s['min'] ?? $s['max'];
            $n['baseSalary'] = ['@type' => 'MonetaryAmount', 'currency' => $s['currency'], 'value' => $val];
        }
        $n['identifier'] = ['@type' => 'PropertyValue', 'name' => $name, 'value' => self::identifier($t, $e)];
        $n['directApply'] = self::formTable($t) !== null;
        if (($start = self::s($t, $e, 'start')) !== '') $n['jobStartDate'] = $start;
        $n['url'] = $url;
        $img = self::v($t, $e, 'image') ?: (($f = Tables::imageField($t)) !== '' ? ($e[$f] ?? null) : null);
        if ($img && ($m = Media::find((int) $img))) $n['image'] = site_url() . Media::url($m, 1200);
        return $n;
    }

    /**
     * JobPosting gegen die Pflicht- und Formatvorgaben von Google prüfen. Rückgabe: Liste der Probleme (leer = gültig).
     * Prüft auch empfohlene Angaben, die gesetzt sind, auf ihr Format.
     */
    public static function check(array $n): array
    {
        $p = [];
        $iso = fn($v) => is_string($v) && preg_match('~^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z))?$~', $v) && strtotime($v) !== false;
        if (($n['@type'] ?? '') !== self::TYPE) $p[] = '@type ist nicht JobPosting';
        if (trim((string) ($n['title'] ?? '')) === '') $p[] = 'title fehlt';
        elseif (mb_strlen((string) $n['title']) > 200) $p[] = 'title ist sehr lang (> 200 Zeichen)';
        $plain = trim(strip_tags((string) ($n['description'] ?? '')));
        if ($plain === '') $p[] = 'description fehlt';
        elseif (mb_strlen($plain) < 100) $p[] = 'description ist sehr kurz (< 100 Zeichen) – Google erwartet die vollständige Anzeige';
        if (str_contains((string) ($n['description'] ?? ''), '[#')) $p[] = 'description enthält eine Redaktionsnotiz';
        if (!$iso($n['datePosted'] ?? null)) $p[] = 'datePosted fehlt oder ist kein ISO-8601-Datum';
        if (isset($n['validThrough'])) {
            if (!$iso($n['validThrough'])) $p[] = 'validThrough ist kein ISO-8601-Datum';
            elseif (strtotime((string) $n['validThrough']) < time()) $p[] = 'validThrough liegt in der Vergangenheit';
            elseif ($iso($n['datePosted'] ?? null) && strtotime((string) $n['validThrough']) < strtotime((string) $n['datePosted'])) $p[] = 'validThrough liegt vor datePosted';
        }
        $org = $n['hiringOrganization'] ?? null;
        if (!is_array($org) || trim((string) ($org['name'] ?? '')) === '') $p[] = 'hiringOrganization.name fehlt';
        $remote = ($n['jobLocationType'] ?? '') === 'TELECOMMUTE';
        if (isset($n['jobLocation'])) {
            $a = (array) ($n['jobLocation']['address'] ?? []);
            if (($a['@type'] ?? '') !== 'PostalAddress') $p[] = 'jobLocation.address ist keine PostalAddress';
            if (empty($a['addressCountry'])) $p[] = 'jobLocation.address.addressCountry fehlt';
            if (empty($a['addressLocality']) && empty($a['postalCode'])) $p[] = 'jobLocation.address: Ort oder PLZ fehlt';
        } elseif (!$remote) {
            $p[] = 'jobLocation fehlt (oder „Vollständig remote“ mit Land)';
        }
        if ($remote && empty($n['applicantLocationRequirements']['name'])) $p[] = 'applicantLocationRequirements fehlt bei TELECOMMUTE';
        foreach ((array) ($n['employmentType'] ?? []) as $et) {
            if (!in_array($et, self::GOOGLE_TYPES, true)) $p[] = "employmentType „{$et}“ kennt Google nicht";
        }
        if (isset($n['baseSalary'])) {
            $b = (array) $n['baseSalary'];
            $v = (array) ($b['value'] ?? []);
            if (($b['@type'] ?? '') !== 'MonetaryAmount' || !preg_match('~^[A-Z]{3}$~', (string) ($b['currency'] ?? ''))) $p[] = 'baseSalary: MonetaryAmount mit Währung (ISO 4217) erwartet';
            if (!isset($v['value']) && !(isset($v['minValue']) && isset($v['maxValue']))) $p[] = 'baseSalary.value: Wert oder minValue/maxValue fehlt';
            if (!in_array($v['unitText'] ?? '', array_keys(self::UNITS), true)) $p[] = 'baseSalary.value.unitText muss HOUR, DAY, WEEK, MONTH oder YEAR sein';
        }
        if (isset($n['identifier']) && trim((string) ($n['identifier']['value'] ?? '')) === '') $p[] = 'identifier.value ist leer';
        if (isset($n['directApply']) && !is_bool($n['directApply'])) $p[] = 'directApply muss true/false sein';
        return $p;
    }

    /** Probleme eines Eintrags für die Verwaltung (Hinweis im Eintrag): JSON-LD bauen und prüfen */
    public static function problems(array $t, array $e): array
    {
        if (self::expired($t, $e)) return [__('Die Stelle ist abgelaufen („Gültig bis“) – sie erscheint nicht mehr in Listen, Sitemap und Google for Jobs.')];
        if (empty($t['settings']['route']) || empty($t['settings']['detail_page_id'])) return [__('Ohne Detailseite (Adresse + Vorlage) gibt es keine Stellenanzeige für Google.')];
        $n = self::jsonLd($t, $e, (string) (Entries::absUrl($t, $e) ?? site_url()));
        return $n ? self::check($n) : [];
    }

    // ================================================================= Bewerbung

    /** Einsendung an einen Bewerbungs-Eingang: Feld „Stelle“ aus `_job` ({tabelle}:{id}) setzen – der Server bestimmt den Text */
    public static function applyPost(array $inbox, array $post): array
    {
        if (!preg_match('~^([a-z][a-z0-9_]{0,40}):(\d+)$~', (string) ($post['_job'] ?? ''), $m)) return $post;
        $t = Tables::findContent($m[1]);
        if (!self::is($t) || self::config($t)['form'] !== $inbox['handle']) return $post;
        $field = (string) self::config($t)['field'];
        $e = Entries::find($t, (int) $m[2]);
        if (!$e || $e['status'] !== 'published' || !Tables::field($inbox, $field)) return $post;
        $post[$field] = self::label($t, $e);
        return $post;
    }

    // ================================================================= Seiten: Detailvorlage, Übersicht

    /** Detailseiten-Vorlage: Kopf (Titel, Bild), Eckdaten mit „Jetzt bewerben“, Beschreibung, Bewerbungsformular (#bewerben) */
    public static function makeTemplate(array $t): int
    {
        $h = $t['handle'] . '.';
        $route = (string) $t['settings']['route'];
        $list = $route !== '' ? Pages::byPath($route) : null;
        $id = fn() => bin2hex(random_bytes(5));
        $head = ['table' => $t['handle'], 'layout' => 'head', 'fields' => array_values(array_filter([$h . '_title', ($img = Tables::imageField($t)) !== '' ? $h . $img : null])), 'ratio' => '16:9'];
        if ($list && empty($list['is_home'])) $head += ['back_label' => 'Alle Stellenangebote', 'back_link' => 'page:' . $list['id']];
        $desc = self::f($t, 'description');
        $blocks = [
            ['id' => $id(), 'type' => 'data_fields', 'data' => $head, 'tunes' => ['section' => ['spaceBottom' => 'small']]],
            ['id' => $id(), 'type' => 'job_facts', 'data' => ['title' => '', 'apply_label' => 'Jetzt bewerben'], 'tunes' => ['section' => ['spaceTop' => 'none', 'spaceBottom' => 'small']]],
            ['id' => $id(), 'type' => 'data_fields', 'data' => ['table' => $t['handle'], 'layout' => 'prose', 'fields' => $desc !== '' ? [$h . $desc] : [], 'ratio' => '16:9'],
                'tunes' => ['section' => ['spaceTop' => 'small', 'spaceBottom' => 'small']]],
            ['id' => $id(), 'type' => 'job_apply', 'data' => ['title' => 'Jetzt bewerben', 'intro' => '', 'form_width' => 'normal', 'form_align' => 'left'],
                'tunes' => ['section' => ['anchor' => 'bewerben', 'spaceTop' => 'small']]],
        ];
        $pid = Pages::create(['slug' => '_vorlage-' . $t['handle'], 'title' => $t['name'] . ' – Detailseite', 'type' => 'template',
            'template_for' => $t['handle'], 'status' => 'published', 'noindex' => 0], Pages::sanitizeBlocks($blocks));
        Tables::setDetailPage($t, $pid);
        return $pid;
    }

    /** Übersichtsseite unter der Adresse der Tabelle (nur einstufige Adresse, nicht im Menü): Datenliste der offenen Stellen */
    public static function makeListPage(array $t, string $title = ''): ?int
    {
        $route = (string) $t['settings']['route'];
        if ($route === '' || str_contains($route, '/') || Pages::byPath($route)) return null;
        $h = $t['handle'] . '.';
        $fields = array_values(array_filter([$h . '_title', ($f = self::f($t, 'teaser')) !== '' ? $h . $f : null]));
        $blocks = Pages::sanitizeBlocks([['id' => bin2hex(random_bytes(5)), 'type' => 'data_list', 'data' => ['eyebrow' => '', 'title' => $title ?: $t['name'], 'intro' => '',
            'table' => $t['handle'], 'fields' => $fields, 'layout' => 'list', 'columns' => '2', 'ratio' => '16:10', 'limit' => 0, 'link_detail' => true,
            'empty_text' => 'Zurzeit sind keine Stellen ausgeschrieben.']]]);
        return Pages::create(['slug' => $route, 'title' => $title ?: $t['name'], 'status' => 'published', 'menu' => 0], $blocks);
    }

    /** Bewerbungs-Eingang anlegen (Vorlage „Bewerbungen“) oder vorhandenen gleichen Kurznamens nutzen; Rückgabe: Eingang oder null */
    public static function ensureForm(string $handle = 'bewerbungen', ?callable $log = null): ?array
    {
        $log ??= fn(string $s) => null;
        if ($x = Tables::find($handle)) {
            if (!Tables::isInbox($x)) { $log("! „{$handle}“ ist keine Eingangs-Tabelle – kein Bewerbungsformular verknüpft."); return null; }
            if (!Tables::field($x, self::FIELD)) {
                // Feld „Stelle“ vorn ergänzen (wird auf Stellenseiten vorbelegt) – übrige Felder und Einstellungen bleiben
                $in = Tables::toInput($x);
                array_unshift($in['fields'], ['label' => 'Stelle', 'type' => 'text', 'labels' => ['en' => 'Position'], 'help' => 'Auf der Seite einer Stelle ist das Feld schon ausgefüllt.']);
                [$def, $errors] = Tables::validate($in, $x);
                if ($errors) { $log('! Feld „Stelle“ nicht ergänzt: ' . implode(' ', $errors)); return $x; }
                Tables::update($x, $def);
                $log("✓ Eingang „{$x['name']}“: Feld „Stelle“ ergänzt");
                $x = Tables::find($handle);
            }
            return $x;
        }
        if (!Inbox::available()) { $log('! Eingänge (Funktion „requests“) sind aus – kein Bewerbungsformular angelegt.'); return null; }
        $def = \Core\Http\Controllers\Admin\DataController::presetDef('applications');
        $def['handle'] = $handle;
        [$clean, $errors] = Tables::validate($def);
        if ($errors) { $log('! Eingang „Bewerbungen“ nicht angelegt: ' . implode(' ', $errors)); return null; }
        Tables::create($clean);
        $x = Tables::find($handle);
        $log("✓ Eingang „{$x['name']}“ angelegt (" . Delivery::modeLabel(Delivery::mode($x)) . (Delivery::mails($x) && Delivery::config($x)['to'] === '' ? ' – Empfänger noch eintragen' : '') . ')');
        return $x;
    }

    /** Stellen-Tabelle mit dem Eingang verknüpfen (settings.jobs.form) */
    public static function linkForm(array $t, string $form): void
    {
        $in = Tables::toInput($t);
        $in['settings']['jobs'] = ['form' => $form] + self::config($t);
        $in['settings']['jobs']['form'] = $form;
        [$def, $errors] = Tables::validate($in, $t);
        if (!$errors) Tables::update($t, $def);
    }

    // ================================================================= Selbsttest (php bin/console jobs:selftest)

    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        // Vorlage: Felder, Einstellungen, Zuordnung aller Rollen
        $def = \Core\Http\Controllers\Admin\DataController::presetDef('jobs');
        $def['handle'] = 'selftest_jobs_' . bin2hex(random_bytes(3));
        $def['settings']['jobs']['form'] = '';
        $def['settings']['route'] = '';   // Adresse wäre schon vergeben, sobald die Website eine Stellen-Tabelle hat
        [$clean, $errors] = Tables::validate($def);
        $eq('Vorlage gültig', $errors, []);
        $eq('schema.org-Typ', $clean['settings']['schema_type'] ?? null, self::TYPE);
        $names = array_column($clean['fields'], 'name');
        foreach (self::MAP as $role => $name) $eq("Feld für {$role}", in_array($name, $names, true), true);
        $eq('Google-Werte der Beschäftigungsarten', array_diff(array_column(self::EMPLOYMENT, 1), self::GOOGLE_TYPES), []);
        $app = self::applicationsPreset();
        $eq('Bewerbungen: Feld „Stelle“', in_array(self::FIELD, array_map(fn($f) => Tables::normName($f['label']), $app['fields']), true), true);

        // JSON-LD ohne Datenbank: vorübergehende Tabelle im Speicher
        $t = $clean + ['id' => 0, 'table' => 'data_' . $def['handle']];
        $t['settings'] += ['detail_page_id' => null];
        $base = ['id' => 7, 'slug' => 'test', 'status' => 'published', 'published_at' => '2026-09-28 16:40:01', 'titel' => 'Medizinische Fachangestellte (m/w/d)',
            'beschreibung' => '<p>' . str_repeat('Wir suchen Verstärkung für unser Team. ', 5) . '</p><p><br></p><h3>Ihre Aufgaben</h3><ul><li>Empfang</li></ul>',
            'veroeffentlicht_am' => '2026-09-28', 'gueltig_bis' => '', 'beschaeftigungsart' => ['vollzeit', 'teilzeit', 'minijob'],
            'strasse' => 'Teststraße 1', 'plz' => '12345', 'ort' => 'Teststadt', 'land' => '', 'arbeitsweise' => '', 'gehalt_von' => null, 'gehalt_bis' => null, 'gehalt_pro' => ''];
        $n = self::jsonLd($t, $base, 'https://example.org/stellen/test');
        $eq('JSON-LD gültig', self::check((array) $n), []);
        $eq('employmentType ohne Doppelte', $n['employmentType'] ?? null, ['FULL_TIME', 'PART_TIME']);
        $eq('datePosted', $n['datePosted'] ?? null, '2026-09-28');
        $eq('Leere Absätze entfernt', str_contains((string) ($n['description'] ?? ''), '<p><br></p>'), false);
        $eq('ohne Gehalt kein baseSalary', isset($n['baseSalary']), false);
        $eq('Adresse mit Land DE', $n['jobLocation']['address']['addressCountry'] ?? null, 'DE');
        $eq('identifier automatisch', $n['identifier']['value'] ?? null, $def['handle'] . '-7');
        $eq('kein TELECOMMUTE vor Ort', isset($n['jobLocationType']), false);
        $eq('JSON kodierbar', json_encode($n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !== false, true);

        $future = (new \DateTimeImmutable('+30 days', Calendar::tz()))->format('Y-m-d');
        $n = self::jsonLd($t, ['gueltig_bis' => $future, 'gehalt_von' => 3200, 'gehalt_bis' => 2800, 'gehalt_pro' => 'MONTH', 'arbeitsweise' => 'remote', 'kennung' => 'MFA-1'] + $base, 'https://example.org/x');
        $eq('JSON-LD remote + Gehalt gültig', self::check((array) $n), []);
        $eq('validThrough Ende des Tages', substr((string) ($n['validThrough'] ?? ''), 0, 19), $future . 'T23:59:59');
        $eq('Gehalt: von/bis getauscht', [$n['baseSalary']['value']['minValue'] ?? null, $n['baseSalary']['value']['maxValue'] ?? null], [2800.0, 3200.0]);
        $eq('TELECOMMUTE', $n['jobLocationType'] ?? null, 'TELECOMMUTE');
        $eq('Bewerber aus Land', $n['applicantLocationRequirements']['name'] ?? null, 'DE');
        $eq('eigene Kennung', $n['identifier']['value'] ?? null, 'MFA-1');
        $n = self::jsonLd($t, ['gehalt_von' => 15, 'gehalt_pro' => 'HOUR'] + $base, 'https://example.org/x');
        $eq('Gehalt: ein Wert', $n['baseSalary']['value']['value'] ?? null, 15.0);

        $past = (new \DateTimeImmutable('-1 day', Calendar::tz()))->format('Y-m-d');
        $eq('abgelaufen erkannt', self::expired($t, ['gueltig_bis' => $past] + $base), true);
        $eq('heute noch gültig', self::expired($t, ['gueltig_bis' => self::today()] + $base), false);
        $eq('abgelaufen: kein JSON-LD', self::jsonLd($t, ['gueltig_bis' => $past] + $base, 'https://example.org/x'), null);
        $p = [];
        $sql = self::currentSql($t, $p);
        $eq('SQL „noch ausgeschrieben“', [$sql, $p], ["(gueltig_bis IS NULL OR gueltig_bis = '' OR gueltig_bis >= ?)", [self::today()]]);

        // Prüfung erkennt Fehler
        $bad = self::check(['@type' => 'JobPosting', 'title' => '', 'description' => 'kurz', 'datePosted' => '28.09.2026', 'employmentType' => 'VOLLZEIT',
            'hiringOrganization' => ['name' => '']]);
        $eq('Prüfung meldet Fehler', count($bad) >= 5, true);
        // Gesperrtes Feld: der Server setzt die Stelle, nicht der Browser (ohne gültige Stelle bleibt die Eingabe)
        $eq('applyPost ohne _job', self::applyPost(['handle' => 'x', 'fields' => []], ['stelle' => 'frei']), ['stelle' => 'frei']);
        $eq('applyPost mit falschem _job', self::applyPost(['handle' => 'x', 'fields' => []], ['_job' => 'gibtsnicht:1', 'stelle' => 'a'])['stelle'], 'a');
        // Bestehende Stellen-Tabellen dieser Website: jede veröffentlichte, laufende Stelle muss gültiges JSON-LD liefern
        foreach (self::tables() as $jt) {
            foreach (Entries::query($jt, ['status' => 'published', 'limit' => 200, 'source' => 'own']) as $je) {
                $eq("Stelle „" . Entries::title($jt, $je) . "“ ({$jt['handle']}): Google-Angaben", self::problems($jt, $je), []);
            }
        }
        return ['ok' => $ok, 'fails' => $fails];
    }

    /** Für Formulare und Links: Eintrag als Link-Ziel */
    public static function ref(array $t, array $e): string
    {
        return 'entry:' . $t['handle'] . ':' . (int) $e['id'];
    }
}
