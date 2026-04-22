<?php
/**
 * Booking confirmation email template.
 *
 * Layout matches KDNA Events Email Template.pdf exactly:
 *   - Logo sits above the card on the grey page background.
 *   - Event image is the hero at the top of the white card.
 *   - Large centred heading (per-event override with global default).
 *   - Email content 1 paragraph (merge-tag enabled).
 *   - Three-column detail row: date, time, type.
 *   - Centred location row.
 *   - Orange Virtual Event link pill, only on virtual/hybrid events
 *     with a URL set.
 *   - Email content 2 paragraph.
 *   - Footer text BELOW the card on grey background.
 *
 * Every block is conditional: if the data is missing, the block is
 * skipped cleanly so there are no orphan table rows or broken image
 * icons anywhere.
 *
 * Variables in scope are set up by
 * KDNA_Events_Emails::render_booking_confirmation_html().
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$content_max   = max( 480, min( 720, (int) ( $design['kdna_events_email_content_max_width'] ?? 600 ) ) );
$page_bg       = (string) ( $design['kdna_events_email_color_page_bg'] ?? '#EFEFEF' );
$card_bg       = (string) ( $design['kdna_events_email_color_content_bg'] ?? '#FFFFFF' );
$heading_color = (string) ( $design['kdna_events_email_color_heading'] ?? '#1A1A1A' );
$body_color    = (string) ( $design['kdna_events_email_color_body'] ?? '#555555' );
$muted_color   = (string) ( $design['kdna_events_email_color_muted'] ?? '#888888' );
$card_radius   = (int) ( $design['kdna_events_email_card_border_radius'] ?? 8 );
$pad_y         = (int) ( $design['kdna_events_email_content_padding_y'] ?? 36 );
$pad_x         = (int) ( $design['kdna_events_email_content_padding_x'] ?? 28 );

$heading_font_size   = (int) ( $design['kdna_events_email_heading_font_size'] ?? 28 );
$heading_font_weight = (int) ( $design['kdna_events_email_heading_font_weight'] ?? 700 );
$body_font_size      = (int) ( $design['kdna_events_email_body_font_size'] ?? 16 );
$body_line_height    = (string) ( $design['kdna_events_email_body_line_height'] ?? '1.55' );

$content_1_lines = preg_split( '/\r\n|\n|\r/', (string) $content_1 );
$content_2_lines = preg_split( '/\r\n|\n|\r/', (string) $content_2 );

include __DIR__ . '/partials/doctype-head.php';
?>
<body class="kdna-events-email-body" style="margin:0;padding:0;background-color:<?php echo esc_attr( $page_bg ); ?>;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
	<?php include __DIR__ . '/partials/preheader.php'; ?>
	<table role="presentation" class="kdna-events-email-wrapper" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="<?php echo esc_attr( $page_bg ); ?>" style="width:100%;background-color:<?php echo esc_attr( $page_bg ); ?>;margin:0;padding:0;">
		<tr>
			<td align="center" style="padding:0;">
				<table role="presentation" width="<?php echo esc_attr( (string) $content_max ); ?>" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:<?php echo esc_attr( (string) $content_max ); ?>px;margin:0 auto;">
					<?php include __DIR__ . '/partials/logo.php'; ?>
					<tr>
						<td style="padding:0 12px 0;">
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="kdna-events-email-card" bgcolor="<?php echo esc_attr( $card_bg ); ?>" style="background-color:<?php echo esc_attr( $card_bg ); ?>;border-radius:<?php echo esc_attr( (string) $card_radius ); ?>px;overflow:hidden;">
								<?php include __DIR__ . '/partials/event-image.php'; ?>
								<tr>
									<td class="kdna-events-email-content" align="center" style="padding:<?php echo esc_attr( (string) $pad_y ); ?>px <?php echo esc_attr( (string) $pad_x ); ?>px;text-align:center;">
										<?php if ( '' !== (string) $email_heading ) : ?>
											<h1 class="kdna-events-email-heading" style="margin:0 0 22px;font-family:<?php echo esc_attr( $heading_stack ); ?>;font-size:<?php echo esc_attr( (string) $heading_font_size ); ?>px;line-height:1.25;font-weight:<?php echo esc_attr( (string) $heading_font_weight ); ?>;color:<?php echo esc_attr( $heading_color ); ?>;text-align:center;mso-line-height-rule:exactly;">
												<?php echo esc_html( (string) $email_heading ); ?>
											</h1>
										<?php endif; ?>

										<?php foreach ( $content_1_lines as $line ) :
											$line = trim( (string) $line );
											if ( '' === $line ) { continue; }
											?>
											<p class="kdna-events-email-body-text" style="margin:0 0 12px;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:<?php echo esc_attr( (string) $body_font_size ); ?>px;line-height:<?php echo esc_attr( $body_line_height ); ?>;color:<?php echo esc_attr( $body_color ); ?>;text-align:left;mso-line-height-rule:exactly;">
												<?php echo esc_html( $line ); ?>
											</p>
										<?php endforeach; ?>

										<?php include __DIR__ . '/partials/event-details.php'; ?>

										<?php include __DIR__ . '/partials/virtual-button.php'; ?>

										<?php foreach ( $content_2_lines as $line ) :
											$line = trim( (string) $line );
											if ( '' === $line ) { continue; }
											?>
											<p class="kdna-events-email-body-text" style="margin:0 0 12px;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:<?php echo esc_attr( (string) $body_font_size ); ?>px;line-height:<?php echo esc_attr( $body_line_height ); ?>;color:<?php echo esc_attr( $body_color ); ?>;text-align:left;mso-line-height-rule:exactly;">
												<?php echo esc_html( $line ); ?>
											</p>
										<?php endforeach; ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<?php include __DIR__ . '/partials/footer.php'; ?>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
