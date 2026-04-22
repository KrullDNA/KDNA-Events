<?php
/**
 * Admin / organiser notification email template.
 *
 * Leaner than the customer email. The logo still sits on the grey
 * page background above the white card so branding stays consistent,
 * but inside the card we show a compact summary table, an attendee
 * table and a short footer, no hero image or decorative heading.
 *
 * Variables set up by
 * KDNA_Events_Emails::render_admin_notification_html().
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
$compact       = ! empty( $compact );
$pad_y         = (int) ( $design['kdna_events_email_content_padding_y'] ?? 32 );
if ( $compact ) {
	$pad_y = (int) round( $pad_y * 0.75 );
}
$pad_x         = (int) ( $design['kdna_events_email_content_padding_x'] ?? 28 );
$divider       = (string) ( $design['kdna_events_email_color_divider'] ?? '#E5E5E5' );
$row_tint      = kdna_events_mix_hex( $divider, 0.35, $card_bg );
$primary       = (string) ( $design['kdna_events_email_color_primary'] ?? '#2E75B6' );

include __DIR__ . '/partials/doctype-head.php';
?>
<body class="kdna-events-email-body" style="margin:0;padding:0;background-color:<?php echo esc_attr( $page_bg ); ?>;">
	<?php include __DIR__ . '/partials/preheader.php'; ?>
	<table role="presentation" class="kdna-events-email-wrapper" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="<?php echo esc_attr( $page_bg ); ?>" style="width:100%;background-color:<?php echo esc_attr( $page_bg ); ?>;">
		<tr>
			<td align="center" style="padding:0;">
				<table role="presentation" width="<?php echo esc_attr( (string) $content_max ); ?>" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:<?php echo esc_attr( (string) $content_max ); ?>px;margin:0 auto;">
					<?php include __DIR__ . '/partials/logo.php'; ?>
					<tr>
						<td style="padding:0 12px 0;">
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="<?php echo esc_attr( $card_bg ); ?>" style="background-color:<?php echo esc_attr( $card_bg ); ?>;border-radius:<?php echo esc_attr( (string) $card_radius ); ?>px;overflow:hidden;">
								<tr>
									<td style="padding:<?php echo esc_attr( (string) $pad_y ); ?>px <?php echo esc_attr( (string) $pad_x ); ?>px;">
										<h1 style="margin:0 0 8px;font-family:<?php echo esc_attr( $heading_stack ); ?>;font-size:22px;line-height:1.3;font-weight:700;color:<?php echo esc_attr( $heading_color ); ?>;">
											<?php echo esc_html( (string) $admin_heading ); ?>
										</h1>
										<p style="margin:0 0 18px;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:15px;line-height:1.55;color:<?php echo esc_attr( $body_color ); ?>;">
											<?php echo esc_html( (string) $admin_intro ); ?>
										</p>

										<?php if ( ! empty( $summary_rows ) ) : ?>
											<h2 style="margin:18px 0 8px;font-family:<?php echo esc_attr( $heading_stack ); ?>;font-size:16px;font-weight:600;color:<?php echo esc_attr( $heading_color ); ?>;">
												<?php echo esc_html( (string) ( $summary_heading ?? '' ) ); ?>
											</h2>
											<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="kdna-events-email-table-admin" style="width:100%;border-collapse:collapse;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:14px;color:<?php echo esc_attr( $heading_color ); ?>;">
												<?php $i = 0; foreach ( $summary_rows as $label => $value ) :
													if ( '' === (string) $value ) { continue; }
													$row_bg = ( 0 === $i % 2 ) ? $card_bg : $row_tint;
													$i++;
													?>
													<tr>
														<th scope="row" align="left" style="padding:10px 12px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $row_bg ); ?>;font-weight:600;color:<?php echo esc_attr( $body_color ); ?>;width:40%;text-align:left;vertical-align:top;"><?php echo esc_html( (string) $label ); ?></th>
														<td style="padding:10px 12px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $row_bg ); ?>;text-align:left;vertical-align:top;"><?php echo esc_html( (string) $value ); ?></td>
													</tr>
												<?php endforeach; ?>
											</table>
										<?php endif; ?>

										<?php if ( '' !== (string) ( $event_heading ?? '' ) ) : ?>
											<h2 style="margin:22px 0 8px;font-family:<?php echo esc_attr( $heading_stack ); ?>;font-size:16px;font-weight:600;color:<?php echo esc_attr( $heading_color ); ?>;">
												<?php echo esc_html( (string) $event_heading ); ?>
											</h2>
											<p style="margin:0 0 4px;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:15px;line-height:1.5;color:<?php echo esc_attr( $heading_color ); ?>;font-weight:600;">
												<?php if ( '' !== (string) $event_edit_url ) : ?>
													<a href="<?php echo esc_url( $event_edit_url ); ?>" style="color:<?php echo esc_attr( $primary ); ?>;text-decoration:none;"><?php echo esc_html( (string) ( $context['event_title'] ?? '' ) ); ?></a>
												<?php else : ?>
													<?php echo esc_html( (string) ( $context['event_title'] ?? '' ) ); ?>
												<?php endif; ?>
											</p>
											<p style="margin:0 0 4px;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:14px;color:<?php echo esc_attr( $body_color ); ?>;">
												<?php echo esc_html( trim( (string) ( $context['event_date'] ?? '' ) . ' ' . (string) ( $context['event_time'] ?? '' ) ) ); ?>
											</p>
											<?php if ( '' !== (string) ( $context['event_location'] ?? '' ) ) : ?>
												<p style="margin:0 0 4px;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:14px;color:<?php echo esc_attr( $body_color ); ?>;">
													<?php echo esc_html( (string) $context['event_location'] ); ?>
												</p>
											<?php endif; ?>
											<?php if ( '' !== (string) ( $context['organiser_name'] ?? '' ) ) : ?>
												<p style="margin:0 0 4px;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:14px;color:<?php echo esc_attr( $muted_color ); ?>;">
													<?php echo esc_html( (string) $context['organiser_name'] ); ?>
												</p>
											<?php endif; ?>
										<?php endif; ?>

										<?php if ( ! empty( $attendee_rows ) ) : ?>
											<h2 style="margin:22px 0 8px;font-family:<?php echo esc_attr( $heading_stack ); ?>;font-size:16px;font-weight:600;color:<?php echo esc_attr( $heading_color ); ?>;">
												<?php echo esc_html( (string) ( $attendees_heading ?? '' ) ); ?>
											</h2>
											<?php
											$custom_keys = array();
											foreach ( $attendee_rows as $row ) {
												if ( isset( $row['custom_fields'] ) && is_array( $row['custom_fields'] ) ) {
													foreach ( $row['custom_fields'] as $k => $v ) {
														if ( ! in_array( $k, $custom_keys, true ) ) {
															$custom_keys[] = $k;
														}
													}
												}
											}
											?>
											<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:13px;color:<?php echo esc_attr( $heading_color ); ?>;">
												<tr>
													<th align="left" style="padding:9px 10px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $primary ); ?>;color:#FFFFFF;font-weight:600;text-align:left;"><?php esc_html_e( 'Ticket code', 'kdna-events' ); ?></th>
													<th align="left" style="padding:9px 10px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $primary ); ?>;color:#FFFFFF;font-weight:600;text-align:left;"><?php esc_html_e( 'Name', 'kdna-events' ); ?></th>
													<th align="left" style="padding:9px 10px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $primary ); ?>;color:#FFFFFF;font-weight:600;text-align:left;"><?php esc_html_e( 'Email', 'kdna-events' ); ?></th>
													<th align="left" style="padding:9px 10px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $primary ); ?>;color:#FFFFFF;font-weight:600;text-align:left;"><?php esc_html_e( 'Phone', 'kdna-events' ); ?></th>
													<?php foreach ( $custom_keys as $key ) : ?>
														<th align="left" style="padding:9px 10px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $primary ); ?>;color:#FFFFFF;font-weight:600;text-align:left;"><?php echo esc_html( (string) $key ); ?></th>
													<?php endforeach; ?>
												</tr>
												<?php $a = 0; foreach ( $attendee_rows as $row ) :
													$row_bg = ( 0 === $a % 2 ) ? $card_bg : $row_tint;
													$a++;
													?>
													<tr>
														<td style="padding:9px 10px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $row_bg ); ?>;text-align:left;vertical-align:top;font-family:<?php echo esc_attr( (string) ( $design['kdna_events_email_monospace_font'] ?? 'monospace' ) ); ?>;"><?php echo esc_html( (string) ( $row['ticket_code'] ?? '' ) ); ?></td>
														<td style="padding:9px 10px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $row_bg ); ?>;text-align:left;vertical-align:top;"><?php echo esc_html( (string) ( $row['name'] ?? '' ) ); ?></td>
														<td style="padding:9px 10px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $row_bg ); ?>;text-align:left;vertical-align:top;"><?php echo esc_html( (string) ( $row['email'] ?? '' ) ); ?></td>
														<td style="padding:9px 10px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $row_bg ); ?>;text-align:left;vertical-align:top;"><?php echo esc_html( (string) ( $row['phone'] ?? '' ) ); ?></td>
														<?php foreach ( $custom_keys as $key ) :
															$value = '';
															if ( isset( $row['custom_fields'][ $key ] ) ) {
																$value = (string) $row['custom_fields'][ $key ];
															}
															?>
															<td style="padding:9px 10px;border-bottom:1px solid <?php echo esc_attr( $divider ); ?>;background:<?php echo esc_attr( $row_bg ); ?>;text-align:left;vertical-align:top;"><?php echo esc_html( $value ); ?></td>
														<?php endforeach; ?>
													</tr>
												<?php endforeach; ?>
											</table>
										<?php endif; ?>

										<?php if ( '' !== (string) ( $admin_footer_note ?? '' ) ) : ?>
											<p style="margin:22px 0 0;padding:12px 14px;background:<?php echo esc_attr( $row_tint ); ?>;border-radius:6px;font-family:<?php echo esc_attr( $body_stack ); ?>;font-size:13px;line-height:1.5;color:<?php echo esc_attr( $muted_color ); ?>;">
												<?php echo esc_html( (string) $admin_footer_note ); ?>
											</p>
										<?php endif; ?>
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
