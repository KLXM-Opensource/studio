<?php
/**
 * Ergebnisseite der Website-Suche (im Grundlayout des Themes). Themes können templates/search.php mitbringen.
 * Klassen: .srch (Seite), .srch-hit (Treffer), .srch-badge, .srch-pager – Stil: public/assets/css/search.css
 * (bzw. public/assets/kits/{theme}/css/search.css).
 * @var array $result  @var string $q  @var bool $limited  @var string $form  @var callable $url  fn(array $extra): string
 */
$r = $result;
$external = $r['mode'] === 'hybrid' && \Core\AI\Ai::capability('embed')['external'];
?>
<section class="sec srch" aria-labelledby="srch-title">
  <div class="wrap srch__wrap">
    <header class="srch__head">
      <p class="eyebrow srch__eyebrow"><?= e(lt('Suche')) ?></p>
      <h1 id="srch-title" class="srch__title"><?= $q !== '' ? e(lt('Ergebnisse für „{q}“', ['q' => $q])) : e(lt('Was suchen Sie?')) ?></h1>
      <?= $form ?>
      <?php if ($external): ?><p class="srch__note"><?= e(lt('Für bessere Ergebnisse wird Ihre Suchanfrage (ohne IP-Adresse) an unseren KI-Dienstleister übermittelt. Details in der Datenschutzerklärung.')) ?></p><?php endif; ?>
    </header>

    <?php if ($limited): ?>
      <p class="srch__status" role="status"><?= e(lt('Zu viele Suchanfragen in kurzer Zeit. Bitte versuchen Sie es in einer Minute erneut.')) ?></p>
    <?php elseif ($q === ''): ?>
      <p class="srch__status"><?= e(lt('Geben Sie einen oder mehrere Begriffe ein – Tippfehler und Umlaute sind kein Problem.')) ?></p>
    <?php elseif (!$r['total']): ?>
      <div class="srch__empty" role="status">
        <p class="srch__status"><strong><?= e(lt('Keine Treffer für „{q}“.', ['q' => $q])) ?></strong></p>
        <ul>
          <li><?= e(lt('Prüfen Sie die Schreibweise oder verwenden Sie weniger bzw. allgemeinere Begriffe.')) ?></li>
          <li><?= e(lt('Versuchen Sie ein anderes Wort mit derselben Bedeutung.')) ?></li>
        </ul>
        <p><a class="srch__home" href="<?= e(url(\Core\Lang::prefix(\Core\Lang::current()) . '/')) ?>"><?= e(lt('Zur Startseite')) ?></a></p>
      </div>
    <?php else: ?>
      <p class="srch__status" role="status"><?= e($r['total'] === 1 ? lt('1 Treffer') : lt('{n} Treffer', ['n' => $r['total']])) ?><?php if ($r['pages'] > 1): ?>
        <span class="srch__pageinfo"> · <?= e(lt('Seite {page} von {pages}', ['page' => $r['page'], 'pages' => $r['pages']])) ?></span><?php endif; ?></p>

      <?= \Core\AI\VisitorChat::searchAnswerBox($q, $r) // KI-Antwort (Grundeinstellungen → Suche) ?>

      <?php if (count($r['types']) > 1 || $r['type'] !== ''): ?>
      <nav class="srch__types" aria-label="<?= e(lt('Nach Art filtern')) ?>">
        <ul>
          <li><a href="<?= e($url()) ?>"<?= $r['type'] === '' ? ' aria-current="true"' : '' ?>><?= e(lt('Alle')) ?></a></li>
          <?php foreach ($r['types'] as $key => $t): ?>
          <li><a href="<?= e($url(['typ' => $key])) ?>"<?= $r['type'] === (string) $key ? ' aria-current="true"' : '' ?>><?= e($t['label']) ?> <span class="srch__count"><?= (int) $t['n'] ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <?php endif; ?>

      <?php if ($r['facets']): $base = ['typ' => $r['type']]; ?>
      <div class="srch__facets">
        <?php foreach ($r['facets'] as $fn => $fc): $cur = $r['facet'][$fn] ?? null; ?>
        <nav class="srch__types srch__facet" aria-label="<?= e(lt('Filter: {name}', ['name' => $fc['label']])) ?>">
          <span class="srch__facetlabel"><?= e($fc['label']) ?>:</span>
          <ul>
            <li><a href="<?= e($url($base + ['f' => array_diff_key($r['facet'], [$fn => 1])])) ?>"<?= $cur === null ? ' aria-current="true"' : '' ?>><?= e(lt('Alle')) ?></a></li>
            <?php foreach (array_slice($fc['values'], 0, 12, true) as $val => $n): ?>
            <li><a href="<?= e($url($base + ['f' => [$fn => (string) $val] + $r['facet']])) ?>"<?= $cur === (string) $val ? ' aria-current="true"' : '' ?>><?= e((string) $val) ?> <span class="srch__count"><?= (int) $n ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        </nav>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <ol class="srch__list" start="<?= (int) (($r['page'] - 1) * \Core\Search\Search::PER_PAGE + 1) ?>">
        <?php foreach ($r['items'] as $it): $ext = str_starts_with($it['url'], 'http'); ?>
        <li class="srch-hit srch-hit--<?= e($it['type']) ?><?= ($img = $it['image'] ? \Core\Media::find((int) $it['image']) : null) && str_starts_with((string) $img['mime'], 'image/') ? ' has-img' : '' ?>">
          <?php if ($img && str_starts_with((string) $img['mime'], 'image/')): ?><img class="srch-hit__img" src="<?= e(\Core\Media::url($img, 480)) ?>" alt="" width="96" height="96" loading="lazy" decoding="async"><?php endif; ?>
          <p class="srch-hit__meta">
            <span class="srch-badge"><?= e($it['badge']) ?></span>
            <?php if ($it['date_label'] !== '' && $it['type'] === 'entry'): ?><time datetime="<?= e(str_replace(' ', 'T', $it['date'])) ?>"><?= e($it['date_label']) ?></time><?php endif; ?>
            <?php if ($it['origin'] !== ''): ?><span class="srch-hit__origin"><?= e(lt('von {name}', ['name' => $it['origin']])) ?></span><?php endif; ?>
          </p>
          <h2 class="srch-hit__title"><a href="<?= e($it['url']) ?>"<?= $ext ? ' rel="noopener"' : '' ?>><?= $it['title'] ?></a></h2>
          <?php if ($it['snippet'] !== ''): ?><p class="srch-hit__text"><?= $it['snippet'] ?></p><?php endif; ?>
          <p class="srch-hit__url" aria-hidden="true"><?= e(preg_replace('~^https?://(www\.)?~', '', $ext ? $it['url'] : (preg_replace('~^https?://~', '', site_url()) . $it['url']))) ?></p>
        </li>
        <?php endforeach; ?>
      </ol>

      <?php if ($r['pages'] > 1): $cur = $r['page']; $extra = ($r['type'] !== '' ? ['typ' => $r['type']] : []) + ($r['facet'] ? ['f' => $r['facet']] : []); ?>
      <nav class="srch-pager" aria-label="<?= e(lt('Seiten der Suchergebnisse')) ?>">
        <ul>
          <?php if ($cur > 1): ?><li><a class="srch-pager__step" href="<?= e($url($extra + ['seite' => $cur - 1 > 1 ? $cur - 1 : null])) ?>" rel="prev"><span aria-hidden="true">←</span> <?= e(lt('Zurück')) ?></a></li><?php endif; ?>
          <?php for ($i = 1; $i <= $r['pages']; $i++): if ($r['pages'] > 7 && $i !== 1 && $i !== $r['pages'] && abs($i - $cur) > 1) { if ($i === 2 || $i === $r['pages'] - 1) echo '<li class="srch-pager__gap" aria-hidden="true">…</li>'; continue; } ?>
          <li><a href="<?= e($url($extra + ['seite' => $i > 1 ? $i : null])) ?>"<?= $i === $cur ? ' aria-current="page"' : '' ?>><span class="sf-sr"><?= e(lt('Seite')) ?> </span><?= $i ?></a></li>
          <?php endfor; ?>
          <?php if ($cur < $r['pages']): ?><li><a class="srch-pager__step" href="<?= e($url($extra + ['seite' => $cur + 1])) ?>" rel="next"><?= e(lt('Weiter')) ?> <span aria-hidden="true">→</span></a></li><?php endif; ?>
        </ul>
      </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
