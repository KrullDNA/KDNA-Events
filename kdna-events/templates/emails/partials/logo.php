<?php
/**
 * Logo row rendered ABOVE the white content card on the grey page
 * background. Hides cleanly when no logo is configured.
 *
 * Expects:
 *   $logo_url      Absolute URL of the logo image, or ''.
 *   $logo_width    Display width in px.
 *   $logo_align    'left' or 'center'.
 *   $site_name     Fallback alt text.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( '' === (string) $logo_url ) {
	return;
}

$align = in_array( (string) $logo_align, array( 'left', 'center' ), true ) ? (string) $logo_align : 'center';
$width = max( 40, min( 400, (int) $logo_width ) );
?>
<tr>
	<td class="kdna-events-email-logo-row" align="<?php echo esc_attr( $align ); ?>" style="padding:36px 24px 24px;text-align:<?php echo esc_attr( $align ); ?>;">
		<img
			src="<?php echo esc_url( $logo_url ); ?>"
			alt="<?php echo esc_attr( (string) $site_name ); ?>"
			width="<?php echo esc_attr( (string) $width ); ?>"
			style="display:inline-block;border:0;outline:none;text-decoration:none;max-width:<?php echo esc_attr( (string) $width ); ?>px;height:auto;"
		/>
	</td>
</tr>
