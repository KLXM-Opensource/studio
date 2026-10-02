<?php /** Entwicklerhandbuch · Werte formatieren (Core\Format, fmt()) – Anker #format */ ?>
  <p class="lead">Eine Stelle für alles, was Werte für Menschen lesbar macht: Datum, Uhrzeit, relative Angaben, Zahlen, Beträge, Dateigrößen, Dauer, Telefonnummern, Hosts und Auszüge – in Kits, Blöcken, Erweiterungen und im Core. Vorbild ist <code>rex_formatter</code>, aber klein: ein Objekt je Sprache, kurze Methoden, Ergebnis immer reiner Text.</p>
  <pre><code>&lt;time datetime="&lt;?= e($e['beginn']) ?&gt;"&gt;&lt;?= e(fmt()-&gt;date($e['beginn'], 'long')) ?&gt;&lt;/time&gt;
&lt;?= e(fmt()-&gt;currency($e['preis'])) ?&gt;            // 19,90 € (de) · €19.90 (en)
&lt;?= e(fmt()-&gt;excerpt($e['text'], 160)) ?&gt;          // Rich-Text → Text, an einer Wortgrenze gekürzt
&lt;?= e(fmt('en')-&gt;relative($m['created_at'])) ?&gt;    // ausdrücklich englisch</code></pre>
  <table class="doc-table">
    <tr><th>Methode</th><th>Beispiel (de · en)</th><th>Hinweise</th></tr>
    <tr><td><code>date($v, $stil = 'short')</code></td><td>24.09.2026 · 24/09/2026</td><td>Stile <code>short</code>, <code>long</code> (24. September 2026), <code>weekday</code> (Donnerstag), <code>day_month</code> (24. September); Intl, sonst eigener Rückfall</td></tr>
    <tr><td><code>time($v)</code>, <code>datetime($v, $stil)</code></td><td>14:05 · 24.09.2026, 14:05</td><td>24-Stunden-Uhr in beiden Sprachen</td></tr>
    <tr><td><code>relative($v, $now = null, $einheit = 'minute')</code></td><td>gerade eben · vor 5 Min. · heute, 14:05 · gestern, 09:12 · vor 3 Tagen · Datum</td><td><code>'day'</code>: heute, 14:05 · gestern · vor 3 Tagen (Kalendertage). Wörter auch englisch (wie <code>lang/en.php</code>)</td></tr>
    <tr><td><code>number($v, $stellen = 0)</code></td><td>1.234,5 · 1,234.5</td><td>leer bei nicht numerischen Werten</td></tr>
    <tr><td><code>decimal($v, $max = 2)</code></td><td>2,5 · 3 · 1.234,57</td><td>ohne Nullen am Ende</td></tr>
    <tr><td><code>currency($v, 'EUR', $stellen = 2)</code></td><td>19,90 € · €19.90</td><td><code>$stellen = null</code>: ganze Beträge ohne Nachkommastellen (1.500 €); Minus als „−“</td></tr>
    <tr><td><code>bytes($n)</code></td><td>12 KB · 1,5 MB · 2,1 GB</td><td>unter 1 MB ganze KB (mindestens 1 KB)</td></tr>
    <tr><td><code>duration($sek, $einheit = true)</code></td><td>3:05 min · 1:02:03 h</td><td>ohne Einheit 3:05 (Player)</td></tr>
    <tr><td><code>phone($nr)</code></td><td>0211 123 45 · +49 211 123 45</td><td>wie <code>phone_display()</code>: Standardsprache wie eingegeben, sonst international</td></tr>
    <tr><td><code>host($url)</code></td><td>example.org</td><td>ohne <code>www.</code>, leer bei Adressen ohne Host</td></tr>
    <tr><td><code>excerpt($text, $max = 160, $html = true)</code></td><td>Ein ganz normaler Satz mit …</td><td>HTML wird zu Text (Absätze, Entities); <code>$html = false</code> für Eingaben, die schon reiner Text sind (dann bleiben „&lt;b&gt;“ und „&amp;amp;“ stehen). Wortgrenze, „ …“ (wie die Textlänge der Datenliste)</td></tr>
  </table>
  <p><b>Sprache:</b> <code>fmt()</code> bzw. <code>Format::for()</code> nimmt die Sprache der aufgerufenen Seite (<code>Lang::current()</code>), <code>fmt('en')</code> eine bestimmte, <code>Format::admin()</code> die Sprache der Verwaltung (<code>I18n::locale()</code>). Andere Sprachen als Deutsch formatieren wie Englisch (Intl liefert Monats- und Tagesnamen in der jeweiligen Sprache).</p>
  <p><b>Im Core umgestellt</b> (gleiche Ausgabe, geprüft): <code>date_local()</code>, <code>Media::humanSize()</code> (ab 1 GB jetzt „GB“), <code>Links::ago()</code>, <code>Dashboard::ago()</code>, <code>MediaJobs::duration()</code>, <code>Clamp::excerpt()</code>, Block-Filter <code>number</code>. Die Helfer bleiben als Kurzformen. In englischer Verwaltung bzw. auf englischen Seiten folgen Trenner und Datum jetzt der Sprache (vorher teils deutsch).</p>
  <p><b>Nicht hier:</b> <code>paragraphs()</code> (Absätze aus Text), <code>e()</code> (Escapen), maschinenlesbare Werte (JSON-LD, iCal, vCard – dort bleiben Punkt und ISO-Formate). Selbsttest: <code>php bin/console format:selftest</code>.</p>
