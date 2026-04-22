<?php
/**
 * Shared doctype + head block for KDNA Events emails.
 *
 * Expects:
 *   $design          Email Design options array.
 *   $heading_stack   Resolved font-family stack for headings.
 *   $body_stack      Resolved font-family stack for body text.
 *   $heading_google  Google Fonts URL or empty string.
 *   $body_google     Google Fonts URL or empty string.
 *   $subject         Plain subject line string.
 *   $inline_style    CSS string to be placed in a <style> block (will be
 *                    inlined by Emogrifier at send time, left as-is in
 *                    the preview).
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="x-apple-disable-message-reformatting" />
<meta name="color-scheme" content="light dark" />
<meta name="supported-color-schemes" content="light dark" />
<meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no" />
<title><?php echo esc_html( (string) $subject ); ?></title>
<?php if ( ! empty( $heading_google ) ) : ?>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link rel="stylesheet" href="<?php echo esc_url( $heading_google ); ?>" />
<?php endif; ?>
<?php if ( ! empty( $body_google ) && $body_google !== $heading_google ) : ?>
<link rel="stylesheet" href="<?php echo esc_url( $body_google ); ?>" />
<?php endif; ?>
<!--[if mso]>
<xml>
	<o:OfficeDocumentSettings>
		<o:PixelsPerInch>96</o:PixelsPerInch>
	</o:OfficeDocumentSettings>
</xml>
<style type="text/css">
	table, td { border-collapse: collapse !important; }
	.kdna-events-email-button, .kdna-events-email-virtual-button { mso-hide: all !important; display: none !important; }
</style>
<![endif]-->
<style type="text/css">
<?php echo $inline_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</style>
</head>
