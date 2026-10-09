<?php
/**
 * Karten (UIkit) – uk-card in einem uk-grid (gleich hohe Karten mit uk-grid-match). Ganze Karte klickbar über
 * uk-position-cover am Link; im Bearbeiten-Modus aus (.is-editing in css/uikit.css), damit Texte bearbeitbar bleiben.
 * @var \Core\Block $b  @var array $d
 */
$h = filled($d['title']) ? 'h3' : 'h2';
?>
<div class="uk-container">
  <?php include __DIR__ . '/_head.php'; ?>
  <ul class="uk-child-width-1-2@s uk-child-width-1-3@m uk-grid-match fw-list" uk-grid>
    <?php foreach ($d['items'] as $i => $it): $href = trim((string) ($it['link'] ?? '')) !== '' ? frameworks_link($it['link']) : ''; ?>
    <li>
      <div class="uk-card uk-card-default uk-card-hover uk-position-relative fw-card"><?= $b->targetEdit((string) ($it['link'] ?? ''), (string) ($it['title'] ?? '')) ?>
        <?php if (!empty($it['image'])): ?><div class="uk-card-media-top"><?= frameworks_image($it['image'], '(min-width: 960px) 360px, (min-width: 640px) 50vw, 100vw', '3:2', 'fw-media--flat', ['alt' => '']) ?></div><?php endif; ?>
        <div class="uk-card-body">
          <?php if (empty($it['image']) && filled($it['icon'] ?? '')): ?><span class="fw-icon"><?= icon($it['icon']) ?></span><?php endif; ?>
          <<?= $h ?> class="uk-card-title"><?php if ($href !== ''): ?><a class="uk-link-heading fw-cover" href="<?= e($href) ?>"<?= ext_attrs($href) ?><?= $b->edit("items.$i.title") ?>><?= emphasis((string) $it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= emphasis((string) $it['title']) ?></span><?php endif; ?></<?= $h ?>>
          <?php if (filled($it['text'] ?? '') || is_editing()): ?><p<?= $b->edit("items.$i.text") ?>><?= nl2br(emphasis((string) ($it['text'] ?? '')), false) ?></p><?php endif; ?>
        </div>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint"><?= e(lt('Noch keine Karten – in der Seitenleiste hinzufügen.')) ?></p><?php endif; ?>
</div>
