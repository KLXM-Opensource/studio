<?php
declare(strict_types=1);

namespace Core;

use Core\Search\Search;

/**
 * Kopfbereich-Aktionen: Handlungsaufruf (CTA), zweite Aktion, Suche, Kontakt-Chip mit Öffnungsstatus, Sprachumschalter,
 * Social-Symbole und Anmelde-Link – einheitlich einstellbar unter Verwaltung → Design → „Kopfbereich: Suche & Aktionen“,
 * gestaltet vom Kit.
 *
 * Kit (design.php):   'groups' => [ …, \Core\HeaderActions::designGroup(['ha_cta_style' => 'link', 'ha_search' => 'inline']) ]
 * Kit (theme.php):    Website → Darstellung: ...\Core\HeaderActions::settingsFields()   (Beschriftungen, Links, Status-Bezeichnung)
 *                     'header_actions' => ['classes' => ['solid' => 'btn btn--primary btn--small', 'outline' => 'btn btn--ghost btn--small'],
 *                                      'kit_css' => 'css/header-actions-kit.css',   // optional: Kit-Regeln, nur mit dem Kern-Bündel geladen
 *                                      'base_css' => false,                        // optional: schlichte Auswahl ohne Kern-Datei
 *                                      'late' => true, 'kit_css_late' => 'css/…']   // Layout ruft header_actions_late() vor </body>
 * Layout (<head>):    <?= header_actions_head() ?>          VOR dem Kit-CSS; nur benötigte Teile als ein Bündel (public/assets/ha/), JS nie im Bearbeitungsmodus
 * Kopf-Template:      <?= header_actions('center') ?>       Suche mittig (Anordnung „Suche mittig“), sonst leer
 *                     <?= header_actions('bar', ['compact' => true]) ?>   Aktionen in der Leiste (compact: mobil verkleinern)
 *                     <?= header_actions('below', ['wrap' => 'wrap']) ?>  Suchleiste unter dem Kopf („Suchleiste“ / „unter der Navigation“)
 *                     <?= header_actions_lang($langs, 'hdr__lang') ?>     Sprachumschalter im gewählten Stil
 * Eigenes Markup:     kits/{kit}/templates/partials/header-actions.php (bekommt $ha = model(), $slot, $opt) statt app/Views/header-actions.php
 */
final class HeaderActions
{
    /** Voreinstellungen, wenn das Kit nichts anderes angibt (entspricht dem bisherigen Kopfbereich) */
    public const DEFAULTS = [
        'ha_cta_style' => 'solid', 'ha_cta' => 'custom', 'ha_cta2' => 'none', 'ha_search' => 'popover', 'ha_layout' => 'right',
        'ha_contact' => 'none', 'ha_status' => true, 'ha_lang' => 'code', 'ha_social' => false, 'ha_account' => false, 'ha_menu_icon' => 'chats',
    ];

    /** Seiten-Slugs, unter denen die Vorgaben „Kontakt“, „Termin“, „Newsletter“ ihr Ziel suchen (erste veröffentlichte gewinnt) */
    private const SLUGS = [
        'contact' => ['kontakt', 'contact', 'kontaktformular', 'anfrage'],
        'appointment' => ['termin', 'termine', 'terminvereinbarung', 'termin-anfragen', 'appointment', 'appointments', 'booking'],
        'newsletter' => ['newsletter', 'mitmachen', 'abonnieren', 'subscribe'],
    ];

    private static ?array $model = null;
    private static string $modelKey = '';

    // ------------------------------------------------------------------ Definition (Kit)

    /**
     * Gruppe für den Style-Editor. $defaults überschreibt DEFAULTS je Kit; $opt['exclude'] = Tokens, die das Kit nicht anbietet
     * (z. B. ['ha_status'] ohne Öffnungszeiten), $opt['label'] = Gruppenname.
     */
    public static function designGroup(array $defaults = [], array $opt = []): array
    {
        $t = fn(string $s): string => $s;   // Markierung für i18n:missing (Übersetzung im Style-Editor über __())
        $d = $defaults + self::DEFAULTS;
        $tokens = [
            ['name' => 'ha_cta_style', 'label' => $t('Handlungsaufruf – Stil'), 'type' => 'choice', 'default' => $d['ha_cta_style'],
                'options' => [
                    'solid' => $t('Gefüllt'),
                    'outline' => $t('Kontur'),
                    'link' => $t('Textlink →'),
                    'icon' => $t('Symbol + Text'),
                    'split' => $t('Geteilt'),
                    'chip' => $t('Chip mit Punkt'),
                    'menu' => $t('Kontakt-Menü'),
                    'navitem' => $t('Menüpunkt'),
                    'none' => $t('Kein'),
                ],
                'help' => $t('Es muss nicht immer ein Button sein: Button (gefüllt/Kontur) für EINE starke Handlung, „Menüpunkt“ (abgesetzter letzter Punkt der Navigation) für ruhige Websites, „Kontakt-Menü“ (Symbol mit Aufklappliste: Anrufen, E-Mail, Formular, Anfahrt, Öffnungszeiten), wenn mehrere Kontaktwege zählen. Dazu Textlink mit Pfeil, Symbol mit Text, geteilt (Aktion + Anruf-/E-Mail-Taste) und Chip mit Statuspunkt. Ecken immer 8 px, den Look bestimmt das Kit; mobil wandert die Aktion ins Menü oder wird zum Symbol.')],
            ['name' => 'ha_menu_icon', 'label' => $t('Symbol des Kontakt-Menüs'), 'type' => 'choice', 'default' => $d['ha_menu_icon'],
                'options' => ['chats' => $t('Sprechblasen'), 'phone' => $t('Telefon'), 'address-book' => $t('Adressbuch'), 'headset' => $t('Headset')],
                'help' => $t('Nur beim Stil „Kontakt-Menü“.')],
            ['name' => 'ha_cta', 'label' => $t('Handlungsaufruf – Inhalt'), 'type' => 'choice', 'default' => $d['ha_cta'],
                'options' => [
                    'custom' => $t('Eigene'),
                    'contact' => $t('Kontakt'),
                    'appointment' => $t('Termin'),
                    'call' => $t('Anrufen'),
                    'email' => $t('E-Mail'),
                    'newsletter' => $t('Newsletter'),
                ],
                'help' => $t('„Eigene“: Beschriftung und Link aus Website → Darstellung. Vorgaben suchen ihr Ziel selbst: Seite „kontakt“, „termin(e)“ bzw. „newsletter“, sonst den gleichnamigen Abschnitt der Startseite, bei Kontakt/Termin zuletzt E-Mail oder Telefon.')],
            ['name' => 'ha_cta2', 'label' => $t('Zweite Aktion'), 'type' => 'choice', 'default' => $d['ha_cta2'],
                'options' => [
                    'none' => $t('Keine'),
                    'call' => $t('Anrufen'),
                    'email' => $t('E-Mail'),
                    'contact' => $t('Kontakt'),
                    'newsletter' => $t('Newsletter'),
                    'custom' => $t('Eigene'),
                ],
                'help' => $t('Beim Stil „Geteilt“ die kleine Schaltfläche neben der Aktion (Standard: Anrufen, sonst E-Mail).')],
            ['name' => 'ha_search', 'label' => $t('Suche im Kopfbereich'), 'type' => 'choice', 'default' => $d['ha_search'],
                'options' => [
                    'popover' => $t('Lupe'),
                    'inline' => $t('Suchfeld'),
                    'expand' => $t('Aufziehen'),
                    'command' => $t('Befehlsfeld ⌘K'),
                    'bar' => $t('Suchleiste'),
                    'none' => $t('Keine'),
                ],
                'help' => $t('Lupe mit Feld darunter, Suchfeld in der Leiste mit Vorschlägen, Lupe, die sich zum Feld aufzieht, Befehlsfeld „Suchen … ⌘K“ oder Suchleiste über die ganze Breite unter dem Kopf. Nur bei eingeschalteter Website-Suche; „/“ oder ⌘K bzw. Strg+K springt hinein, Escape schließt. Mobil: Lupe bzw. Feld im Menü.')],
            ['name' => 'ha_layout', 'label' => $t('Anordnung'), 'type' => 'choice', 'default' => $d['ha_layout'],
                'options' => [
                    'right' => $t('Aktionen rechts'),
                    'center' => $t('Suche mittig'),
                    'below' => $t('Suche darunter'),
                ],
                'help' => $t('„Suche mittig“ setzt das Suchfeld zwischen Marke und Menü, „Suche darunter“ in eine eigene Zeile unter der Navigation.')],
            ['name' => 'ha_contact', 'label' => $t('Kontakt-Chip'), 'type' => 'choice', 'default' => $d['ha_contact'],
                'options' => ['none' => $t('Aus'), 'phone' => $t('Telefon'), 'email' => $t('E-Mail'), 'both' => $t('Beide')],
                'help' => $t('Kompakt aus Website → Stammdaten; mobil nur als Symbol.')],
            ['name' => 'ha_status', 'label' => $t('Öffnungsstatus zeigen („jetzt geöffnet“)'), 'type' => 'bool', 'default' => (bool) $d['ha_status'],
                'help' => $t('Am Kontakt-Chip und am Statuspunkt – berechnet aus den Öffnungszeiten. Bezeichnung (z. B. „Hofladen“): Website → Darstellung.')],
            ['name' => 'ha_lang', 'label' => $t('Sprachumschalter'), 'type' => 'choice', 'default' => $d['ha_lang'],
                'options' => ['code' => $t('Kürzel'), 'dropdown' => $t('Aufklappliste'), 'text' => $t('Namen')],
                'help' => $t('Kürzel („DE · EN“), Aufklappliste oder Sprachnamen als Text. Nur bei mehreren Sprachen. Bewusst ohne Flaggen – Sprachen sind keine Länder.')],
            ['name' => 'ha_social', 'label' => $t('Social-Media-Symbole im Kopfbereich'), 'type' => 'bool', 'default' => (bool) $d['ha_social'],
                'help' => $t('Profile aus Website → Social Media (höchstens vier); mobil im Fußbereich.')],
            ['name' => 'ha_account', 'label' => $t('Link „Anmelden“ (Mitgliederbereich)'), 'type' => 'bool', 'default' => (bool) $d['ha_account'],
                'help' => $t('Ziel und Beschriftung: Website → Darstellung.')],
        ];
        $exclude = (array) ($opt['exclude'] ?? []);
        return ['id' => 'kopfaktionen', 'label' => (string) ($opt['label'] ?? $t('Kopfbereich: Suche & Aktionen')),
            'tokens' => array_values(array_filter($tokens, fn($x) => !in_array($x['name'], $exclude, true)))];
    }

    /** Felder für Website → Darstellung (ersetzen die bisherigen zwei Felder des Buttons; Namen bleiben gleich) */
    public static function settingsFields(): array
    {
        $t = fn(string $s): string => $s;
        return [
            ['type' => 'heading', 'label' => $t('Kopfbereich: Aktionen'), 'help' => $t('Stil, Suche und Anordnung: Verwaltung → Design → „Kopfbereich: Suche & Aktionen“.')],
            ['name' => 'header_cta_label', 'label' => $t('Handlungsaufruf – Beschriftung'), 'type' => 'text', 'max' => 28, 'width' => 'half',
                'help' => $t('Für den Inhalt „Eigene Beschriftung und Link“. Kurz halten, z. B. „Termin anfragen“.')],
            ['name' => 'header_cta_link', 'label' => $t('Handlungsaufruf – Link'), 'type' => 'link', 'width' => 'half', 'translate' => true],
            ['name' => 'header_cta2_label', 'label' => $t('Zweite Aktion – Beschriftung (optional)'), 'type' => 'text', 'max' => 24, 'width' => 'half',
                'help' => $t('Für die zweite Aktion „Eigene“.')],
            ['name' => 'header_cta2_link', 'label' => $t('Zweite Aktion – Link'), 'type' => 'link', 'width' => 'half', 'translate' => true],
            ['name' => 'header_status_label', 'label' => $t('Bezeichnung für den Öffnungsstatus (optional)'), 'type' => 'text', 'max' => 24, 'width' => 'half',
                'placeholder' => $t('z. B. Hofladen, Praxis, Büro'), 'help' => $t('Ergibt „Hofladen geöffnet“ statt „Jetzt geöffnet“.')],
            ['name' => 'header_account_label', 'label' => $t('Link „Anmelden“ – Beschriftung (optional)'), 'type' => 'text', 'max' => 20, 'width' => 'half',
                'placeholder' => $t('Anmelden')],
            ['name' => 'header_account_link', 'label' => $t('Link „Anmelden“ – Ziel'), 'type' => 'link', 'width' => 'half', 'translate' => true,
                'help' => $t('Z. B. der Mitgliederbereich. Erscheint, wenn im Design eingeschaltet.')],
        ];
    }

    // ------------------------------------------------------------------ Werte

    /** Eingestellte Werte (Design) mit Voreinstellungen; Kits ohne Gruppe bekommen DEFAULTS */
    public static function config(): array
    {
        $out = [];
        $tokens = Design::tokens();
        foreach (self::DEFAULTS as $k => $v) {
            $out[$k] = isset($tokens[$k]) ? Design::get($k) : $v;
        }
        // Frühere Einstellung „Button im Kopfbereich“ aus → kein Handlungsaufruf (bis im neuen Feld etwas gespeichert ist)
        $raw = (array) app()->settings->get('design.' . app()->theme->name, []);
        if (!array_key_exists('ha_cta_style', $raw) && (($raw['nav_cta'] ?? null) === false || ($raw['header_cta'] ?? null) === false)) {
            $out['ha_cta_style'] = 'none';
        }
        return $out;
    }

    /** Alles, was das Markup braucht (einmal je Anfrage und Sprache berechnet) */
    public static function model(): array
    {
        $key = Lang::current() . '|' . md5(json_encode(Design::values()));
        if (self::$model !== null && self::$modelKey === $key) return self::$model;
        $c = self::config();
        $phone = phone_display(trim((string) setting('phone')));
        $tel = tel_href($phone);
        $email = trim((string) setting('email'));
        if (!filled($email) || !str_contains($email, '@')) $email = '';

        // Handlungsaufruf + zweite Aktion
        $cta = $c['ha_cta_style'] === 'none' ? null : self::action((string) $c['ha_cta'], 'header_cta', $tel, $phone, $email);
        $cta2 = self::action((string) $c['ha_cta2'], 'header_cta2', $tel, $phone, $email);
        if ($cta2 && $cta && $cta2['href'] === $cta['href']) $cta2 = null;
        // „Geteilt“: kleine Schaltfläche = zweite Aktion (Anruf/E-Mail), sonst Telefon, sonst E-Mail
        $mini = null;
        if ($cta && $c['ha_cta_style'] === 'split') {
            $mini = $cta2 && in_array($cta2['kind'], ['call', 'email'], true) ? $cta2
                : ($tel ? self::action('call', '', $tel, $phone, $email) : ($email !== '' ? self::action('email', '', $tel, $phone, $email) : null));
            if ($mini && $mini['href'] === $cta['href']) $mini = null;
            if ($mini && $cta2 && $mini['href'] === $cta2['href']) $cta2 = null;
        }

        // Öffnungsstatus
        $ranges = self::ranges();
        $status = null;
        if ($c['ha_status'] && $ranges) {
            $name = trim((string) setting('header_status_label'));
            $status = [
                'ranges' => $ranges, 'tz' => date_default_timezone_get(),
                'open' => $name !== '' ? lt('{name} geöffnet', ['name' => $name]) : lt('Jetzt geöffnet'),
                'closed' => $name !== '' ? lt('{name} geschlossen', ['name' => $name]) : lt('Zurzeit geschlossen'),
            ];
        }

        // Kontakt-Chip
        $contact = [];
        if (in_array($c['ha_contact'], ['phone', 'both'], true) && $tel) $contact[] = ['kind' => 'phone', 'href' => $tel, 'text' => $phone, 'icon' => 'phone', 'sr' => lt('Anrufen: {nummer}', ['nummer' => $phone])];
        if (in_array($c['ha_contact'], ['email', 'both'], true) && $email !== '') $contact[] = ['kind' => 'email', 'href' => 'mailto:' . $email, 'text' => $email, 'icon' => 'envelope-simple', 'sr' => lt('E-Mail an {adresse}', ['adresse' => $email])];

        // Suche
        $search = null;
        if ($c['ha_search'] !== 'none' && Search::enabled()) {
            $mode = (string) $c['ha_search'];
            $slot = $mode === 'bar' || $c['ha_layout'] === 'below' ? 'below' : ($c['ha_layout'] === 'center' ? 'center' : 'bar');
            // Darunter: immer ein Feld; mittig: Feld oder Befehlsfeld
            if ($slot === 'below' || ($slot === 'center' && $mode !== 'command')) $mode = 'inline';
            $search = ['mode' => $mode, 'slot' => $slot, 'band' => $c['ha_search'] === 'bar',
                'action' => Search::url(), 'suggest' => Search::suggestUrl(), 'js' => asset('js/search.js'),
                'l10n' => ['all' => lt('Alle Ergebnisse für'), 'count' => lt('{n} Vorschläge')]];
        }

        // Social, Anmelden
        $social = [];
        if ($c['ha_social']) {
            foreach ((array) setting('social', []) as $s) {
                $url = trim((string) ($s['url'] ?? ''));
                $label = trim((string) ($s['label'] ?? ''));
                if ($label === '' || !is_external($url)) continue;
                $social[] = ['label' => $label, 'href' => $url, 'icon' => self::socialIcon($label, $url)];
                if (count($social) >= 4) break;
            }
        }
        $account = null;
        if ($c['ha_account'] && ($al = trim((string) setting('header_account_link'))) !== '') {
            $href = link_href($al);
            if ($href !== '#') $account = ['label' => trim((string) setting('header_account_label')) ?: lt('Anmelden'), 'href' => $href, 'ext' => is_external($href)];
        }

        // Kontakt-Menü (Symbol + Aufklappliste): alle vorhandenen Kontaktwege aus den Website-Angaben
        $menu = $c['ha_cta_style'] === 'menu' ? self::contactMenu($cta, $tel, $phone, $email, (string) $c['ha_menu_icon']) : null;

        $model = ['cfg' => $c, 'cta' => $cta, 'cta2' => $cta2, 'mini' => $mini, 'contact' => $contact, 'status' => $status,
            'search' => $search, 'social' => $social, 'account' => $account, 'menu' => $menu,
            'classes' => (array) (app()->theme->def['header_actions']['classes'] ?? [])];
        self::$modelKey = $key;
        return self::$model = $model;
    }

    /**
     * Eine Aktion auflösen: custom (Website-Felder {$prefix}_label/_link), contact/appointment/newsletter (Seite suchen),
     * call (tel:), email (mailto:). null, wenn nichts Sinnvolles entsteht.
     * @return ?array{kind: string, label: string, href: string, link: string, ext: bool, icon: string, sr: string}
     */
    private static function action(string $kind, string $prefix, ?string $tel, string $phone, string $email): ?array
    {
        $icons = ['custom' => 'chat-circle-text', 'contact' => 'chat-circle-text', 'appointment' => 'calendar-check',
            'call' => 'phone', 'email' => 'envelope-simple', 'newsletter' => 'newspaper'];
        [$label, $link, $sr] = match ($kind) {
            'custom' => [$prefix !== '' ? trim((string) setting($prefix . '_label')) : '', $prefix !== '' ? trim((string) setting($prefix . '_link')) : '', ''],
            // Ohne passende Seite: gleichnamiger Abschnitt der Startseite, sonst E-Mail bzw. Telefon (nie ein Link ins Leere)
            'contact' => [lt('Kontakt'), self::findPage('contact') ?? self::anchor(['kontakt', 'contact']) ?? ($email !== '' ? 'mailto:' . $email : (string) $tel), ''],
            'appointment' => [lt('Termin anfragen'), self::findPage('appointment') ?? self::anchor(['termin', 'termine', 'appointment']) ?? self::findPage('contact')
                ?? self::anchor(['kontakt', 'contact']) ?? ($email !== '' ? 'mailto:' . $email : (string) $tel), ''],
            'newsletter' => [lt('Newsletter'), self::findPage('newsletter') ?? self::anchor(['newsletter']) ?? '', ''],
            'call' => [lt('Anrufen'), (string) $tel, lt('Anrufen: {nummer}', ['nummer' => $phone])],
            'email' => [lt('E-Mail schreiben'), $email !== '' ? 'mailto:' . $email : '', lt('E-Mail an {adresse}', ['adresse' => $email])],
            default => ['', '', ''],
        };
        if ($label === '' || $link === '') return null;
        // Sonderwerte der Link-Felder mancher Kits: „phone“ / „email“
        if ($link === 'phone') $link = (string) $tel;
        if ($link === 'email') $link = $email !== '' ? 'mailto:' . $email : '';
        if ($link === '') return null;
        $href = link_href($link);
        if ($href === '#') return null;
        return ['kind' => $kind, 'label' => $label, 'href' => $href, 'link' => $link, 'ext' => is_external($href),
            'icon' => $icons[$kind] ?? 'arrow-right', 'sr' => $sr !== '' ? $sr : $label];
    }

    /**
     * Einträge des Kontakt-Menüs: Anrufen (Nummer), E-Mail (Adresse), Kontaktformular/-seite, Anfahrt (nur mit Adresse und
     * Standort der Karte, Core\Maps), WhatsApp/Signal (nur wenn das Kit ein solches Feld hat und es befüllt ist) und die
     * Öffnungszeiten (mit Status über header-actions-status.js). null, wenn es gar keinen Kontaktweg gibt.
     */
    private static function contactMenu(?array $cta, ?string $tel, string $phone, string $email, string $icon): ?array
    {
        $items = [];
        if ($tel) $items[] = ['icon' => 'phone', 'label' => lt('Anrufen'), 'sub' => $phone, 'href' => $tel, 'ext' => false];
        if ($email !== '') $items[] = ['icon' => 'envelope-simple', 'label' => lt('E-Mail schreiben'), 'sub' => $email, 'href' => 'mailto:' . $email, 'ext' => false];
        $form = self::findPage('contact');
        if ($form) {
            $items[] = ['icon' => 'chat-circle-text', 'label' => lt('Kontaktformular'), 'sub' => '', 'href' => link_href($form), 'ext' => false];
        } elseif ($cta && !in_array($cta['kind'], ['call', 'email'], true)) {
            $items[] = ['icon' => 'chat-circle-text', 'label' => $cta['label'], 'sub' => '', 'href' => $cta['href'], 'ext' => $cta['ext']];
        }
        $loc = Maps::siteLocation();
        $addr = Maps::siteAddress();
        if ($loc && $addr !== '') {
            $custom = ($k = (string) project('map.route', '')) !== '' ? trim((string) setting($k)) : '';
            $route = is_external($custom) ? $custom : 'https://www.openstreetmap.org/directions?to=' . rawurlencode($loc[0] . ',' . $loc[1]) . '#map=16/' . $loc[0] . '/' . $loc[1];
            $items[] = ['icon' => 'map-pin', 'label' => lt('Anfahrt planen'), 'sub' => $addr, 'href' => $route, 'ext' => true];
        }
        // Messenger nur, wenn das Kit ein Feld dafür anbietet (keine eigene Anbindung)
        $fields = [];
        foreach ((array) (app()->theme->def['settings']['groups'] ?? []) as $g) foreach ((array) ($g['fields'] ?? []) as $f) if (!empty($f['name'])) $fields[$f['name']] = true;
        foreach (['whatsapp' => ['WhatsApp', 'whatsapp-logo', 'https://wa.me/'], 'signal' => ['Signal', 'chats', 'https://signal.me/#p/+']] as $k => [$label, $ico, $base]) {
            $num = preg_replace('~[^\d]~', '', (string) tel_href(trim((string) setting($k))));
            if (isset($fields[$k]) && $num !== '') $items[] = ['icon' => $ico, 'label' => $label, 'sub' => '', 'href' => $base . $num, 'ext' => true];
        }
        if (!$items) return null;
        return ['items' => $items, 'hours' => self::hoursList(), 'icon' => $icon, 'label' => lt('Kontakt')];
    }

    /** Öffnungszeiten kurz, gleiche Zeiten an Folgetagen zusammengefasst: [['days' => 'Mo–Fr', 'time' => '9:00–17:00'], …] */
    public static function hoursList(): array
    {
        $byDay = [];
        foreach (self::ranges() as [$dow, $from, $to]) $byDay[$dow][] = ltrim($from, '0') . '–' . ltrim($to, '0');
        $lang = Lang::current();
        $short = function (int $dow) use ($lang): string {
            if (class_exists(\IntlDateFormatter::class)) {
                $f = new \IntlDateFormatter($lang, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', null, 'EEE');
                $out = $f->format(strtotime('2024-01-0' . ($dow === 0 ? 7 : $dow) . ' 12:00 UTC'));
                if (is_string($out)) return rtrim($out, '.');
            }
            return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][$dow];
        };
        $groups = [];
        foreach ([1, 2, 3, 4, 5, 6, 0] as $dow) {
            if (!isset($byDay[$dow])) { $groups[] = null; continue; }
            $time = implode(', ', $byDay[$dow]);
            $last = $groups ? $groups[array_key_last($groups)] : null;
            if ($last && $last['time'] === $time) $groups[array_key_last($groups)]['to'] = $dow;
            else $groups[] = ['from' => $dow, 'to' => $dow, 'time' => $time];
        }
        $out = [];
        foreach (array_filter($groups) as $g) {
            $out[] = ['days' => $short($g['from']) . ($g['to'] !== $g['from'] ? '–' . $short($g['to']) : ''), 'time' => $g['time']];
        }
        return $out;
    }

    /**
     * Stil „Menüpunkt“: steht der Handlungsaufruf schon als Punkt im Hauptmenü (gleiches Ziel, ohne Unterseiten), wird er dort
     * entfernt – er erscheint abgesetzt am Ende. Für Kit-Templates: $menu = \Core\HeaderActions::menu(kit_menu()).
     */
    public static function menu(array $menu): array
    {
        $m = self::model();
        if ($m['cfg']['ha_cta_style'] !== 'navitem' || !$m['cta']) return $menu;
        $norm = fn(string $h) => rtrim((string) preg_replace('~[?#].*$~', '', $h), '/');
        $target = $norm($m['cta']['href']);
        return array_values(array_filter($menu, fn($i) => !empty($i['children']) || $norm((string) ($i['href'] ?? '')) !== $target));
    }

    /** Erste veröffentlichte Seite mit einem der Slugs der Vorgabe (Sprache der Seite) → Link „page:ID“ */
    private static function findPage(string $kind): ?string
    {
        static $cache = [];
        $ck = $kind . '|' . Lang::current();
        if (array_key_exists($ck, $cache)) return $cache[$ck];
        foreach (self::SLUGS[$kind] ?? [] as $slug) {
            $p = Pages::byPath($slug);
            if ($p && ($p['status'] ?? '') === 'published') return $cache[$ck] = 'page:' . (int) $p['id'];
        }
        return $cache[$ck] = null;
    }

    /** Erster vorhandener Abschnitt (Anker) der Startseite, z. B. „#kontakt“ */
    private static function anchor(array $ids): ?string
    {
        $home = Pages::home();
        if (!$home) return null;
        $anchors = Pages::anchors($home);
        foreach ($ids as $id) if (isset($anchors[$id])) return '#' . $id;
        return null;
    }

    /** Öffnungszeiten (Website → Stammdaten „hours“) als [Wochentag 0–6, von, bis] – Pausen teilen den Tag */
    public static function ranges(): array
    {
        $out = [];
        foreach ((array) setting('hours', []) as $r) {
            $dow = (int) ($r['tag'] ?? -1);
            if ($dow < 0 || $dow > 6 || empty($r['von']) || empty($r['bis'])) continue;
            if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
                $out[] = [$dow, (string) $r['von'], (string) $r['pause_von']];
                $out[] = [$dow, (string) $r['pause_bis'], (string) $r['bis']];
            } else {
                $out[] = [$dow, (string) $r['von'], (string) $r['bis']];
            }
        }
        return $out;
    }

    /** Symbol für ein Social-Profil nach Bezeichnung bzw. Adresse */
    public static function socialIcon(string $label, string $url): string
    {
        $s = strtolower($label . ' ' . (string) parse_url($url, PHP_URL_HOST));
        foreach (['mastodon' => 'mastodon-logo', 'instagram' => 'instagram-logo', 'facebook' => 'facebook-logo', 'linkedin' => 'linkedin-logo',
            'youtube' => 'youtube-logo', 'youtu.be' => 'youtube-logo', 'tiktok' => 'tiktok-logo', 'threads' => 'threads-logo', 'github' => 'github-logo',
            'pinterest' => 'pinterest-logo', 'whatsapp' => 'whatsapp-logo', 'x.com' => 'x-logo', 'twitter' => 'x-logo', 'newsletter' => 'newspaper'] as $k => $icon) {
            if (str_contains($s, $k)) return $icon;
        }
        return 'share-network';
    }

    // ------------------------------------------------------------------ Ausgabe

    /**
     * Benötigte Teile für die aktuelle Einstellung: ['css' => [teil, …], 'js' => [teil, …]] – Teil '' = Grundlage
     * (public/assets/css/header-actions.css), sonst header-actions-{teil}.css bzw. .js.
     */
    public static function parts(): array
    {
        $m = self::model();
        $c = $m['cfg'];
        $css = [''];
        $js = [];
        $late = [];
        $kit = (array) (app()->theme->def['header_actions'] ?? []);
        $style = (string) $c['ha_cta_style'];
        if ($m['cta']) {
            if (in_array($style, ['solid', 'outline', 'icon', 'split', 'link', 'chip'], true)) $css[] = 'cta';
            $css[] = match ($style) { 'link' => 'link', 'chip' => 'chip', 'split' => 'split', 'navitem' => empty($kit['classes']['navitem']) ? 'navitem' : '', default => '' };
            if ($style === 'chip') $css[] = 'dot';
        }
        if ($m['menu']) {
            array_push($css, 'menu', 'dot');
            $late[] = 'menu-panel';                                        // Paneel erst nach dem Rendern (verborgen bis zur Bedienung)
            $js[] = 'menu';                                                // Escape, Klick daneben, aria-expanded
        }
        if ($m['cta2']) $css[] = 'cta2';
        if ($s = $m['search']) {
            $js[] = '';                                                    // Tastenkürzel „/“ und ⌘K, Escape
            if ($s['mode'] === 'inline' || $s['mode'] === 'expand') $css[] = 'field';
            if ($s['mode'] === 'expand') $css[] = 'expand';
            if ($s['mode'] === 'command') $css[] = 'command';
            if ($s['slot'] === 'below') $css[] = 'below';
        }
        if ($m['contact']) array_push($css, 'contact', 'dot');
        if ($m['status'] && ($m['contact'] || $m['menu'] || ($m['cta'] && $c['ha_cta_style'] === 'chip'))) $js[] = 'status';
        if ($m['social'] || $m['account'] || ($c['ha_lang'] === 'dropdown' && Lang::multi())) $css[] = 'extra';
        if ($c['ha_lang'] === 'dropdown' && Lang::multi()) $js[] = 'menu';
        $css = array_values(array_unique($css));
        // Kit mit 'header_actions' => ['base_css' => false]: schlichte Auswahl (Button bzw. Menüpunkt mit Kit-Klassen + Lupe)
        // braucht keine Kern-Datei – das Kit-CSS enthält dann selbst .ha{display:flex…} und die Ecken (z. B. basis, starter)
        $plain = array_values(array_filter($css, fn($x) => $x !== '' && $x !== 'cta'));
        if (!$plain && !$late && ($kit['base_css'] ?? true) === false
            && ($style === 'none' || !$m['cta'] || (in_array($style, (array) ($kit['plain'] ?? ['solid', 'outline', 'navitem']), true) && !empty($kit['classes'][$style])))) {
            $css = [];
        }
        return ['css' => $css, 'js' => array_values(array_unique($js)), 'late' => $late];
    }

    /** Braucht die Einstellung JavaScript? (Tastenkürzel der Suche, Öffnungsstatus, Aufklappliste der Sprachen) */
    public static function needsJs(): bool
    {
        return (bool) self::parts()['js'];
    }

    /**
     * <link>/<script> für <head> – VOR dem CSS des Kits einbinden. Nur die benötigten Teile, gebündelt zu je EINER Datei
     * public/assets/ha/{hash}.css|js (für alle Websites mit derselben Auswahl gleich; entsteht beim ersten Aufruf, wie das
     * Symbol-Sprite). Geht das Schreiben nicht, kommen die Teile einzeln. JavaScript nie im Bearbeitungsmodus.
     * $opt['media']: Medienabfrage für das CSS (z. B. '(min-width: 48em)', wenn das Kit die Aktionen mobil ins Menü legt –
     * dann blockieren sie das Rendern auf Telefonen nicht).
     */
    public static function head(array $opt = []): string
    {
        $p = self::parts();
        $media = trim((string) ($opt['media'] ?? ''));
        $h = '';
        $kitDef = (array) (app()->theme->def['header_actions'] ?? []);
        // Kits ohne header_actions_late() im Layout ('late' => true fehlt): späte Teile gleich hier mitladen
        $early = empty($kitDef['late']) ? array_merge($p['css'], $p['late']) : $p['css'];
        $hrefs = self::bundle('css', $early);
        if (empty($kitDef['late']) && $p['late'] && !empty($kitDef['kit_css_late'])) $hrefs[] = theme_asset((string) $kitDef['kit_css_late']);
        // Eigene Regeln des Kits zu den Optionen ('header_actions' => ['kit_css' => 'css/…']): nur zusammen mit dem Kern-Bündel,
        // direkt danach (gleiche Spezifität gewinnt) – so bleiben sie aus dem renderblockierenden site.css heraus
        $kitCss = (string) (app()->theme->def['header_actions']['kit_css'] ?? '');
        if ($hrefs && $kitCss !== '') $hrefs[] = theme_asset($kitCss);
        foreach ($hrefs as $href) {
            $h .= '<link rel="stylesheet" href="' . e($href) . '"' . ($media !== '' ? ' media="' . e($media) . '"' : '') . '>' . "\n";
        }
        if (!app()->editing && $p['js']) {
            foreach (self::bundle('js', $p['js']) as $src) $h .= '<script src="' . e($src) . '" defer></script>' . "\n";
        }
        return $h;
    }

    /**
     * Späte Teile am Ende von <body> (Kit-Layout: <?= header_actions_late() ?>, theme.php 'header_actions' => ['late' => true]):
     * Stile, die erst nach einer Bedienung sichtbar werden (Paneel des Kontakt-Menüs) – nicht renderblockierend.
     */
    public static function late(): string
    {
        $p = self::parts();
        $kitDef = (array) (app()->theme->def['header_actions'] ?? []);
        if (empty($kitDef['late']) || !$p['late']) return '';
        $hrefs = self::bundle('css', $p['late']);
        if (!empty($kitDef['kit_css_late'])) $hrefs[] = theme_asset((string) $kitDef['kit_css_late']);
        $h = '';
        foreach ($hrefs as $href) $h .= '<link rel="stylesheet" href="' . e($href) . '">' . "\n";
        return $h;
    }

    /** Teile → URL(s): gebündelte Datei in public/assets/ha/ oder (Fallback) die einzelnen Dateien */
    private static function bundle(string $ext, array $parts): array
    {
        if (!$parts) return [];
        $files = [];
        foreach ($parts as $part) {
            $rel = $ext . '/header-actions' . ($part !== '' ? '-' . $part : '') . '.' . $ext;
            if (is_file(ROOT . '/public/assets/' . $rel)) $files[$rel] = filemtime(ROOT . '/public/assets/' . $rel);
        }
        if (count($files) < 2) return array_map('asset', array_keys($files));
        $name = substr(hash('sha256', json_encode($files)), 0, 16) . '.' . $ext;
        $dir = ROOT . '/public/assets/ha';
        if (!is_file("$dir/$name")) {
            $out = '';
            foreach (array_keys($files) as $rel) $out .= rtrim((string) file_get_contents(ROOT . '/public/assets/' . $rel)) . "\n";
            $tmp = "$dir/.$name." . bin2hex(random_bytes(4));
            if ((!is_dir($dir) && !@mkdir($dir, 0775, true)) || @file_put_contents($tmp, $out) === false || !@rename($tmp, "$dir/$name")) {
                @unlink($tmp);
                return array_map('asset', array_keys($files));
            }
            @chmod("$dir/$name", 0644);
            // Ältere Bündel nach zwei Tagen entfernen (Seiten-Cache, Browser)
            foreach (glob("$dir/*.$ext") ?: [] as $old) if (basename($old) !== $name && filemtime($old) < time() - 2 * 86400) @unlink($old);
        }
        return [base_path() . '/assets/ha/' . $name];
    }

    /**
     * Markup einer Stelle im Kopf: bar (Leiste) | center (Suche mittig) | below (Suchleiste unter dem Kopf).
     * $opt: compact (bool, mobil verkleinern), class (zusätzliche Klasse), wrap (Klasse des inneren Containers bei „below“),
     *       search (false = Suche hier weglassen), cta (false = Handlungsaufruf weglassen), contact (false),
 *       lang (language_links() – Sprachumschalter zwischen Suche und Aktion), lang_class (Klasse dafür)
     */
    public static function render(string $slot = 'bar', array $opt = []): string
    {
        $ha = self::model();
        // Kern-Fragment: Projekt/Kit dürfen es ersetzen (Core\Fragments – Suchreihenfolge bis app/Views/header-actions.php)
        return app()->theme->partial('header-actions', ['ha' => $ha, 'slot' => $slot, 'opt' => $opt]);
    }

    /**
     * Sprachumschalter im Stil aus Design („Kürzel“, „Aufklappliste“, „Sprachnamen“) – ohne Flaggen.
     * $langs aus language_links(); $class wird an die Liste gehängt (Kit-Stil „langswitch“ bleibt erhalten).
     */
    public static function lang(array $langs, string $class = ''): string
    {
        if (count($langs) < 2) return '';
        $style = (string) self::config()['ha_lang'];
        $cur = null;
        foreach ($langs as $l) if (!empty($l['active'])) $cur = $l;
        $cur ??= $langs[0];
        $items = '';
        foreach ($langs as $l) {
            $text = $style === 'code'
                ? '<span aria-hidden="true">' . e(strtoupper((string) $l['code'])) . '</span><span class="sf-sr">' . e((string) $l['label']) . '</span>'
                : e((string) $l['label']);
            $items .= '<li><a href="' . e((string) $l['url']) . '" hreflang="' . e((string) $l['code']) . '" lang="' . e((string) $l['code']) . '"'
                . (!empty($l['active']) ? ' aria-current="true"' : '') . '>' . $text . '</a></li>';
        }
        if ($style !== 'dropdown') {
            return '<ul class="langswitch ha-lang ha-lang--' . e($style) . ($class !== '' ? ' ' . e($class) : '') . '" role="list" aria-label="' . e(lt('Sprache')) . '">' . $items . '</ul>';
        }
        return '<details class="ha-lang ha-lang--dropdown' . ($class !== '' ? ' ' . e($class) : '') . '" data-ha-menu>'
            . '<summary class="ha-lang__btn">' . icon('globe', ['class' => 'ha-ico']) . '<span aria-hidden="true">' . e(strtoupper((string) $cur['code'])) . '</span>'
            . '<span class="sf-sr">' . e(lt('Sprache: {sprache}', ['sprache' => (string) $cur['label']])) . '</span>' . icon('caret-down', ['class' => 'ha-ico ha-caret']) . '</summary>'
            . '<ul class="ha-lang__list" role="list">' . $items . '</ul></details>';
    }

    /**
     * Geschätzte Breite der Aktionen in der Leiste (rem) – für Kits, die per Container-Query entscheiden, ob das Menü passt.
     */
    public static function widthRem(): float
    {
        $m = self::model();
        $c = $m['cfg'];
        $em = fn(string $s, float $size = .9375) => mb_strlen($s) * .57 * $size;
        $w = 0.0;
        if ($s = $m['search']) {
            // Suche in der Leiste bzw. mittig (braucht ebenfalls Platz in der Zeile); darunter: eigene Zeile
            $w += $s['slot'] === 'below' ? 0 : match ($s['mode']) { 'inline' => $s['slot'] === 'center' ? 18 : 15.5, 'command' => 12, default => 3.25 };
        }
        if ($cta = $m['cta']) {
            $w += match ($c['ha_cta_style']) {
                'link' => $em($cta['label']) + 2.2, 'icon' => $em($cta['label']) + 4, 'split' => $em($cta['label']) + 6.2,
                'chip' => $em($cta['label']) + 4.2, 'navitem' => $em($cta['label']) + 3, 'menu' => 0, default => $em($cta['label']) + 2.8,
            } + .6;
        }
        if ($m['menu']) $w += 3.4;
        if ($m['cta2']) $w += 3.25;
        foreach ($m['contact'] as $i => $x) $w += ($i === 0 && $m['status'] ? 9 : 0) + $em($x['text'], .875) + 3.2;
        $w += count($m['social']) * 2.75;
        if ($m['account']) $w += $em($m['account']['label']) + 3;
        return round($w, 1);
    }
}
