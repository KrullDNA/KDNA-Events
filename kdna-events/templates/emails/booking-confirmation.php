<?php
/**
 * Booking confirmation email template.
 *
 * Variables provided by KDNA_Events_Emails::load_template:
 *
 * @var object             $order
 * @var array<int,object>  $tickets
 * @var array<string,mixed> $context
 * @var string             $body_html
 * @var string             $role          'purchaser' | 'attendee' | 'admin'
 * @var string             $site_name
 * @var string             $site_url
 * @var string             $support_email
 * @var string             $total_display
 * @var string             $currency
 *
 * All CSS is inline so mail clients that strip <style> still render the layout.
 * No external images or fonts; system font stack only.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo esc_html( $context['event_title'] ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
	<tr>
		<td align="center" style="padding:24px;">
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
				<tr>
					<td style="padding:28px 28px 16px 28px;background-color:#1d4ed8;color:#ffffff;">
						<p style="margin:0;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.85;">
							<?php esc_html_e( 'Booking confirmed', 'kdna-events' ); ?>
						</p>
						<h1 style="margin:8px 0 0 0;font-size:24px;line-height:1.25;">
							<?php echo esc_html( $context['event_title'] ); ?>
						</h1>
						<p style="margin:8px 0 0 0;font-size:14px;opacity:0.9;">
							<?php
							printf(
								/* translators: %s: order reference */
								esc_html__( 'Reference: %s', 'kdna-events' ),
								'<strong>' . esc_html( $context['order_ref'] ) . '</strong>'
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<td style="padding:28px;">
						<p style="margin:0 0 16px 0;font-size:16px;line-height:1.5;">
							<?php
							printf(
								/* translators: %s: attendee or purchaser name */
								esc_html__( 'Hi %s,', 'kdna-events' ),
								esc_html( $context['attendee_name'] )
							);
							?>
						</p>
						<div style="margin:0 0 20px 0;font-size:15px;line-height:1.6;color:#374151;">
							<?php
							// Body is pre-merged and pre-escaped by the emails class.
							echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>

						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px 0;border:1px solid #e5e7eb;border-radius:8px;border-collapse:separate;">
							<tr>
								<td style="padding:12px 16px;border-bottom:1px solid #e5e7eb;font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">
									<?php esc_html_e( 'Event details', 'kdna-events' ); ?>
								</td>
							</tr>
							<tr>
								<td style="padding:12px 16px;">
									<p style="margin:0 0 4px 0;font-weight:600;"><?php esc_html_e( 'When', 'kdna-events' ); ?></p>
									<p style="margin:0 0 12px 0;color:#374151;">
										<?php echo esc_html( '' !== $context['event_date'] ? $context['event_date'] : __( 'TBA', 'kdna-events' ) ); ?>
									</p>
									<?php if ( '' !== $context['event_location'] ) : ?>
										<p style="margin:0 0 4px 0;font-weight:600;"><?php esc_html_e( 'Where', 'kdna-events' ); ?></p>
										<p style="margin:0 0 12px 0;color:#374151;">
											<?php echo esc_html( $context['event_location'] ); ?>
										</p>
									<?php endif; ?>
									<?php if ( '' !== $context['organiser_name'] ) : ?>
										<p style="margin:0 0 4px 0;font-weight:600;"><?php esc_html_e( 'Organiser', 'kdna-events' ); ?></p>
										<p style="margin:0;color:#374151;"><?php echo esc_html( $context['organiser_name'] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						</table>

						<p style="margin:0 0 12px 0;font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">
							<?php echo esc_html( _n( 'Your ticket', 'Your tickets', count( $tickets ), 'kdna-events' ) ); ?>
						</p>

						<?php foreach ( $tickets as $ticket ) : ?>
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 10px 0;border:1px solid #e5e7eb;border-radius:8px;border-collapse:separate;">
								<tr>
									<td style="padding:14px 16px;">
										<p style="margin:0 0 6px 0;font-weight:600;">
											<?php echo esc_html( (string) $ticket->attendee_name ); ?>
										</p>
										<p style="margin:0;">
											<span style="display:inline-block;padding:6px 10px;background-color:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:14px;letter-spacing:0.08em;color:#111827;">
												<?php echo esc_html( (string) $ticket->ticket_code ); ?>
											</span>
										</p>
									</td>
								</tr>
							</table>
						<?php endforeach; ?>

						<?php if ( 'admin' !== $role ) : ?>
							<p style="margin:20px 0 0 0;font-size:14px;color:#6b7280;line-height:1.5;">
								<?php
								printf(
									/* translators: %s: support email */
									esc_html__( 'Need help? Reply to this email or contact us at %s.', 'kdna-events' ),
									esc_html( $support_email )
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<td style="padding:20px 28px;background-color:#f9fafb;color:#6b7280;font-size:12px;text-align:center;">
						<?php
						printf(
							/* translators: 1: year, 2: site name */
							esc_html__( '&copy; %1$s %2$s. All rights reserved.', 'kdna-events' ),
							esc_html( (string) current_time( 'Y' ) ),
							esc_html( $site_name )
						);
						?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
