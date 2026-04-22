<?php
/**
 * Brand header strip: background colour + logo.
 *
 * Expects:
 *   $design Resolved design tokens from the generator.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logo_url = $design['logo_id'] ? (string) wp_get_attachment_image_url( $design['logo_id'], 'medium' ) : '';
$align    = in_array( (string) $design['logo_align'], array( 'left', 'center' ), true ) ? (string) $design['logo_align'] : 'center';
$height   = max( 40, (int) $design['header_height'] );
?>
<div class="tkt-header" style="background:<?php echo esc_attr( $design['header_bg'] ); ?>;min-height:<?php echo esc_attr( (string) $height ); ?>px;text-align:<?php echo esc_attr( $align ); ?>;">
	<?php if ( '' !== $logo_url ) : ?>
		<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( (string) get_bloginfo( 'name' ) ); ?>" style="max-width:<?php echo esc_attr( (string) (int) $design['logo_width'] ); ?>px;" />
	<?php else : ?>
		<span style="color:#ffffff;font-size:14pt;font-weight:600;"><?php echo esc_html( (string) get_bloginfo( 'name' ) ); ?></span>
	<?php endif; ?>
</div>
