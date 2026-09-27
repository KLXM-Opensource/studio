<?php
/**
 * E-Mail „Einladung“ (HTML) – Core\Invites::mail(). Im Kit überschreibbar: kits/{kit}/templates/mail/invitation.php
 * (Text: invitation.txt.php). Tabellen-Layout für Outlook (VML-Schaltfläche), Gmail, Apple Mail; hell/dunkel (color-scheme,
 * prefers-color-scheme, [data-ogsc] für Outlook.com); keine Hintergrundbilder. Alle Werte escaped.
 *
 * @var string $lang  @var string $subject  @var string $site  @var string $siteUrl  @var string $siteHost  @var ?string $logo (cid:logo)
 * @var string $brand  @var string $brandDark  @var string $onBrand  @var string $onBrandDark  @var string $inviter  @var string $role
 * @var string $name  @var string $message  @var string $url  @var string $expires  @var bool $passkeys  @var bool $passwordless
 */
$font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
$cta = __('Einladung annehmen');
$pre = __('{name} hat Sie als {role} zu {site} eingeladen.', ['name' => $inviter, 'role' => $role, 'site' => $site]);
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
    .quote { border-color: <?= e($brandDark) ?> !important; }
    .btn-td { background-color: <?= e($brandDark) ?> !important; }
    .btn-a { background-color: <?= e($brandDark) ?> !important; color: <?= e($onBrandDark) ?> !important; }
    .link { color: <?= e($brandDark) ?> !important; }
  }
  [data-ogsc] .bg-page { background-color: #111215 !important; }
  [data-ogsc] .bg-card { background-color: #1D1E22 !important; }
  [data-ogsc] .bg-soft { background-color: #27282D !important; }
  [data-ogsc] .t-ink { color: #F2F2F4 !important; }
  [data-ogsc] .t-muted { color: #B7B9C0 !important; }
  [data-ogsc] .btn-a { color: <?= e($onBrandDark) ?> !important; }
  [data-ogsb] .btn-td, [data-ogsb] .btn-a { background-color: <?= e($brandDark) ?> !important; }
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
                  <h1 class="h1 t-ink" style="margin:0 0 16px; font-size:26px; line-height:33px; font-weight:800; color:#1F2430;"><?= e(__('Sie wurden zu {site} eingeladen', ['site' => $site])) ?></h1>
                  <p class="t-ink" style="margin:0 0 14px; font-size:16px; line-height:25px; color:#1F2430;"><?= e($name !== '' ? __('Guten Tag {name},', ['name' => $name]) : __('Guten Tag,')) ?></p>
                  <p class="t-ink" style="margin:0 0 20px; font-size:16px; line-height:25px; color:#1F2430;"><?= __('{name} lädt Sie ein, als {role} an der Website {site} mitzuarbeiten.', [
                      'name' => '<strong>' . e($inviter) . '</strong>', 'role' => '<strong>' . e($role) . '</strong>', 'site' => e($site)]) ?></p>
                </td>
              </tr>
              <?php if ($message !== ''): ?>
              <tr>
                <td class="px" style="padding:0 40px 20px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td class="quote bg-soft" style="border-left:4px solid <?= e($brand) ?>; background-color:#F5F6F9; padding:14px 18px; border-radius:0 8px 8px 0; font-family:<?= $font ?>;">
                        <p class="t-ink" style="margin:0; font-size:15px; line-height:23px; font-style:italic; color:#1F2430;"><?= nl2br(e($message)) ?></p>
                        <p class="t-muted" style="margin:8px 0 0; font-size:13px; line-height:18px; color:#5C5F6A;">– <?= e($inviter) ?></p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <?php endif; ?>
              <!-- Schaltfläche (VML für Outlook unter Windows) -->
              <tr>
                <td class="px" align="center" style="padding:8px 40px 26px;">
                  <!--[if mso]>
                  <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?= e($url) ?>" style="height:52px; v-text-anchor:middle; width:300px;" arcsize="16%" stroke="f" fillcolor="<?= e($brand) ?>">
                    <w:anchorlock/>
                    <center style="color:<?= e($onBrand) ?>; font-family:Arial,sans-serif; font-size:17px; font-weight:bold;"><?= e($cta) ?></center>
                  </v:roundrect>
                  <![endif]-->
                  <!--[if !mso]><!-->
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                    <tr>
                      <td class="btn-td" align="center" bgcolor="<?= e($brand) ?>" style="border-radius:8px; background-color:<?= e($brand) ?>;">
                        <a class="btn-a" href="<?= e($url) ?>" target="_blank" rel="noopener" style="display:inline-block; padding:15px 36px; font-family:<?= $font ?>; font-size:17px; line-height:22px; font-weight:700; color:<?= e($onBrand) ?>; background-color:<?= e($brand) ?>; text-decoration:none; border-radius:8px; -webkit-text-size-adjust:none;"><?= e($cta) ?></a>
                      </td>
                    </tr>
                  </table>
                  <!--<![endif]-->
                  <p class="t-muted" style="margin:14px 0 0; font-family:<?= $font ?>; font-size:14px; line-height:20px; color:#5C5F6A;"><?= e(__('Die Einladung gilt bis {date}.', ['date' => $expires])) ?></p>
                </td>
              </tr>
              <!-- So geht es weiter -->
              <tr>
                <td class="px" style="padding:0 40px 26px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td class="bg-soft" style="background-color:#F5F6F9; border-radius:8px; padding:16px 18px; font-family:<?= $font ?>;">
                        <p class="t-ink" style="margin:0 0 6px; font-size:15px; line-height:21px; font-weight:700; color:#1F2430;"><?= e(__('So geht es weiter')) ?></p>
                        <p class="t-ink" style="margin:0; font-size:14.5px; line-height:22px; color:#1F2430;"><?= e($passkeys && $passwordless
                            ? __('Nach dem Klick geben Sie Ihren Namen ein und wählen, wie Sie sich künftig anmelden: am einfachsten mit einem Passkey – bestätigt per Fingerabdruck, Gesicht oder Geräte-PIN, ohne Passwort – oder mit einem eigenen Passwort (mindestens 12 Zeichen).')
                            : ($passkeys ? __('Nach dem Klick geben Sie Ihren Namen ein und legen ein Passwort fest (mindestens 12 Zeichen). Zusätzlich können Sie einen Passkey einrichten – bestätigt per Fingerabdruck, Gesicht oder Geräte-PIN.')
                                : __('Nach dem Klick geben Sie Ihren Namen ein und legen ein Passwort fest (mindestens 12 Zeichen).'))) ?></p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <!-- Ersatz-Link -->
              <tr>
                <td class="px line" style="padding:20px 40px 30px; border-top:1px solid #E6E7EC; font-family:<?= $font ?>;">
                  <p class="t-muted" style="margin:0 0 6px; font-size:13px; line-height:19px; color:#5C5F6A;"><?= e(__('Funktioniert die Schaltfläche nicht? Kopieren Sie diese Adresse in Ihren Browser:')) ?></p>
                  <p class="link-url" style="margin:0; font-size:13px; line-height:19px; word-break:break-all;"><a class="link" href="<?= e($url) ?>" target="_blank" rel="noopener" style="color:<?= e($brand) ?>; text-decoration:underline;"><?= e($url) ?></a></p>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <!-- Fuß -->
        <tr>
          <td class="px" align="center" style="padding:22px 30px 8px; font-family:<?= $font ?>;">
            <p class="t-muted" style="margin:0 0 6px; font-size:13px; line-height:19px; color:#5C5F6A;"><?= e($site) ?> · <a class="link" href="<?= e($siteUrl) ?>" target="_blank" rel="noopener" style="color:#5C5F6A; text-decoration:underline;"><?= e($siteHost) ?></a></p>
            <p class="t-muted" style="margin:0; font-size:12.5px; line-height:18px; color:#5C5F6A;"><?= e(__('Falls Sie diese E-Mail nicht erwartet haben, können Sie sie ignorieren.')) ?></p>
          </td>
        </tr>
      </table>
      <!--[if mso]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body>
</html>
