<?php
/** Karte mit dem Standort aus den Praxisdaten → Anfahrt (Core\Maps, über den eigenen Proxy – ohne Einwilligung). */
echo \Core\Maps::renderBlock(['location' => 'site', 'zoom' => 16, 'height' => 'm', 'route' => true]);
