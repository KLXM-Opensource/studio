<?php
/**
 * Layout (Spalten): Raster mit Blöcken je Spalte – Ausgabe Core\Layout::render (Kern-Block, theme.php → 'layout' => false schaltet ab).
 * Ein Kit kann eine eigene Vorlage kits/{name}/blocks/layout.php mitbringen und dort ebenfalls Core\Layout::render aufrufen.
 * @var \Core\Block $b  @var array $d
 */
echo \Core\Layout::render(app()->theme, $b);
