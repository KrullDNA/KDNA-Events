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

// Resolve the actual font stack that each selector will use, and
// bake it into the stylesheet by substituting the {{FONT_BODY}} and
// {{FONT_HEADING}} tokens already embedded in ticket.css.
//
// Prior versions tried to override the hardcoded 'helvetica' stacks
// by appending a second set of rules with !important - Dompdf 2.x's
// !important handling is flaky when it has to defeat equal-specificity
// rules that were parsed first, so the override silently failed on
// most selectors. Baking the stack into the primary declaration means
// there is nothing to override.
$heading_face   = trim( (string) ( $design['heading_font_url'] ?? '' ) );
$body_face      = trim( (string) ( $design['body_font_url'] ?? '' ) );
$heading_family = 'KdnaPdfHeading';
$body_family    = 'KdnaPdfBody';
// Dompdf de-duplicates @font-face entries by src URL: if we register
// two different family names pointing at the same TTF, only the first
// family sticks and the second silently fails to render. When both
// slots use the same URL, emit the TTF once and point both stacks at
// the same family.
if ( '' !== $body_face && '' !== $heading_face && $body_face === $heading_face ) {
	$heading_family = $body_family;
}
$body_fallback    = (string) ( $design['body_font'] ?? 'helvetica' );
$heading_fallback = (string) ( $design['heading_font'] ?? 'helvetica' );
$body_stack    = ( '' !== $body_face ? "'" . $body_family . "', " : '' ) . $body_fallback . ', Arial, sans-serif';
$heading_stack = ( '' !== $heading_face ? "'" . $heading_family . "', " : '' ) . $heading_fallback . ', Arial, sans-serif';

// Register each TTF at both normal + bold weights. Dompdf's font
// matcher is strict: if the CSS asks for font-weight:700 but the
// @font-face only declares font-weight:normal, it falls through to
// the next family in the stack instead of reusing the TTF at a
// synthesised weight.
$face_for = static function ( $family, $url ) {
	$url_safe = esc_url_raw( $url );
	return "@font-face { font-family: '" . $family . "'; src: url('" . $url_safe . "') format('truetype'); font-weight: normal; font-style: normal; }\n"
		. "@font-face { font-family: '" . $family . "'; src: url('" . $url_safe . "') format('truetype'); font-weight: bold; font-style: normal; }\n";
};
$face_css = '';
if ( '' !== $body_face ) {
	$face_css .= $face_for( $body_family, $body_face );
}
if ( '' !== $heading_face && $heading_family !== $body_family ) {
	$face_css .= $face_for( $heading_family, $heading_face );
}

$css = $face_css
	. '@page { margin: ' . $margin . 'mm ' . $margin . 'mm ' . ( $margin + 6 ) . 'mm; }' . "\n"
	. strtr(
		$css,
		array(
			'{{FONT_BODY}}'    => $body_stack,
			'{{FONT_HEADING}}' => $heading_stack,
		)
	);

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
