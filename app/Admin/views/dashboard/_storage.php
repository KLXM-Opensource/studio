<?php
/** Übersicht · „Speicher“ (nur Administration) – nachgeladen, Core\StorageUsage (stündlich neu gezählt). @var array $data */
echo \Core\Theme::capture(ROOT . '/app/Admin/views/storage/_usage.php', ['u' => (array) $data, 'compact' => true]);
?>
