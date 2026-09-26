<?php
/**
 * Einstieg (H1): Text + Bild, zentriert, Seitenkopf – dazu drei Varianten mit Werkzeug (Stylesheet css/hero-x.css, nur dort):
 *   search  Such-Einstieg: Suchfeld mit Vorschlägen beim Tippen + beliebte Begriffe (Core\Blocks\Hero::search/chips)
 *   form    Text links, kompaktes Formular (Datentabelle, z. B. Rückruf) rechts in einer Karte
 *   map     Standort: Text + Karte mit Anschrift, Kontakt, „jetzt geöffnet“ und Öffnungszeiten aus „Website“
 * @var \Core\Block $b  @var array $d
 */
use Core\Blocks\Hero;

$v = $b->variant() ?: 'split';
$textHtml = function (bool $buttons = true) use ($b, $d): string {
    $h = '<div class="hero__text">';
    if ($d['eyebrow'] !== '') $h .= '<p class="eyebrow"' . $b->edit('eyebrow') . '>' . e($d['eyebrow']) . '</p>';
    $h .= '<h1 id="' . e($b->titleId()) . '" class="h1"' . $b->edit('title') . '>' . e($d['title']) . '</h1>';
    if ($d['text'] !== '' || is_editing()) $h .= '<p class="hero__lead"' . $b->edit('text') . '>' . nl2br(e($d['text']), false) . '</p>';
    return $h . ($buttons ? basis_buttons($b, 'hero__actions') : '') . '</div>';
};

if ($v === 'search'):
    $form = Hero::search($d);
    $chips = Hero::chips($d);
?>
<div class="wrap hero hx-search">
  <?= $textHtml(false) ?>
  <div class="hx-search__box">
    <?= $form !== '' ? $form : (is_editing() ? '<p class="empty-hint">' . e(__('Die Website-Suche ist ausgeschaltet (Grundeinstellungen → Funktionen) – es erscheinen nur die Links.')) . '</p>' : '') ?>
    <?php if ($chips): ?>
    <nav class="hx-search__chips" aria-label="<?= e(lt('Häufig gesucht')) ?>">
      <span class="hx-search__chips-label" aria-hidden="true"><?= e(lt('Häufig gesucht')) ?>:</span>
      <ul role="list"><?php foreach ($chips as $c): ?><li><a class="hx-chip" href="<?= e($c['href']) ?>"><?= e($c['label']) ?></a></li><?php endforeach; ?></ul>
    </nav>
    <?php endif; ?>
  </div>
  <?= basis_buttons($b, 'hero__actions hx-search__actions') ?>
</div>
<?php elseif ($v === 'form'):
    $points = Hero::lines($d['points'] ?? '');
?>
<div class="wrap hero hx-form">
  <div class="hx-form__text">
    <?= $textHtml(false) ?>
    <?php if ($points): ?><ul class="hx-checks" role="list"><?php foreach ($points as $p): ?><li><?= basis_icon('check', 'hx-checks__ico') ?><span><?= e($p) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    <?= basis_buttons($b, 'hero__actions') ?>
  </div>
  <div class="hx-form__card dff-wrap">
    <?php if (trim((string) ($d['form_title'] ?? '')) !== ''): ?><h2 class="hx-form__title"<?= $b->edit('form_title') ?>><?= e($d['form_title']) ?></h2><?php endif; ?>
    <?= Hero::form($b, $d) ?>
    <?php if (trim((string) ($d['form_note'] ?? '')) !== ''): ?><p class="hx-form__note"<?= $b->edit('form_note') ?>><?= e($d['form_note']) ?></p><?php endif; ?>
  </div>
</div>
<?php elseif ($v === 'map'):
    $phone = basis_phone(); $tel = basis_phone_href(); $email = basis_email();
    $addr = basis_address_lines();
    $hours = !empty($d['map_hours']) ? basis_hours() : [];
?>
<div class="wrap hero hx-map">
  <?= $textHtml() ?>
  <div class="hx-map__stage">
    <?= Hero::map($d, 'l', 'hx-map__map') ?>
    <div class="hx-map__card"<?= $b->central() ?>>
      <p class="hx-map__name"><?= e(basis_name()) ?></p>
      <?php if ($addr): ?><address class="hx-map__addr"><?= implode('<br>', array_map('e', $addr)) ?></address><?php endif; ?>
      <?php if ($hours): ?><p class="hx-map__open"><?= app()->theme->partial('openstate') ?></p><?php endif; ?>
      <ul class="hx-map__contact" role="list">
        <?php if (filled($phone) && $tel): ?><li><a href="<?= e($tel) ?>"><?= basis_icon('phone') ?><span><?= e($phone) ?></span></a></li><?php endif; ?>
        <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= basis_icon('mail') ?><span><?= e($email) ?></span></a></li><?php endif; ?>
      </ul>
      <?php if ($hours): ?><details class="hx-map__hours"><summary><?= e(lt('Öffnungszeiten')) ?></summary><?= app()->theme->partial('hours', ['hours' => $hours]) ?></details><?php endif; ?>
      <?php if (trim((string) ($d['map_note'] ?? '')) !== ''): ?><p class="hx-map__note"<?= $b->edit('map_note') ?>><?= e($d['map_note']) ?></p><?php endif; ?>
    </div>
  </div>
</div>
<?php else:
    $ratio = $v === 'centered' ? '16:9' : ((string) ($d['ratio'] ?? '') ?: '4:3');
    $media = $v === 'compact' ? '' : basis_image($d['image'] ?? null,
        $v === 'centered' ? '(min-width: 1280px) 1200px, 100vw' : '(min-width: 1080px) 560px, 100vw', $ratio, 'hero__media', ['eager' => true]);
?>
<div class="wrap hero<?= $media !== '' ? ' hero--media' : '' ?>">
  <div class="hero__text">
    <?php if ($d['eyebrow'] !== ''): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
    <h1 id="<?= e($b->titleId()) ?>" class="h1"<?= $b->edit('title') ?>><?= e($d['title']) ?></h1>
    <?php if ($d['text'] !== '' || is_editing()): ?><p class="hero__lead"<?= $b->edit('text') ?>><?= nl2br(e($d['text']), false) ?></p><?php endif; ?>
    <?= basis_buttons($b, 'hero__actions') ?>
  </div>
  <?= $media ?>
</div>
<?php endif;
