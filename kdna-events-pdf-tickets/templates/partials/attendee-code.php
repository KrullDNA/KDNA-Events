<?php
/**
 * Attendee name + large ticket code chip + order reference.
 *
 * Expects: $ticket, $data.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$name   = (string) ( $ticket->attendee_name ?? '' );
$code   = (string) ( $ticket->ticket_code ?? '' );
$design = (array) $data['design'];
$order  = $data['order'];
$order_ref = $order && isset( $order->order_reference ) ? (string) $order->order_reference : '';
?>
<div class="tkt-attendee">
	<?php if ( '' !== $name ) : ?>
		<p class="tkt-attendee__name" style="color:<?php echo esc_attr( $design['heading_color'] ); ?>;"><?php echo esc_html( $name ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $code ) : ?>
		<div class="tkt-attendee__code" style="background:<?php echo esc_attr( $design['accent_soft'] ); ?>;font-family:<?php echo esc_attr( $design['code_font'] ); ?>,monospace;font-size:<?php echo esc_attr( (string) (int) $design['code_size'] ); ?>pt;">
			<?php echo esc_html( $code ); ?>
		</div>
	<?php endif; ?>
	<?php if ( '' !== $order_ref ) : ?>
		<p class="tkt-attendee__order-ref"><?php printf( esc_html__( 'Order reference: %s', 'kdna-events-pdf-tickets' ), esc_html( $order_ref ) ); ?></p>
	<?php endif; ?>
</div>
