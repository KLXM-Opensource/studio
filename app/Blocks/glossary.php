<?php
/**
 * Glossar (Kern-Block, Funktion „glossary“, Core\Glossary\Glossary) – vom Kit überschreibbar (kits/{name}/blocks/glossary.php),
 * Aussehen über css/glossary.css des Kerns und die Variablen --gl-* / --glx-* (optional css/glossary.css des Kits).
 *  - Ansicht „list“: Übersicht A–Z mit Buchstaben-Navigation und Suchfilter (JavaScript, ohne JavaScript: vollständige Liste),
 *    JSON-LD DefinedTermSet (inLanguage = Sprache der Seite; Begriffe dieser Sprache). Im Block selbst werden keine Begriffe markiert (data-glossary="off").
 *  - Ansicht „term“: auf der Detailseiten-Vorlage der aufgerufene Begriff (Kurz-Erklärung, ausführliche Erklärung, Quelle),
 *    JSON-LD DefinedTerm. Andere Begriffe in der Erklärung werden markiert, der Begriff selbst nicht.
 * @var \Core\Block $b  @var array $d
 */
use Core\Glossary\Glossary;
use Core\StructuredData;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$t = Glossary::table();
if (!$t) {
    if (is_editing()) echo '<div class="' . e($wrap) . '"><p class="glx-empty">' . e(__('Das Glossar ist noch nicht eingerichtet (Verwaltung → Einstellungen → Glossar).')) . '</p></div>';
    return;
}
$ctx = app()->entry;
$drafts = app()->auth->check() && can('data.edit', $t['handle']);

// ------------------------------------------------------------------ Ansicht „Begriff“ (Detailseite)
if (($d['view'] ?? 'list') === 'term') {
    $e = $ctx && ($ctx['table']['handle'] ?? '') === $t['handle'] ? $ctx['entry'] : (is_editing() ? (\Core\Data\Entries::query($t, ['status' => 'all', 'limit' => 1])[0] ?? null) : null);
    if (!$e) {
        if (is_editing()) echo '<div class="' . e($wrap) . '"><p class="glx-empty">' . e(__('Hier erscheint der aufgerufene Begriff – sobald es einen gibt.')) . '</p></div>';
        return;
    }
    $term = Glossary::term($t, $e);
    $back = Glossary::overviewUrl();
    $host = $term['link'] !== '' ? (string) parse_url($term['link'], PHP_URL_HOST) : '';
    $set = ['@type' => 'DefinedTermSet', 'name' => lt('Glossar'), 'inLanguage' => $term['lang']] + ($back ? ['url' => abs_url($back)] : []);
    StructuredData::add(array_filter(['@type' => 'DefinedTerm', 'name' => $term['term'], 'description' => $term['short'], 'inLanguage' => $term['lang'],
        'alternateName' => array_values(array_diff($term['variants'], [$term['term']])) ?: null,
        'url' => $term['url'] ? abs_url($term['url']) : null, 'sameAs' => preg_match('~(^|\.)(wikipedia|wikidata)\.org$~', $host) ? $term['link'] : null, 'inDefinedTermSet' => $set]));
    ?>
<div class="<?= e($wrap) ?> glx glx--term">
  <?php if ($back): ?><p class="glx-back"><a href="<?= e($back) ?>"><span aria-hidden="true">← </span><?= e(lt('Alle Begriffe im Glossar')) ?></a></p><?php endif; ?>
  <article class="glx-article" aria-labelledby="<?= e($b->titleId()) ?>">
    <header class="glx-head">
      <p class="eyebrow eyebrow--accent glx-eyebrow"><?= e(lt('Glossar')) ?><?= $term['category'] !== '' ? ' · ' . e($term['category']) : '' ?><?= $term['draft'] ? ' · <span class="glx-draft">' . e(lt('Entwurf')) . '</span>' : '' ?></p>
      <h1 class="h2 glx-title" id="<?= e($b->titleId()) ?>"><dfn<?= entry_edit_attr($t, $e, 'begriff', 'plain') ?>><?= e($term['term']) ?></dfn></h1>
      <?php if ($alt = array_values(array_diff($term['variants'], [$term['term']]))): ?>
      <p class="glx-alt" data-glossary="off"><span class="glx-alt__label"><?= e(lt('Auch')) ?>:</span> <?= e(implode(', ', $alt)) ?></p>
      <?php endif; ?>
    </header>
    <p class="glx-lead"<?= entry_edit_attr($t, $e, 'kurz', 'plain') ?>><?= e($term['short']) ?></p>
    <?php if ($term['long'] !== ''): ?><div class="glx-long prose"<?= entry_edit_attr($t, $e, 'erklaerung', 'rich') ?>><?= $term['long'] ?></div><?php endif; ?>
    <?php if ($host !== ''): ?>
    <p class="glx-source"><a href="<?= e($term['link']) ?>" rel="noopener external"><?= e(lt('Mehr erfahren')) ?>: <?= e(preg_replace('~^www\.~', '', $host)) ?> <span aria-hidden="true">↗</span></a></p>
    <?php endif; ?>
  </article>
</div>
<?php
    return;
}

// ------------------------------------------------------------------ Ansicht „Übersicht A–Z“
$terms = Glossary::terms($drafts);
$cat = trim((string) ($d['category'] ?? ''));
if ($cat !== '') $terms = array_values(array_filter($terms, fn($x) => mb_strtolower($x['category']) === mb_strtolower($cat)));
$letterOf = function (string $s): string {
    $c = mb_strtoupper(mb_substr(trim($s), 0, 1));
    $c = strtr($c, ['Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U', 'À' => 'A', 'Á' => 'A', 'É' => 'E', 'È' => 'E']);
    return preg_match('~^[A-Z]$~', $c) ? $c : '#';
};
$sortKey = fn(string $s) => strtr(mb_strtolower($s), ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);
usort($terms, fn($a, $b) => strcmp($sortKey($a['term']), $sortKey($b['term'])));
$groups = [];
foreach ($terms as $x) $groups[$letterOf($x['term'])][] = $x;
if (isset($groups['#'])) { $h = $groups['#']; unset($groups['#']); $groups['#'] = $h; }   // Ziffern/Zeichen ans Ende
$base = 'glx-' . $b->id;
$hTag = !empty($d['title']) ? 'h3' : 'h2';
$showCat = !empty($d['show_category']);
$set = ['@type' => 'DefinedTermSet', 'name' => (string) ($d['title'] ?: lt('Glossar')), 'inLanguage' => \Core\Lang::current()];
if (($u = Glossary::overviewUrl()) !== null) $set['url'] = abs_url($u);
$set['hasDefinedTerm'] = array_map(fn($x) => array_filter(['@type' => 'DefinedTerm', 'name' => $x['term'], 'description' => $x['short'],
    'url' => $x['url'] ? abs_url($x['url']) : null]), array_values(array_filter($terms, fn($x) => !$x['draft'])));
if ($set['hasDefinedTerm']) StructuredData::add($set);
?>
<div class="<?= e($wrap) ?> glx glx--list" data-glossary="off" data-glx-list>
  <?php if (!empty($d['eyebrow']) || !empty($d['title']) || !empty($d['intro'])): ?>
  <header class="glx-head">
    <?php if (!empty($d['eyebrow'])): ?><p class="eyebrow eyebrow--accent"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($d['title'])): ?><h2 id="<?= e($b->titleId()) ?>" class="h2 h2--m glx-heading"><span<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></span></h2><?php endif; ?>
    <?php if (!empty($d['intro'])): ?><p class="muted glx-intro"<?= $b->edit('intro') ?>><?= e($d['intro']) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>

  <?php if (!$terms): ?>
  <p class="glx-empty"><?= e(lt('Noch keine Begriffe im Glossar.')) ?></p>
  <?php else: ?>
  <div class="glx-tools">
    <?php if (!empty($d['search'])): ?>
    <div class="glx-search" data-glx-search hidden>
      <label class="glx-search__label" for="<?= e($base) ?>-q"><?= e(lt('Begriffe filtern')) ?></label>
      <input class="glx-search__input" id="<?= e($base) ?>-q" type="search" autocomplete="off" spellcheck="false" aria-controls="<?= e($base) ?>-groups" aria-describedby="<?= e($base) ?>-n" placeholder="<?= e(lt('z. B. Barrierefreiheit')) ?>">
      <p class="glx-count" id="<?= e($base) ?>-n" role="status" aria-live="polite" data-glx-count data-one="<?= e(lt('1 Begriff')) ?>" data-many="<?= e(lt('{n} Begriffe')) ?>" data-none="<?= e(lt('Kein Begriff passt zur Eingabe.')) ?>"></p>
    </div>
    <?php endif; ?>
    <?php if (!empty($d['letters']) && count($groups) > 1): ?>
    <nav class="glx-letters" aria-label="<?= e(lt('Glossar nach Anfangsbuchstaben')) ?>">
      <ul class="glx-letters__list">
        <?php foreach ([...range('A', 'Z'), ...(isset($groups['#']) ? ['#'] : [])] as $L): $id = $base . '-' . ($L === '#' ? 'num' : strtolower($L)); ?>
        <li><?php if (isset($groups[$L])): ?><a class="glx-letters__a" href="#<?= e($id) ?>" data-glx-letter="<?= e($id) ?>"><?= e($L) ?></a><?php else: ?><span class="glx-letters__off" aria-hidden="true"><?= e($L) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>
  </div>

  <div class="glx-groups" id="<?= e($base) ?>-groups">
    <?php foreach ($groups as $L => $items): $id = $base . '-' . ($L === '#' ? 'num' : strtolower($L)); ?>
    <section class="glx-group" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-h" data-glx-group>
      <<?= $hTag ?> class="glx-letter" id="<?= e($id) ?>-h"><?= e($L === '#' ? '0–9' : $L) ?></<?= $hTag ?>>
      <dl class="glx-list">
        <?php foreach ($items as $x): $alt = array_values(array_diff($x['variants'], [$x['term']]));
          $hay = mb_strtolower(implode(' ', [$x['term'], ...$alt, $x['short'], $x['category']])); ?>
        <div class="glx-item<?= $x['draft'] ? ' is-draft' : '' ?>" id="<?= e($x['key']) ?>" data-glx="<?= e($hay) ?>">
          <dt class="glx-item__term"><dfn><?= $x['url'] ? '<a href="' . e($x['url']) . '">' . e($x['term']) . '</a>' : e($x['term']) ?></dfn>
            <?php if ($alt): ?><span class="glx-item__alt">(<?= e(implode(', ', array_slice($alt, 0, 4))) ?>)</span><?php endif; ?>
            <?php if ($showCat && $x['category'] !== ''): ?><span class="glx-item__cat"><?= e($x['category']) ?></span><?php endif; ?>
            <?php if ($x['draft']): ?><span class="glx-draft"><?= e(lt('Entwurf')) ?></span><?php endif; ?></dt>
          <dd class="glx-item__short"><?= e($x['short']) ?></dd>
        </div>
        <?php endforeach; ?>
      </dl>
    </section>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
