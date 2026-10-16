<?php
// Konstanten und Autoloader für die statische Analyse (ohne App-Start)
define('ROOT', dirname(__DIR__, 2));
define('CMS_VERSION', '1.0.0');
require ROOT . '/vendor/autoload.php';
require __DIR__ . '/vendor/autoload.php';   // dompdf u. a. (optionale Pakete, nur für die Analyse)
