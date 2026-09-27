<?php
/**
 * Redaktions-Werkzeugleiste (nur eingeloggt) – rendert der Kern für alle Modi (Seite, Eintrag, Vorlage):
 * Core\Toolbar, app/Views/toolbar.php. Dieses Partial ist nur der Einhängepunkt; ohne Partial rendert der Kern sie auch.
 * @var array $page  @var bool $editing  @var bool $dirty  @var bool $live
 */
echo cms_toolbar(get_defined_vars());
