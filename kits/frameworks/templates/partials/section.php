<?php
/**
 * Hülle je Block (Core\Theme::renderBlock). Kern-Klassen ($b->sectionClass(): sec, sec--{typ}, bg-{hintergrund}, v-{variante},
 * pt-/pb-small|none, sec--divider …) bleiben immer dran – das Framework ergänzt nur seine eigenen (views/{fw}/section.php).
 * @var \Core\Block $b  @var string $inner
 */
include frameworks_view('section');
