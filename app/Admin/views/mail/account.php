<?php
/**
 * E-Mails zu den Anmeldedaten (HTML) – Core\EmailChange::compose(): neue Adresse bestätigen, Hinweis an die bisherige Adresse
 * („Das war ich nicht“), Adresse vergeben, Adresse geändert, Passwort geändert. Layout wie die Einladung (invitation.php).
 * Im Kit überschreibbar: kits/{kit}/templates/mail/account.php (Text: account.txt.php). Alle Werte escaped.
 *
 * @var string $lang  @var string $subject  @var string $site  @var string $siteUrl  @var string $siteHost  @var ?string $logo (cid:logo)
 * @var string $brand  @var string $brandDark  @var string $onBrand  @var string $onBrandDark  @var string $title  @var string $greeting
 * @var list<string> $paras  @var array<string,string> $facts  @var ?array{label:string, url:string, note:string} $cta
 * @var list<string> $after  @var string $foot  @var bool $danger  Schaltfläche als Warnung (rot) statt in der Markenfarbe
 */
$font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
$pre = $paras[0] ?? $title;
$btn = $danger ? '#B42318' : $brand;
$btnDark = $danger ? '#F97066' : $brandDark;
$onBtn = $danger ? '#FFFFFF' : $onBrand;
$onBtnDark = $danger ? '#111111' : $onBrandDark;
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title><?= e($subject) ?></title>
<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
<style>
  :root { color-scheme: light dark; supported-color-schemes: light dark; }
  body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
  table, td { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
  img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
  a { color: <?= e($brand) ?>; }
  .link-url { word-break: break-all; }
  @media screen and (max-width: 620px) {
    .container { width: 100% !important; }
    .px { padding-left: 22px !important; padding-right: 22px !important; }
    .h1 { font-size: 23px !important; line-height: 30px !important; }
    .btn-a { display: block !important; }
  }
  @media (prefers-color-scheme: dark) {
    .bg-page { background-color: #111215 !important; }
    .bg-card { background-color: #1D1E22 !important; }
    .bg-soft { background-color: #27282D !important; }
    .t-ink { color: #F2F2F4 !important; }
    .t-muted { color: #B7B9C0 !important; }
    .line { border-color: #34353B !important; }
    .brand-bar { background-color: <?= e($brandDark) ?> !important; }
    .btn-td { background-color: <?= e($btnDark) ?> !important; }
    .btn-a { background-color: <?= e($btnDark) ?> !important; color: <?= e($onBtnDark) ?> !important; }
    .link { color: <?= e($brandDark) ?> !important; }
  }
  [data-ogsc] .bg-page { background-color: #111215 !important; }
  [data-ogsc] .bg-card { background-color: #1D1E22 !important; }
  [data-ogsc] .bg-soft { background-color: #27282D !important; }
  [data-ogsc] .t-ink { color: #F2F2F4 !important; }
  [data-ogsc] .t-muted { color: #B7B9C0 !important; }
  [data-ogsc] .btn-a { color: <?= e($onBtnDark) ?> !important; }
  [data-ogsb] .btn-td, [data-ogsb] .btn-a { background-color: <?= e($btnDark) ?> !important; }
</style>
</head>
<body class="bg-page" style="margin:0; padding:0; background-color:#F2F3F6;">
<div style="display:none; max-height:0; max-width:0; overflow:hidden; opacity:0; mso-hide:all; font-size:1px; line-height:1px; color:#F2F3F6;"><?= e($pre) ?><?= str_repeat('&#8199;&#847; ', 40) ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg-page" bgcolor="#F2F3F6" style="background-color:#F2F3F6;">
  <tr>
    <td align="center" style="padding:32px 12px;">
      <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
      <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px;">
        <!-- Kopf: App-Icon und Name der Website -->
        <tr>
          <td align="center" style="padding:0 0 20px;">
            <?php if ($logo): ?><img src="<?= e($logo) ?>" width="56" height="56" alt="<?= e($site) ?>" style="display:block; width:56px; height:56px; border-radius:14px; margin:0 auto 10px;"><?php endif; ?>
            <div class="t-ink" style="font-family:<?= $font ?>; font-size:17px; line-height:22px; font-weight:700; color:#1F2430;"><?= e($site) ?></div>
          </td>
        </tr>
        <!-- Karte -->
        <tr>
          <td class="bg-card" bgcolor="#FFFFFF" style="background-color:#FFFFFF; border-radius:12px; overflow:hidden;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr><td class="brand-bar" height="5" bgcolor="<?= e($brand) ?>" style="height:5px; line-height:5px; font-size:5px; background-color:<?= e($brand) ?>; border-radius:12px 12px 0 0;">&nbsp;</td></tr>
              <tr>
                <td class="px" style="padding:34px 40px 8px; font-family:<?= $font ?>;">
                  <h1 class="h1 t-ink" style="margin:0 0 16px; font-size:26px; line-height:33px; font-weight:800; color:#1F2430;"><?= e($title) ?></h1>
                  <p class="t-ink" style="margin:0 0 14px; font-size:16px; line-height:25px; color:#1F2430;"><?= e($greeting) ?></p>
                  <?php foreach ($paras as $p): ?><p class="t-ink" style="margin:0 0 16px; font-size:16px; line-height:25px; color:#1F2430;"><?= e($p) ?></p><?php endforeach; ?>
                </td>
              </tr>
              <?php if ($facts): // Bisher/Neu bzw. Konto ?>
              <tr>
                <td class="px" style="padding:0 40px 22px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg-soft" style="background-color:#F5F6F9; border-radius:8px;">
                    <?php foreach ($facts as $label => $value): ?>
                    <tr>
                      <td class="t-muted" width="90" style="padding:10px 0 10px 18px; font-family:<?= $font ?>; font-size:14px; line-height:20px; color:#5C5F6A; vertical-align:top;"><?= e($label) ?></td>
                      <td class="t-ink" style="padding:10px 18px 10px 8px; font-family:<?= $font ?>; font-size:15px; line-height:20px; font-weight:700; color:#1F2430; word-break:break-all;"><?= e($value) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </table>
                </td>
              </tr>
              <?php endif; ?>
              <?php if ($cta): ?>
              <!-- Schaltfläche (VML für Outlook unter Windows) -->
              <tr>
                <td class="px" align="center" style="padding:4px 40px 26px;">
                  <!--[if mso]>
                  <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?= e($cta['url']) ?>" style="height:52px; v-text-anchor:middle; width:360px;" arcsize="16%" stroke="f" fillcolor="<?= e($btn) ?>">
                    <w:anchorlock/>
                    <center style="color:<?= e($onBtn) ?>; font-family:Arial,sans-serif; font-size:16px; font-weight:bold;"><?= e($cta['label']) ?></center>
                  </v:roundrect>
                  <![endif]-->
                  <!--[if !mso]><!-->
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                    <tr>
                      <td class="btn-td" align="center" bgcolor="<?= e($btn) ?>" style="border-radius:8px; background-color:<?= e($btn) ?>;">
                        <a class="btn-a" href="<?= e($cta['url']) ?>" target="_blank" rel="noopener" style="display:inline-block; padding:15px 30px; font-family:<?= $font ?>; font-size:16px; line-height:22px; font-weight:700; color:<?= e($onBtn) ?>; background-color:<?= e($btn) ?>; text-decoration:none; border-radius:8px; -webkit-text-size-adjust:none;"><?= e($cta['label']) ?></a>
                      </td>
                    </tr>
                  </table>
                  <!--<![endif]-->
                  <?php if (($cta['note'] ?? '') !== ''): ?><p class="t-muted" style="margin:14px 0 0; font-family:<?= $font ?>; font-size:14px; line-height:20px; color:#5C5F6A;"><?= e($cta['note']) ?></p><?php endif; ?>
                </td>
              </tr>
              <?php endif; ?>
              <?php if ($after): ?>
              <tr>
                <td class="px" style="padding:0 40px 24px; font-family:<?= $font ?>;">
                  <?php foreach ($after as $p): ?><p class="t-ink" style="margin:0 0 10px; font-size:15px; line-height:23px; color:#1F2430;"><?= e($p) ?></p><?php endforeach; ?>
                </td>
              </tr>
              <?php endif; ?>
              <?php if ($cta): ?>
              <!-- Ersatz-Link -->
              <tr>
                <td class="px line" style="padding:20px 40px 30px; border-top:1px solid #E6E7EC; font-family:<?= $font ?>;">
                  <p class="t-muted" style="margin:0 0 6px; font-size:13px; line-height:19px; color:#5C5F6A;"><?= e(__('Funktioniert die Schaltfläche nicht? Kopieren Sie diese Adresse in Ihren Browser:')) ?></p>
                  <p class="link-url" style="margin:0; font-size:13px; line-height:19px; word-break:break-all;"><a class="link" href="<?= e($cta['url']) ?>" target="_blank" rel="noopener" style="color:<?= e($brand) ?>; text-decoration:underline;"><?= e($cta['url']) ?></a></p>
                </td>
              </tr>
              <?php endif; ?>
            </table>
          </td>
        </tr>
        <!-- Fuß -->
        <tr>
          <td class="px" align="center" style="padding:22px 30px 8px; font-family:<?= $font ?>;">
            <p class="t-muted" style="margin:0 0 6px; font-size:13px; line-height:19px; color:#5C5F6A;"><?= e($site) ?> · <a class="link" href="<?= e($siteUrl) ?>" target="_blank" rel="noopener" style="color:#5C5F6A; text-decoration:underline;"><?= e($siteHost) ?></a></p>
            <p class="t-muted" style="margin:0; font-size:12.5px; line-height:18px; color:#5C5F6A;"><?= e($foot) ?></p>
          </td>
        </tr>
      </table>
      <!--[if mso]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body>
</html>
