<?php /** Bild mit Unterschrift: Textbreite, breit, randlos oder mit Unterschrift in der Randspalte. @var \Core\Block $b  @var array $d */
$v = in_array($b->variant(), ['wide', 'text', 'bleed', 'margin'], true) ? $b->variant() : 'wide';
$image = (int) ($d['image'] ?? 0) ?: null;
$sizes = ['bleed' => '100vw', 'wide' => '(min-width: 1360px) 1280px, 100vw', 'text' => '(min-width: 860px) 760px, 100vw', 'margin' => '(min-width: 1360px) 900px, (min-width: 960px) 66vw, 100vw'][$v];
$media = editorial_image($image, $sizes, (string) ($d['ratio'] ?? ''), '', ['eager' => $b->prev === null]);
$credit = trim((string) ($d['credit'] ?? '')) ?: editorial_credit($image);
$cap = editorial_caption(trim((string) ($d['caption'] ?? '')), $credit, is_editing() ? $b->edit('caption') : '');
if ($media === '') return;
?>
<figure class="fig fig--<?= e($v) ?><?= $v === 'bleed' ? '' : ' wrap' ?>"><?= $media ?><?= $cap ?></figure>
