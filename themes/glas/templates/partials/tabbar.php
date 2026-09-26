<?php
/**
 * Tab-Leiste des Glas-Docks (nur Telefone, < 48 em): Glasleiste am unteren Rand – daumenfreundlich, 44-px-Ziele.
 * Start + bis zu drei Hauptseiten mit Duoton-Symbol und Beschriftung, dazu „Mehr“ (öffnet das Glasblatt #mnav mit allen
 * Seiten, Suche, Direktkontakt und Sprache). Die aktuelle Seite trägt die Glaslinse und aria-current.
 * Beachtet env(safe-area-inset-bottom); die Seite erhält unten Platz (has-tabbar), der Besucher-Chat wird per
 * --cms-chat-lift darüber gehoben. Beim Scrollen nach unten blendet site.js die Leiste aus, nach oben wieder ein
 * (nur ohne „Bewegung reduzieren“). data-cms-hide-editing: im Bearbeiten-Modus mit Aktionsleiste ausgeblendet.
 * @var array $menu
 */
$tabs = glas_tabs($menu);
?>
<nav class="tabbar" aria-label="<?= e(lt('Schnellnavigation')) ?>" data-tabbar data-cms-hide-editing>
  <ul class="tabbar__list" role="list">
    <?php foreach ($tabs as $t): ?>
    <li><a class="tab<?= $t['active'] && !$t['current'] ? ' is-active' : '' ?>" href="<?= e($t['href']) ?>"<?= $t['current'] ? ' aria-current="page"' : '' ?>><?= icon($t['icon'], ['class' => 'tab__ico']) ?><span class="tab__lbl"><?= e($t['label']) ?></span></a></li>
    <?php endforeach; ?>
    <li><button type="button" class="tab tab--more" popovertarget="mnav" aria-controls="mnav" aria-haspopup="dialog" aria-expanded="false"><?= icon('dots-three', ['class' => 'tab__ico']) ?><span class="tab__lbl"><?= e(lt('Mehr')) ?></span></button></li>
  </ul>
</nav>
