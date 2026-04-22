<?php
/**
 * Tax invoice template rendered through Dompdf.
 *
 * Layout per docs/pdf-tax-invoice-reference.pdf:
 *   - Logo top-left + document heading top-right
 *   - TO block on the left, date + invoice number on the right
 *   - Line items table with dark header row
 *   - Right-aligned totals stack (subtotal, tax, total amount)
 *   - Optional conditional blocks below: paid stamp overlay,
 *     payment details, notes + tax-inclusive statement
 *   - Fixed footer with business identity at the bottom of every page
 *
 * Variables provided by KDNA_Events_Invoices::render_html() and
 * KDNA_Events_Invoices::generate_sample():
 *   $invoice, $order (nullable), $context, $design
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$snapshot  = isset( $context['snapshot'] ) ? (array) $context['snapshot'] : array();
$heading   = (string) ( $snapshot['heading'] ?? get_option( 'kdna_events_invoice_document_heading', 'Tax Invoice' ) );
$page_size = (string) ( $design['page_size'] ?? 'A4' );
$margin_mm = (int) ( $design['page_margin'] ?? 20 );
$css_path  = KDNA_EVENTS_PATH . 'templates/invoices/css/invoice.css';
$css       = file_exists( $css_path ) ? (string) file_get_contents( $css_path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

// Override page margin + primary colour in-place via prepended rules.
$css = '@page { margin: ' . (int) $margin_mm . 'mm; }' . "\n" . $css;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title><?php echo esc_html( $heading ); ?> <?php echo esc_html( (string) ( $invoice->invoice_number ?? '' ) ); ?></title>
<style>
<?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</style>
</head>
<body>
	<?php include __DIR__ . '/partials/brand-header.php'; ?>

	<table class="inv-meta-grid" role="presentation" cellpadding="0" cellspacing="0" border="0">
		<tr>
			<td style="width:60%;"><?php include __DIR__ . '/partials/to-block.php'; ?></td>
			<td style="width:40%;text-align:right;"><?php include __DIR__ . '/partials/meta-block.php'; ?></td>
		</tr>
	</table>

	<?php include __DIR__ . '/partials/line-items.php'; ?>
	<?php include __DIR__ . '/partials/totals.php'; ?>
	<?php include __DIR__ . '/partials/paid-stamp.php'; ?>
	<?php include __DIR__ . '/partials/payment-details.php'; ?>
	<?php include __DIR__ . '/partials/notes.php'; ?>
	<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
