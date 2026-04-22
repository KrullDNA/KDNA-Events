<?php
/**
 * Code 128 barcode block. Renders PNG via picqer, printed with the
 * human-readable ticket code below for staff verification.
 *
 * Expects: $ticket, $data.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$code   = (string) ( $ticket->ticket_code ?? '' );
if ( '' === $code ) {
	return;
}

$design = (array) $data['design'];

$barcode_width_px  = max( 200, (int) round( (int) $design['barcode_width'] * 3.78 ) ); // mm → px @96dpi approx
$barcode_height_px = max( 40, (int) round( (int) $design['barcode_height'] * 3.78 ) );

$data_uri = class_exists( 'KDNA_Events_PDF_Barcode' )
	? KDNA_Events_PDF_Barcode::render( $code, $barcode_width_px * 2, $barcode_height_px * 2 )
	: '';
?>
<div class="tkt-barcode">
	<?php if ( '' !== $data_uri ) : ?>
		<img src="<?php echo esc_attr( $data_uri ); ?>" alt="<?php echo esc_attr( $code ); ?>" style="width:<?php echo esc_attr( (string) (int) $design['barcode_width'] ); ?>mm;height:<?php echo esc_attr( (string) (int) $design['barcode_height'] ); ?>mm;" />
	<?php endif; ?>
	<?php if ( ! empty( $design['barcode_show_text'] ) ) : ?>
		<p class="tkt-barcode__code" style="font-family:<?php echo esc_attr( (string) $design['code_font'] ); ?>,monospace;"><?php echo esc_html( $code ); ?></p>
	<?php endif; ?>
</div>
