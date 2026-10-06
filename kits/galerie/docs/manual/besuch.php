<?php /** Handbuch „galerie“ · Kapitel „Besuch: Öffnungszeiten, Ausnahmen, Orte“ · @var string $settingsTitle */ ?>
  <p class="lead">Öffnungszeiten stehen bei Galerien ganz vorn. Sie pflegen sie einmal – Startseite, Block „Besuch“ und Fußbereich zeigen sie automatisch.</p>
  <ul>
    <li><b>Reguläre Öffnungszeiten:</b> <?= e($settingsTitle) ?> → Stammdaten → Öffnungszeiten (ein Eintrag je Wochentag; gleiche Zeiten werden zusammengefasst, z. B. „Mittwoch–Freitag“). Darunter ein Hinweis wie „und nach Vereinbarung“.</li>
    <li><b>Abweichende Öffnungszeiten</b> (Feiertage, Sommerpause, Aufbau): <?= e($settingsTitle) ?> → Galerie. Von–bis, Anlass, geschlossen oder abweichende Zeiten. Sie erscheinen 60 Tage vorher im Block „Besuch“, am Tag selbst steht „Heute geschlossen (Anlass)“ – und danach verschwinden sie von selbst.</li>
    <li><b>Weitere Orte</b> (Showroom, Lager, Projektraum): ebenfalls unter Galerie – mit Adresse, Zeiten und Hinweis. Der Kartenlink führt zu OpenStreetMap (erst beim Klick).</li>
    <li><b>Eintritt</b> und <b>Termine nach Vereinbarung</b>: kurze Sätze unter Galerie, erscheinen im Block „Besuch“.</li>
  </ul>
