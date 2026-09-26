<?php /** Handbuch aus dem Kit (themes/{name}/docs/manual.php). @var string $themeManual  @var array $blocks */
$helpTab = 'manual';
include ROOT . '/app/Admin/views/support/_helptabs.php';   // Reiter: Handbuch · Wissensdatenbank · Fragen & Antworten · Technik
include $themeManual;
