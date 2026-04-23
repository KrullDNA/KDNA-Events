<?php
/**
 * PDF ticket master template, matched to
 * docs/pdf-ticket-reference.pdf exactly.
 *
 * Top row: logo top-left, 'Ticket' label top-right.
 * Body:    one <table class="tkt-card"> per ticket; the side strip,
 *          content area, and event image sit in three td cells so
 *          Dompdf can render the horizontal card without any
 *          flex / grid support.
 * Footer:  shared fixed footer at the bottom of every page.
 *
 * Variables in scope via KDNA_Events_PDF_Generator::render_html():
 *   $data (merge-tag context + design tokens).
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

$margin = max( 5, min( 40, (int) ( $design['page_margin'] ?? 18 ) ) );
$css    = '@page { margin: ' . $margin . 'mm ' . $margin . 'mm ' . ( $margin + 6 ) . 'mm; }' . "\n" . $css;

// Inject @font-face rules + override font-family declarations when
// the admin has supplied a TTF URL. Dompdf will fetch the TTF on
// first render (isRemoteEnabled is on in the generator) and cache it.
//
// Dompdf inherits font-family unreliably through descendants, so
// the body URL is broadcast to every text selector explicitly. The
// heading URL then overrides headings + meta on top, so any of the
// three combinations (only body, only heading, both) produces the
// expected visual result.
$heading_face = trim( (string) ( $design['heading_font_url'] ?? '' ) );
$body_face    = trim( (string) ( $design['body_font_url'] ?? '' ) );
$face_css     = '';
$body_targets = "body, .tkt-event-name, .tkt-meta__label, .tkt-meta__value, .tkt-location__label, .tkt-location__value, .tkt-terms, .tkt-top__title, .tkt-footer, .tkt-footer p, .tkt-footer strong";
$heading_targets = ".tkt-event-name, .tkt-meta__label, .tkt-meta__value, .tkt-location__label, .tkt-location__value, .tkt-top__title";
if ( '' !== $body_face ) {
	$face_css .= "@font-face { font-family: 'KdnaPdfBody'; src: url('" . esc_url_raw( $body_face ) . "') format('truetype'); font-weight: normal; font-style: normal; }\n";
	$face_css .= $body_targets . " { font-family: 'KdnaPdfBody', " . ( $design['body_font'] ?? 'helvetica' ) . ", sans-serif !important; }\n";
}
if ( '' !== $heading_face ) {
	$face_css .= "@font-face { font-family: 'KdnaPdfHeading'; src: url('" . esc_url_raw( $heading_face ) . "') format('truetype'); font-weight: normal; font-style: normal; }\n";
	$face_css .= $heading_targets . " { font-family: 'KdnaPdfHeading', " . ( $design['heading_font'] ?? 'helvetica' ) . ", sans-serif !important; }\n";
}
$css .= "\n" . $face_css;

// Pull variables up once so each ticket block can reference them.
$logo_id  = (int) ( $design['logo_id'] ?? 0 );
$logo_url = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

$event_name     = (string) $data['event_title'];
$event_date     = (string) $data['event_date'];
$event_time     = (string) $data['event_time'];
$event_type_key = (string) $data['event_type'];
$type_labels    = array(
	'in-person' => __( 'In-person', 'kdna-events-pdf-tickets' ),
	'virtual'   => __( 'Virtual', 'kdna-events-pdf-tickets' ),
	'hybrid'    => __( 'Hybrid', 'kdna-events-pdf-tickets' ),
);
$event_type     = isset( $type_labels[ $event_type_key ] ) ? $type_labels[ $event_type_key ] : ucfirst( $event_type_key );
$event_location = (string) $data['event_location'];

$header_image_url = (string) ( $data['header_image_url'] ?? '' );
if ( '' === $header_image_url && ! empty( $design['default_image'] ) ) {
	$header_image_url = (string) wp_get_attachment_image_url( (int) $design['default_image'], 'large' );
}

$event_terms = (string) ( $data['event_terms'] ?? '' );

// Business footer snapshot.
$biz      = trim( (string) ( $design['business_name'] ?? '' ) );
$website  = trim( (string) ( $design['website_url'] ?? '' ) );
$support  = trim( (string) ( $design['support_email'] ?? '' ) );
$phone    = trim( (string) ( $design['support_phone'] ?? '' ) );
$tax_id   = trim( (string) get_option( 'kdna_events_invoice_tax_id', '' ) ); // inherit from core tax invoices when configured
$address  = trim( (string) get_option( 'kdna_events_invoice_business_address', '' ) );
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title><?php echo esc_html( $event_name ); ?></title>
<style>
<?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</style>
</head>
<body>
	<?php
	if ( empty( $tickets ) ) {
		echo '</body></html>';
		return;
	}
	$last = count( $tickets ) - 1;
	foreach ( $tickets as $i => $ticket ) :
		$page_style = $i < $last ? 'page-break-after:always;' : '';
		$code       = (string) ( $ticket->ticket_code ?? '' );
		$barcode    = '' !== $code && class_exists( 'KDNA_Events_PDF_Barcode' )
			? KDNA_Events_PDF_Barcode::render( $code, 480, 120 )
			: '';
		?>
		<div style="<?php echo esc_attr( $page_style ); ?>">

			<!-- Top row: logo + 'Ticket' label -->
			<table class="tkt-top" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td class="tkt-top__logo" style="width:50%;">
						<?php if ( '' !== $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( (string) get_bloginfo( 'name' ) ); ?>" style="max-width:<?php echo esc_attr( (string) (int) $design['logo_width'] ); ?>px;" />
						<?php endif; ?>
					</td>
					<td class="tkt-top__title" style="width:50%;"><?php esc_html_e( 'Ticket', 'kdna-events-pdf-tickets' ); ?></td>
				</tr>
			</table>

			<!-- Horizontal ticket card -->
			<table class="tkt-card" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td class="tkt-card__side"><span><?php esc_html_e( 'Single Ticket', 'kdna-events-pdf-tickets' ); ?></span></td>
					<td class="tkt-card__body">
						<h2 class="tkt-event-name"><?php echo esc_html( $event_name ); ?></h2>

						<table class="tkt-meta" cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td>
									<span class="tkt-meta__label"><?php esc_html_e( 'Event date:', 'kdna-events-pdf-tickets' ); ?></span>
									<span class="tkt-meta__value"><?php echo esc_html( $event_date ); ?></span>
								</td>
								<td>
									<span class="tkt-meta__label"><?php esc_html_e( 'Event time:', 'kdna-events-pdf-tickets' ); ?></span>
									<span class="tkt-meta__value"><?php echo esc_html( $event_time ); ?></span>
								</td>
								<td>
									<span class="tkt-meta__label"><?php esc_html_e( 'Event type:', 'kdna-events-pdf-tickets' ); ?></span>
									<span class="tkt-meta__value"><?php echo esc_html( $event_type ); ?></span>
								</td>
							</tr>
						</table>

						<?php if ( '' !== $event_location ) : ?>
							<div class="tkt-location">
								<span class="tkt-location__label"><?php esc_html_e( 'Event location:', 'kdna-events-pdf-tickets' ); ?></span>
								<span class="tkt-location__value"><?php echo esc_html( $event_location ); ?></span>
							</div>
						<?php endif; ?>

						<table class="tkt-bottom" cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td class="tkt-terms">
									<?php echo nl2br( esc_html( $event_terms ) ); ?>
								</td>
								<td class="tkt-barcode">
									<?php if ( '' !== $barcode ) : ?>
										<img src="<?php echo esc_attr( $barcode ); ?>" alt="<?php echo esc_attr( $code ); ?>" />
										<?php if ( ! empty( $design['barcode_show_text'] ) ) : ?>
											<span class="tkt-barcode__code"><?php echo esc_html( $code ); ?></span>
										<?php endif; ?>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</td>
					<td class="tkt-card__image" style="<?php echo '' !== $header_image_url ? 'background-image:url(' . esc_url( $header_image_url ) . ');' : 'background:#EFEFEF;'; ?>">
						<span class="tkt-card__image-spacer">&nbsp;</span>
					</td>
				</tr>
			</table>

		</div>
	<?php endforeach; ?>

	<!-- Shared footer, rendered fixed on every page -->
	<div class="tkt-footer">
		<?php if ( '' !== $biz || '' !== $tax_id ) : ?>
			<p>
				<?php if ( '' !== $biz ) : ?><strong><?php echo esc_html( $biz ); ?></strong><?php endif; ?>
				<?php if ( '' !== $biz && '' !== $tax_id ) : ?> &nbsp;|&nbsp; <?php endif; ?>
				<?php if ( '' !== $tax_id ) : ?><strong><?php esc_html_e( 'ABN:', 'kdna-events-pdf-tickets' ); ?></strong> <?php echo esc_html( $tax_id ); ?><?php endif; ?>
			</p>
		<?php endif; ?>
		<?php if ( '' !== $address ) : ?>
			<p><?php echo esc_html( $address ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $phone || '' !== $support || '' !== $website ) : ?>
			<p>
				<?php if ( '' !== $phone ) : ?><strong>T:</strong> <?php echo esc_html( $phone ); ?><?php endif; ?>
				<?php if ( '' !== $phone && ( '' !== $support || '' !== $website ) ) : ?> &nbsp;|&nbsp; <?php endif; ?>
				<?php if ( '' !== $support ) : ?><strong>E:</strong> <?php echo esc_html( $support ); ?><?php endif; ?>
				<?php if ( '' !== $support && '' !== $website ) : ?> &nbsp;|&nbsp; <?php endif; ?>
				<?php if ( '' !== $website ) : ?><strong>W:</strong> <?php echo esc_html( $website ); ?><?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
</body>
</html>
