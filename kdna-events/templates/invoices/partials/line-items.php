<?php
/**
 * Invoice line items table.
 *
 * Expects:
 *   $context Context array from KDNA_Events_Invoices::build_context().
 *   $design  Resolved design tokens (for the header background).
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items    = isset( $context['line_items'] ) && is_array( $context['line_items'] ) ? $context['line_items'] : array();
$currency = (string) ( $context['currency'] ?? 'AUD' );
$primary  = (string) ( $design['primary'] ?? '#1A1A1A' );
?>
<table class="inv-items" cellpadding="0" cellspacing="0" border="0">
	<thead>
		<tr>
			<th style="background:<?php echo esc_attr( $primary ); ?>;"><?php esc_html_e( 'Description', 'kdna-events' ); ?></th>
			<th class="num" style="background:<?php echo esc_attr( $primary ); ?>;width:70pt;"><?php esc_html_e( 'Qty', 'kdna-events' ); ?></th>
			<th class="num" style="background:<?php echo esc_attr( $primary ); ?>;width:100pt;"><?php esc_html_e( 'Total', 'kdna-events' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $items as $row ) : ?>
			<tr>
				<td><?php echo esc_html( (string) ( $row['description'] ?? '' ) ); ?></td>
				<td class="num"><?php echo esc_html( (string) (int) ( $row['quantity'] ?? 1 ) ); ?></td>
				<td class="num"><?php echo esc_html( kdna_events_format_price( (float) ( $row['line_total'] ?? 0 ), $currency ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
