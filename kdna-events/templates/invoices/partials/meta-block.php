<?php
/**
 * Invoice date + invoice number (right column of the meta grid).
 *
 * Expects:
 *   $invoice Invoice record.
 *   $order   Order record (nullable).
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$issued_raw   = (string) ( $invoice->issued_at ?? current_time( 'mysql' ) );
$issued_date  = '' !== $issued_raw ? mysql2date( 'd/m/Y', $issued_raw, true ) : '';
$status_label = '';
$status_class = '';
$paid         = $order && isset( $order->status ) && 'paid' === $order->status;
if ( $paid ) {
	$status_label = (string) get_option( 'kdna_events_invoice_paid_label', 'PAID' );
	$status_class = '';
} elseif ( $order ) {
	$status_label = (string) get_option( 'kdna_events_invoice_pending_label', 'PENDING PAYMENT' );
	$status_class = ' inv-status-chip--pending';
}
?>
<div class="inv-meta">
	<?php if ( '' !== $issued_date ) : ?>
		<p><strong><?php esc_html_e( 'Date:', 'kdna-events' ); ?></strong> <?php echo esc_html( $issued_date ); ?></p>
	<?php endif; ?>
	<p><strong><?php esc_html_e( 'Tax invoice #:', 'kdna-events' ); ?></strong> <?php echo esc_html( (string) $invoice->invoice_number ); ?></p>
	<?php if ( $order && isset( $order->order_reference ) && '' !== (string) $order->order_reference ) : ?>
		<p><strong><?php esc_html_e( 'Order ref:', 'kdna-events' ); ?></strong> <?php echo esc_html( (string) $order->order_reference ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $status_label ) : ?>
		<p><span class="inv-status-chip<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></p>
	<?php endif; ?>
</div>
