<?php
/**
 * Notes + tax-inclusive statement block.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notes     = trim( (string) get_option( 'kdna_events_invoice_notes', '' ) );
$statement = trim( (string) get_option( 'kdna_events_invoice_tax_inclusive_statement', '' ) );

if ( '' === $notes && '' === $statement ) {
	return;
}
?>
<div class="inv-notes">
	<?php if ( '' !== $notes ) : ?>
		<p style="margin:0 0 4pt;"><?php echo nl2br( esc_html( $notes ) ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $statement ) : ?>
		<p class="inv-tax-statement"><?php echo esc_html( $statement ); ?></p>
	<?php endif; ?>
</div>
