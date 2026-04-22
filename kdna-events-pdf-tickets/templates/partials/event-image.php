<?php
/**
 * Event hero banner. Uses core's Brief A header-image cascade.
 *
 * Expects: $data['header_image_url'].
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $data['header_image_url'] ) ) {
	$default_id = (int) ( $data['design']['default_image'] ?? 0 );
	$url        = $default_id ? (string) wp_get_attachment_image_url( $default_id, 'large' ) : '';
	if ( '' === $url ) {
		return;
	}
} else {
	$url = (string) $data['header_image_url'];
}
?>
<div class="tkt-hero">
	<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( (string) $data['event_title'] ); ?>" />
</div>
