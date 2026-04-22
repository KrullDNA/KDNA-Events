<?php
/**
 * PDF ticket master template.
 *
 * One <div class="tkt-page"> per ticket. Dompdf breaks at
 * page-break-after on every page so the combined-mode single PDF
 * renders one ticket per physical page.
 *
 * Variables in scope via KDNA_Events_PDF_Generator::render_html():
 *   $data (merge-tag context + design tokens)
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tickets = (array) ( $data['tickets'] ?? array() );
$design  = (array) ( $data['design'] ?? array() );
$css     = file_exists( KDNA_EVENTS_PDF_PLUGIN_DIR . 'templates/css/ticket.css' )
	? (string) file_get_contents( KDNA_EVENTS_PDF_PLUGIN_DIR . 'templates/css/ticket.css' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	: '';

$margin = max( 5, min( 40, (int) ( $design['page_margin'] ?? 15 ) ) );
$css    = '@page { margin: ' . $margin . 'mm; }' . "\n" . $css;

$merge_tags = array(
	'event_title'    => (string) $data['event_title'],
	'event_date'     => (string) $data['event_date'],
	'event_time'     => (string) $data['event_time'],
	'event_location' => (string) $data['event_location'],
	'organiser_name' => (string) ( $data['organiser']['name'] ?? '' ),
);
$data['merge_tags'] = $merge_tags;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title><?php echo esc_html( (string) $data['event_title'] ); ?></title>
<style>
<?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</style>
</head>
<body style="background:<?php echo esc_attr( (string) $design['page_bg'] ); ?>;">
	<?php
	if ( empty( $tickets ) ) {
		echo '</body></html>';
		return;
	}
	$last = count( $tickets ) - 1;
	foreach ( $tickets as $i => $ticket ) :
		$page_style = $i < $last ? 'page-break-after:always;' : '';
		?>
		<div class="tkt-page" style="<?php echo esc_attr( $page_style ); ?>">
			<?php include __DIR__ . '/partials/brand-header.php'; ?>
			<?php include __DIR__ . '/partials/event-image.php'; ?>
			<?php include __DIR__ . '/partials/event-details.php'; ?>
			<?php include __DIR__ . '/partials/attendee-code.php'; ?>
			<?php include __DIR__ . '/partials/barcode.php'; ?>
			<?php include __DIR__ . '/partials/terms.php'; ?>
		</div>
	<?php endforeach; ?>

	<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
