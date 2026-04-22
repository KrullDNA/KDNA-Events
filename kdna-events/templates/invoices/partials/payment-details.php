<?php
/**
 * Payment details block. Only renders for paid orders or when the
 * payment terms setting has content.
 *
 * Expects:
 *   $order Order record (nullable).
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$method_label = (string) get_option( 'kdna_events_invoice_payment_method_label', 'Paid via Stripe' );
$terms        = (string) get_option( 'kdna_events_invoice_payment_terms', '' );
$paid         = $order && isset( $order->status ) && 'paid' === $order->status;
$stripe_ref   = $order && isset( $order->stripe_payment_intent ) ? (string) $order->stripe_payment_intent : '';

if ( ! $paid && '' === $terms ) {
	return;
}
?>
<div class="inv-payment">
	<?php if ( $paid ) : ?>
		<p><strong><?php esc_html_e( 'Payment method:', 'kdna-events' ); ?></strong> <?php echo esc_html( $method_label ); ?><?php if ( '' !== $stripe_ref ) : ?> <span style="color:#888888;font-family:monospace;"><?php echo esc_html( $stripe_ref ); ?></span><?php endif; ?></p>
		<?php if ( ! empty( $order->paid_at ) ) : ?>
			<p><strong><?php esc_html_e( 'Payment date:', 'kdna-events' ); ?></strong> <?php echo esc_html( mysql2date( 'd/m/Y', (string) $order->paid_at, true ) ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( '' !== $terms ) : ?>
		<p><?php echo esc_html( $terms ); ?></p>
	<?php endif; ?>
</div>
