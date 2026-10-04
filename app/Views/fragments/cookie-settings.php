<?php
/**
 * Kern-Fragment „cookie-settings“ (überschreibbar: kits/{kit}/fragments/cookie-settings.php): Link „Datenschutz-Einstellungen“
 * zum erneuten Öffnen der Einwilligungs-Auswahl. Leer, solange keine Einwilligungs-Verwaltung (Erweiterung consent_kit)
 * einen einwilligungspflichtigen Dienst meldet – dann gibt es nichts einzustellen.
 * @var ?string $label  @var ?string $class
 */
if (!function_exists('consent_settings_link')) return;
echo consent_settings_link((string) ($label ?? ''), (string) ($class ?? ''));
