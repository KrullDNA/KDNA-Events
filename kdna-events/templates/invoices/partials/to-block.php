<?php
/**
 * Buyer / recipient block (left column of the meta grid).
 *
 * Expects:
 *   $order Order row (possibly a sample).
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$name    = $order && isset( $order->purchaser_name ) ? (string) $order->purchaser_name : '';
$email   = $order && isset( $order->purchaser_email ) ? (string) $order->purchaser_email : '';
$phone   = $order && isset( $order->purchaser_phone ) ? (string) $order->purchaser_phone : '';
$address = $order && isset( $order->purchaser_address ) ? (string) $order->purchaser_address : '';
?>
<div class="inv-to">
	<p class="inv-to__label"><?php esc_html_e( 'To', 'kdna-events' ); ?></p>
	<table cellpadding="0" cellspacing="0" border="0">
		<?php if ( '' !== $name ) : ?>
			<tr><td class="inv-to__key"><?php esc_html_e( 'Name:', 'kdna-events' ); ?></td><td><?php echo esc_html( $name ); ?></td></tr>
		<?php endif; ?>
		<?php if ( '' !== $address ) : ?>
			<tr><td class="inv-to__key" style="vertical-align:top;"><?php esc_html_e( 'Address:', 'kdna-events' ); ?></td><td style="white-space:pre-line;"><?php echo esc_html( $address ); ?></td></tr>
		<?php endif; ?>
		<?php if ( '' !== $phone ) : ?>
			<tr><td class="inv-to__key"><?php esc_html_e( 'Phone:', 'kdna-events' ); ?></td><td><?php echo esc_html( $phone ); ?></td></tr>
		<?php endif; ?>
		<?php if ( '' !== $email ) : ?>
			<tr><td class="inv-to__key"><?php esc_html_e( 'Email:', 'kdna-events' ); ?></td><td><?php echo esc_html( $email ); ?></td></tr>
		<?php endif; ?>
	</table>
</div>
