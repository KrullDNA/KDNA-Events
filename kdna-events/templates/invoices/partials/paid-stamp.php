<?php
/**
 * Rotated PAID / VOID stamp overlay. Only renders when the design
 * toggle is on AND the order is paid or the invoice is voided.
 *
 * Expects:
 *   $invoice Invoice record.
 *   $order   Order record (nullable).
 *   $design  Design tokens.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_paid = ! empty( $design['show_paid_stamp'] );
$voided    = isset( $invoice->status ) && 'voided' === $invoice->status;
$paid      = $order && isset( $order->status ) && 'paid' === $order->status;

if ( ! $voided && ( ! $paid || ! $show_paid ) ) {
	return;
}

$label = $voided
	? __( 'VOID', 'kdna-events' )
	: (string) get_option( 'kdna_events_invoice_paid_label', 'PAID' );
$colour = $voided ? '#B91C1C' : (string) ( $design['accent'] ?? '#F07759' );
?>
<div class="inv-paid-stamp" style="color:<?php echo esc_attr( $colour ); ?>;">
	<?php echo esc_html( $label ); ?>
</div>
