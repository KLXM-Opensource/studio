<?php
/**
 * E-Mail „Anfrage“ mit vollständigem Inhalt (HTML) – Core\Data\Delivery::message(). Im Kit überschreibbar:
 * kits/{kit}/templates/mail/request.php. Bewusst schlicht und archivtauglich: nur Tabellen, keine Bilder, keine externen
 * Ressourcen, keine Links außer der Website, hell (druck- und PDF-freundlich). Alle Werte escaped.
 *
 * @var string $subject  @var string $form  @var string $ref  @var array<string,string> $meta  @var list<array> $rows
 *      (label, value, optional table: cols/rows)  @var string $footer  @var bool $test  @var ?string $machine (Dateiname des JSON/XML-Anhangs)
 */
$font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
$cell = 'padding:8px 12px;border-bottom:1px solid #E3E6EA;vertical-align:top;font:15px/1.45 ' . $font . ';color:#111418;';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
<title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#F4F5F7;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F4F5F7;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:640px;background:#FFFFFF;border:1px solid #D9DDE2;border-radius:6px;">
  <?php if ($test): ?>
  <tr><td style="padding:10px 20px;background:#FFF3C4;font:bold 14px/1.4 <?= $font ?>;color:#111418;border-radius:6px 6px 0 0;"><?= e(__('TESTNACHRICHT – erfundene Angaben')) ?></td></tr>
  <?php endif; ?>
  <tr><td style="padding:20px 20px 4px;font:600 20px/1.3 <?= $font ?>;color:#111418;"><?= e($form) ?></td></tr>
  <tr><td style="padding:0 20px 16px;font:15px/1.4 <?= $font ?>;color:#4A525C;"><?= e(__('Vorgangsnummer')) ?> <strong style="font-family:Menlo,Consolas,monospace;color:#111418;"><?= e($ref) ?></strong></td></tr>
  <tr><td style="padding:0 20px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #E3E6EA;">
      <?php foreach ($meta as $k => $v): ?>
      <tr><td style="<?= $cell ?>width:34%;color:#4A525C;font-size:13px;"><?= e($k) ?></td><td style="<?= $cell ?>font-size:13px;"><?= e($v) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </td></tr>
  <tr><td style="padding:20px 20px 6px;font:600 16px/1.3 <?= $font ?>;color:#111418;"><?= e(__('Angaben')) ?></td></tr>
  <tr><td style="padding:0 20px 8px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #E3E6EA;">
      <?php foreach ($rows as $r): ?>
      <tr>
        <th scope="row" align="left" style="<?= $cell ?>width:34%;font-weight:600;"><?= e($r['label']) ?></th>
        <td style="<?= $cell ?>">
          <?php if (!empty($r['table']['rows'])): ?>
          <table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;width:100%;">
            <tr><?php foreach ($r['table']['cols'] as $c): ?><th align="left" style="padding:4px 6px;border:1px solid #D9DDE2;background:#F4F5F7;font:600 13px/1.3 <?= $font ?>;"><?= e($c) ?></th><?php endforeach; ?></tr>
            <?php foreach ($r['table']['rows'] as $cells): ?>
            <tr><?php foreach ($cells as $c): ?><td style="padding:4px 6px;border:1px solid #D9DDE2;font:13px/1.35 <?= $font ?>;"><?= e($c) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
          </table>
          <?php else: ?><?= nl2br(e((string) $r['value']), false) ?><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </td></tr>
  <?php if ($machine): ?>
  <tr><td style="padding:4px 20px 0;font:13px/1.4 <?= $font ?>;color:#4A525C;"><?= e(__('Maschinenlesbar im Anhang: {file}', ['file' => $machine])) ?></td></tr>
  <?php endif; ?>
  <tr><td style="padding:16px 20px 20px;font:13px/1.45 <?= $font ?>;color:#4A525C;"><?= e($footer) ?></td></tr>
</table>
</td></tr>
</table>
</body>
</html>
