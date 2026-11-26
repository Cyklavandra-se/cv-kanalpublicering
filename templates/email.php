<?php
/**
 * Mall för nyhetsbrevet. Variabler: $m (utskicket), $s (inställningar), $trips.
 *
 * @var array $m
 * @var array $s
 * @var array $trips
 */

defined( 'ABSPATH' ) || exit;

$r       = 'CVKP_Email_Renderer';
$font    = $r::FONT;
$green   = $r::GREEN;
$button  = $r::GREEN_DARK;
$text_c  = $r::TEXT;
$head_c  = $r::HEADING;
$pad     = 'padding-left:30px;padding-right:30px;';
$eyebrow = "margin:0 0 4px 0;font-family:{$font};font-size:12px;line-height:16px;letter-spacing:1.5px;text-transform:uppercase;color:{$green};font-weight:bold;";
$h3      = "margin:0 0 10px 0;font-family:{$font};font-size:23px;line-height:29px;color:{$head_c};font-weight:bold;";
$address = array_filter( array_map( 'trim', explode( "\n", (string) $s['address'] ) ) );
?><!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>*|MC:SUBJECT|*</title>
<!--[if gte mso 15]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
<style type="text/css">
  body,#bodyTable{margin:0;padding:0;width:100%;height:100%;background:#F2F4F1;}
  table{border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;}
  img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;display:block;height:auto;}
  p{margin:0 0 14px 0;}
  a{color:<?php echo esc_html( $green ); ?>;}
  .mcnPreviewText{display:none !important;}
  @media only screen and (max-width:480px){
    .container{width:100% !important;}
    .pad{padding-left:20px !important;padding-right:20px !important;}
    .h1{font-size:28px !important;line-height:34px !important;}
    .code{font-size:22px !important;letter-spacing:1px !important;}
    .h3{font-size:21px !important;line-height:27px !important;}
  }
  /* Adressen i footern: Gmail, Apple Mail och Outlook gör adresser till blå länkar på egen hand.
     De här reglerna ger sådana länkar footerns färg och tar bort understrykningen. */
  .cvkp-address a, .cvkp-address a:link, .cvkp-address a:visited { color:#FFF0F5 !important; text-decoration:none !important; cursor:text; }
  u + #body .cvkp-address a, #MessageViewBody .cvkp-address a { color:#FFF0F5 !important; text-decoration:none !important; }
  a[x-apple-data-detectors] { color:inherit !important; text-decoration:none !important; font-size:inherit !important; font-family:inherit !important; font-weight:inherit !important; line-height:inherit !important; }
</style>
</head>
<body id="body" style="margin:0;padding:0;background:#F2F4F1;">
<?php if ( '' !== trim( $m['preheader'] ) ) : ?>
<span class="mcnPreviewText" style="display:none;font-size:0;line-height:0;max-height:0;max-width:0;opacity:0;overflow:hidden;visibility:hidden;mso-hide:all;"><?php echo esc_html( $m['preheader'] ); ?></span>
<?php endif; ?>
<center>
<table role="presentation" id="bodyTable" width="100%" border="0" cellpadding="0" cellspacing="0" style="background:#F2F4F1;">
<tr><td align="center" valign="top" style="padding:24px 0 0 0;">

<!--[if mso]><table role="presentation" width="640" align="center" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
<table role="presentation" class="container" width="640" border="0" cellpadding="0" cellspacing="0" style="width:640px;max-width:640px;background:#FFFFFF;">

  <!-- LOGGA -->
  <tr><td align="center" class="pad" style="padding:30px 30px 26px 30px;border-bottom:1px solid #E6E9E4;">
    <a href="<?php echo esc_url( $s['button_url'] ); ?>" target="_blank"><img src="<?php echo esc_url( $s['logo_url'] ); ?>" width="320" alt="Cykla &amp; Vandra" style="width:320px;max-width:80%;height:auto;margin:0 auto;"></a>
  </td></tr>

  <!-- RUBRIK OCH INTRO -->
  <tr><td class="pad" style="padding-top:38px;padding-bottom:6px;<?php echo esc_attr( $pad ); ?>font-family:<?php echo esc_attr( $font ); ?>;">
    <?php if ( '' !== trim( $m['eyebrow'] ) ) : ?>
    <p style="margin:0 0 10px 0;font-size:13px;line-height:18px;letter-spacing:2px;text-transform:uppercase;color:<?php echo esc_attr( $green ); ?>;font-weight:bold;text-align:center;"><?php echo esc_html( $m['eyebrow'] ); ?></p>
    <?php endif; ?>
    <h1 class="h1" style="margin:0 0 18px 0;font-family:<?php echo esc_attr( $font ); ?>;font-size:34px;line-height:40px;color:<?php echo esc_attr( $head_c ); ?>;font-weight:bold;text-align:center;"><?php echo esc_html( $m['heading'] ); ?></h1>
    <?php echo $r::rich( $m['intro'], 'center' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
  </td></tr>

  <?php if ( '' !== trim( $m['code'] ) ) : ?>
  <!-- RABATTKOD -->
  <tr><td class="pad" style="padding-top:6px;padding-bottom:10px;<?php echo esc_attr( $pad ); ?>">
    <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="background:#EAF6EE;border:2px dashed <?php echo esc_attr( $button ); ?>;">
      <tr><td align="center" style="padding:22px 16px 20px 16px;font-family:<?php echo esc_attr( $font ); ?>;">
        <p style="margin:0 0 6px 0;font-size:13px;line-height:18px;letter-spacing:1px;text-transform:uppercase;color:<?php echo esc_attr( $text_c ); ?>;">Rabattkod</p>
        <p class="code" style="margin:0 0 8px 0;font-family:'Courier New',Courier,monospace;font-size:28px;line-height:34px;letter-spacing:3px;font-weight:bold;color:<?php echo esc_attr( $head_c ); ?>;"><?php echo esc_html( $m['code'] ); ?></p>
        <?php if ( '' !== trim( $m['code_note'] ) ) : ?>
        <p style="margin:0;font-size:14px;line-height:21px;color:<?php echo esc_attr( $text_c ); ?>;"><?php echo esc_html( $m['code_note'] ); ?></p>
        <?php endif; ?>
      </td></tr>
    </table>
  </td></tr>
  <?php endif; ?>

  <?php if ( '' !== trim( $m['after_offer'] ) ) : ?>
  <tr><td class="pad" style="padding-top:18px;padding-bottom:8px;<?php echo esc_attr( $pad ); ?>">
    <?php echo $r::rich( $m['after_offer'], 'center' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
  </td></tr>
  <?php endif; ?>

  <?php if ( $trips ) : ?>
  <!-- RESOR: INTRO -->
  <tr><td class="pad" style="padding-top:22px;padding-bottom:4px;<?php echo esc_attr( $pad ); ?>font-family:<?php echo esc_attr( $font ); ?>;border-top:1px solid #E6E9E4;">
    <?php if ( '' !== trim( $m['trips_heading'] ) ) : ?>
    <h2 style="margin:22px 0 12px 0;font-family:<?php echo esc_attr( $font ); ?>;font-size:24px;line-height:30px;color:<?php echo esc_attr( $head_c ); ?>;font-weight:bold;"><?php echo esc_html( $m['trips_heading'] ); ?></h2>
    <?php endif; ?>
    <?php echo $r::rich( $m['trips_intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
  </td></tr>

    <?php foreach ( $trips as $trip ) : ?>
  <!-- RESA: <?php echo esc_html( $trip['data']['title'] ); ?> -->
      <?php if ( $trip['data']['image_full'] ) : ?>
  <tr><td style="padding:22px 0 0 0;">
    <img src="<?php echo esc_url( $trip['data']['image_full'] ); ?>" width="640" alt="<?php echo esc_attr( $trip['data']['title'] ); ?>" style="width:100%;max-width:640px;height:auto;">
  </td></tr>
      <?php endif; ?>
  <tr><td class="pad" style="padding-top:16px;padding-bottom:8px;<?php echo esc_attr( $pad ); ?>font-family:<?php echo esc_attr( $font ); ?>;">
      <?php if ( '' !== trim( $trip['label'] ) ) : ?>
    <p style="<?php echo esc_attr( $eyebrow ); ?>"><?php echo esc_html( $trip['label'] ); ?></p>
      <?php endif; ?>
    <h3 class="h3" style="<?php echo esc_attr( $h3 ); ?>"><?php echo esc_html( $trip['heading'] ); ?></h3>
    <?php echo $r::trip_text( $trip['text'], $trip['data']['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
  </td></tr>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ( '' !== trim( $m['closing'] ) ) : ?>
  <!-- AVSLUTNING -->
  <tr><td class="pad" style="padding-top:22px;padding-bottom:10px;<?php echo esc_attr( $pad ); ?>">
    <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="background:#F2F4F1;">
      <tr><td align="center" style="padding:22px 20px 8px 20px;">
        <?php echo $r::rich( $m['closing'], 'center' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
      </td></tr>
    </table>
  </td></tr>
  <?php endif; ?>

  <tr><td class="pad" style="padding-top:18px;padding-bottom:4px;<?php echo esc_attr( $pad ); ?>">
    <?php echo $r::rich( $m['greeting'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <?php echo $r::rich( $m['ps'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
  </td></tr>

  <?php if ( '' !== trim( $m['button_text'] ) && '' !== trim( $m['button_url'] ) ) : ?>
  <tr><td align="center" style="padding:10px 30px 36px 30px;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse:separate;">
      <tr><td align="center" bgcolor="<?php echo esc_attr( $button ); ?>" style="border-radius:4px;background:<?php echo esc_attr( $button ); ?>;">
        <a href="<?php echo esc_url( $m['button_url'] ); ?>" target="_blank" style="display:inline-block;padding:16px 30px;font-family:<?php echo esc_attr( $font ); ?>;font-size:17px;font-weight:bold;color:#FFFFFF;text-decoration:none;"><?php echo esc_html( $m['button_text'] ); ?></a>
      </td></tr>
    </table>
  </td></tr>
  <?php endif; ?>

  <!-- FOOTER -->
  <tr><td align="center" style="background:#333333;padding:18px 18px 8px 18px;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr>
      <td style="padding:5px 10px;"><a href="<?php echo esc_url( $s['facebook_url'] ); ?>" target="_blank"><img src="https://cdn-images.mailchimp.com/icons/social-block-v2/outline-light-facebook-48.png" width="24" height="24" alt="Facebook" style="display:block;"></a></td>
      <td style="padding:5px 10px;"><a href="<?php echo esc_url( $s['instagram_url'] ); ?>" target="_blank"><img src="https://cdn-images.mailchimp.com/icons/social-block-v2/outline-light-instagram-48.png" width="24" height="24" alt="Instagram" style="display:block;"></a></td>
      <td style="padding:5px 10px;"><a href="<?php echo esc_url( $s['button_url'] ); ?>" target="_blank"><img src="https://cdn-images.mailchimp.com/icons/social-block-v2/outline-light-link-48.png" width="24" height="24" alt="Webbplats" style="display:block;"></a></td>
    </tr></table>
  </td></tr>
  <tr><td style="background:#333333;padding:0 18px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="border-top:2px solid #505050;font-size:0;line-height:0;padding-top:18px;">&nbsp;</td></tr></table></td></tr>
  <tr><td align="center" style="background:#333333;padding:0 18px 24px 18px;font-family:<?php echo esc_attr( $font ); ?>;font-size:12px;line-height:18px;color:#FFF0F5;text-align:center;">
    <em>Copyright © *|CURRENT_YEAR|* <?php echo esc_html( $s['company_name'] ); ?>, All rights reserved.</em><br>
    Vi skickar regelbundet erbjudanden och information till er som har kontaktat oss. Vill ni inte längre få nyhetsbrevet kan ni enkelt avprenumerera via länken längst ner.<br><br>
    <span class="cvkp-address" style="color:#FFF0F5;text-decoration:none;"><?php echo $r::unlinkable( $s['company_name'] ); // phpcs:ignore ?><br><?php echo implode( '<br>', array_map( array( $r, 'unlinkable' ), $address ) ); // phpcs:ignore ?></span><br><br>
    <a href="*|LIST:ADDRESS_VCARD_HREF|*" style="color:#FFFFFF;text-decoration:underline;">Lägg till oss i din adressbok</a><br><br>
    Vill du ändra hur du får dessa nyhetsbrev?<br>
    Du kan <a href="*|UPDATE_PROFILE|*" style="color:#FFFFFF;text-decoration:underline;">uppdatera dina inställningar</a> eller <a href="*|UNSUB|*" style="color:#FFFFFF;text-decoration:underline;">avprenumerera dig</a>.
  </td></tr>

</table>
<!--[if mso]></td></tr></table><![endif]-->

</td></tr>
</table>
</center>
</body>
</html>
