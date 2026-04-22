<?php
/**
 * Centred footer, fixed to the bottom of every page.
 *
 * Renders from the invoice snapshot so historical invoices keep the
 * business details they were issued with, even after settings change.
 *
 * Expects:
 *   $snapshot Associative array of business details.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$legal        = (string) ( $snapshot['legal_name'] ?? '' );
$tax_id_label = (string) ( $snapshot['tax_id_label'] ?? 'ABN' );
$tax_id       = (string) ( $snapshot['tax_id'] ?? '' );
$address      = (string) ( $snapshot['address'] ?? '' );
$phone        = (string) ( $snapshot['phone'] ?? '' );
$email        = (string) ( $snapshot['email'] ?? '' );
$website      = (string) ( $snapshot['website'] ?? '' );

$line1 = array();
if ( '' !== $legal ) {
	$line1[] = '<strong>' . esc_html( $legal ) . '</strong>';
}
if ( '' !== $tax_id ) {
	$line1[] = '<strong>' . esc_html( $tax_id_label ) . ':</strong> ' . esc_html( $tax_id );
}
$line3 = array();
if ( '' !== $phone ) {
	$line3[] = '<strong>T:</strong> ' . esc_html( $phone );
}
if ( '' !== $email ) {
	$line3[] = '<strong>E:</strong> ' . esc_html( $email );
}
if ( '' !== $website ) {
	$line3[] = '<strong>W:</strong> ' . esc_html( $website );
}
?>
<div class="inv-footer">
	<?php if ( ! empty( $line1 ) ) : ?>
		<p><?php echo implode( '  |  ', $line1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
	<?php endif; ?>
	<?php if ( '' !== $address ) : ?>
		<p><?php echo nl2br( esc_html( $address ) ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $line3 ) ) : ?>
		<p><?php echo implode( '  |  ', $line3 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
	<?php endif; ?>
</div>
