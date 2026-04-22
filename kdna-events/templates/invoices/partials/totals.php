<?php
/**
 * Totals stack (right aligned), matching the reference PDF boxed rows.
 *
 * Expects:
 *   $invoice  Invoice record.
 *   $context  Context array.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$currency   = (string) ( $context['currency'] ?? 'AUD' );
$tax_rate   = (float) ( $invoice->tax_rate_applied ?? 0 );
$tax_label  = (string) ( $invoice->tax_label_applied ?? 'GST' );
$subtotal   = (float) ( $invoice->subtotal_ex_tax ?? 0 );
$tax_amount = (float) ( $invoice->tax_amount ?? 0 );
$total      = (float) ( $invoice->total_inc_tax ?? 0 );
?>
<table class="inv-totals" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<td class="label"><?php esc_html_e( 'Sub-total', 'kdna-events' ); ?></td>
		<td class="value"><?php echo esc_html( kdna_events_format_price( $subtotal, $currency ) ); ?></td>
	</tr>
	<?php if ( $tax_rate > 0 ) : ?>
		<tr>
			<td class="label">
				<?php
				printf(
					/* translators: 1: tax label, 2: rate */
					esc_html__( '%1$s %2$s%%', 'kdna-events' ),
					esc_html( $tax_label ),
					esc_html( rtrim( rtrim( number_format( $tax_rate, 2 ), '0' ), '.' ) )
				);
				?>
			</td>
			<td class="value"><?php echo esc_html( kdna_events_format_price( $tax_amount, $currency ) ); ?></td>
		</tr>
	<?php endif; ?>
	<tr class="grand">
		<td class="label"><?php esc_html_e( 'Total amount', 'kdna-events' ); ?></td>
		<td class="value"><?php echo esc_html( kdna_events_format_price( $total, $currency ) ); ?></td>
	</tr>
</table>
