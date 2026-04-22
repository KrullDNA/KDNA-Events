<?php
/**
 * Terms / fine print block. Hides when empty.
 *
 * Expects: $data, $context (merge tag context passed to renderer).
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$text = (string) ( $data['design']['terms_text'] ?? '' );
if ( '' === trim( $text ) ) {
	return;
}
if ( function_exists( 'kdna_events_render_merge_tags' ) ) {
	$text = kdna_events_render_merge_tags( $text, (array) ( $data['merge_tags'] ?? array() ) );
}
?>
<div class="tkt-terms">
	<?php echo nl2br( esc_html( $text ) ); ?>
</div>
