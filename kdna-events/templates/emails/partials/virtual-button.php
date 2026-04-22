<?php
/**
 * Bulletproof Virtual Event link button. Renders ONLY when the event
 * is virtual/hybrid and has a virtual URL set, matching the PDF.
 *
 * Outlook Desktop Windows gets a VML rounded rectangle; every other
 * client renders the CSS <a> version. Both reach the same click target.
 *
 * Expects:
 *   $virtual_url      Target URL. Empty string disables the block.
 *   $button_label     Visible label.
 *   $button_bg        Background hex.
 *   $button_text      Text hex.
 *   $button_radius    Corner radius in px.
 *   $body_stack       Inline font-family stack.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( '' === (string) $virtual_url ) {
	return;
}

$radius   = max( 0, (int) $button_radius );
$arcsize  = min( 50, (int) round( ( $radius / 48 ) * 100 ) );
$arcsize  = $arcsize . '%';
$width_px = 240;
$height   = 48;
?>
<tr>
	<td class="kdna-events-email-button-row" align="center" style="padding:8px 0 24px;text-align:center;">
		<!--[if mso]>
		<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
			href="<?php echo esc_url( $virtual_url ); ?>"
			style="height:<?php echo esc_attr( (string) $height ); ?>px;v-text-anchor:middle;width:<?php echo esc_attr( (string) $width_px ); ?>px;"
			arcsize="<?php echo esc_attr( $arcsize ); ?>"
			stroke="f" fillcolor="<?php echo esc_attr( $button_bg ); ?>">
			<w:anchorlock/>
			<center style="color:<?php echo esc_attr( $button_text ); ?>;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:16px;font-weight:600;">
				<?php echo esc_html( $button_label ); ?>
			</center>
		</v:roundrect>
		<![endif]-->
		<!--[if !mso]><!-- -->
		<a class="kdna-events-email-virtual-button"
			href="<?php echo esc_url( $virtual_url ); ?>"
			style="background:<?php echo esc_attr( $button_bg ); ?>;border-radius:<?php echo esc_attr( (string) $radius ); ?>px;color:<?php echo esc_attr( $button_text ); ?>;display:inline-block;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:16px;font-weight:600;line-height:<?php echo esc_attr( (string) $height ); ?>px;padding:0 36px;text-align:center;text-decoration:none;-webkit-text-size-adjust:none;">
			<?php echo esc_html( $button_label ); ?>
		</a>
		<!--<![endif]-->
	</td>
</tr>
