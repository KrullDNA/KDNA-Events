<?php
/**
 * Admin + organiser notification email.
 *
 * Variables provided by KDNA_Events_Emails::load_template:
 *
 * @var object             $order
 * @var array<int,object>  $tickets
 * @var array<string,mixed> $context
 * @var string             $role
 * @var string             $site_name
 * @var string             $site_url
 * @var string             $total_display
 * @var string             $currency
 *
 * Compact, table-style summary sized for an email preview pane.
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
			<table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;background-color:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
				<tr>
					<td style="padding:18px 22px;background-color:#111827;color:#ffffff;font-size:14px;letter-spacing:0.05em;text-transform:uppercase;">
						<?php esc_html_e( 'New booking received', 'kdna-events' ); ?>
					</td>
				</tr>
				<tr>
					<td style="padding:22px;">
						<h2 style="margin:0 0 10px 0;font-size:18px;color:#111827;">
							<?php echo esc_html( $context['event_title'] ); ?>
						</h2>
						<p style="margin:0 0 14px 0;font-size:14px;color:#4b5563;">
							<?php echo esc_html( '' !== $context['event_date'] ? $context['event_date'] : __( 'Date TBA', 'kdna-events' ) ); ?>
						</p>

						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;border-collapse:collapse;">
							<tr>
								<td style="padding:6px 0;width:40%;font-size:13px;color:#6b7280;"><?php esc_html_e( 'Reference', 'kdna-events' ); ?></td>
								<td style="padding:6px 0;font-size:14px;color:#111827;font-weight:600;"><?php echo esc_html( $context['order_ref'] ); ?></td>
							</tr>
							<tr>
								<td style="padding:6px 0;width:40%;font-size:13px;color:#6b7280;"><?php esc_html_e( 'Purchaser', 'kdna-events' ); ?></td>
								<td style="padding:6px 0;font-size:14px;color:#111827;">
									<?php echo esc_html( (string) $order->purchaser_name ); ?>
									&middot;
									<a href="mailto:<?php echo esc_attr( (string) $order->purchaser_email ); ?>" style="color:#1d4ed8;text-decoration:none;">
										<?php echo esc_html( (string) $order->purchaser_email ); ?>
									</a>
								</td>
							</tr>
							<?php if ( ! empty( $order->purchaser_phone ) ) : ?>
								<tr>
									<td style="padding:6px 0;width:40%;font-size:13px;color:#6b7280;"><?php esc_html_e( 'Phone', 'kdna-events' ); ?></td>
									<td style="padding:6px 0;font-size:14px;color:#111827;"><?php echo esc_html( (string) $order->purchaser_phone ); ?></td>
								</tr>
							<?php endif; ?>
							<tr>
								<td style="padding:6px 0;width:40%;font-size:13px;color:#6b7280;"><?php esc_html_e( 'Quantity', 'kdna-events' ); ?></td>
								<td style="padding:6px 0;font-size:14px;color:#111827;"><?php echo esc_html( (string) (int) $order->quantity ); ?></td>
							</tr>
							<tr>
								<td style="padding:6px 0;width:40%;font-size:13px;color:#6b7280;"><?php esc_html_e( 'Total', 'kdna-events' ); ?></td>
								<td style="padding:6px 0;font-size:14px;color:#111827;font-weight:600;"><?php echo esc_html( $total_display ); ?></td>
							</tr>
							<tr>
								<td style="padding:6px 0;width:40%;font-size:13px;color:#6b7280;"><?php esc_html_e( 'Status', 'kdna-events' ); ?></td>
								<td style="padding:6px 0;font-size:14px;color:#111827;"><?php echo esc_html( ucfirst( (string) $order->status ) ); ?></td>
							</tr>
						</table>

						<?php if ( ! empty( $tickets ) ) : ?>
							<p style="margin:14px 0 6px 0;font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">
								<?php esc_html_e( 'Attendees', 'kdna-events' ); ?>
							</p>
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:6px;">
								<?php foreach ( $tickets as $ticket ) : ?>
									<tr>
										<td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;font-size:13px;color:#111827;">
											<?php echo esc_html( (string) $ticket->attendee_name ); ?>
											<?php if ( ! empty( $ticket->attendee_email ) ) : ?>
												<span style="color:#6b7280;">&middot; <?php echo esc_html( (string) $ticket->attendee_email ); ?></span>
											<?php endif; ?>
										</td>
										<td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;text-align:right;">
											<span style="display:inline-block;padding:3px 8px;background-color:#f3f4f6;border:1px solid #e5e7eb;border-radius:4px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;letter-spacing:0.04em;color:#111827;">
												<?php echo esc_html( (string) $ticket->ticket_code ); ?>
											</span>
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding:14px 22px;background-color:#f9fafb;color:#6b7280;font-size:12px;">
						<?php
						printf(
							/* translators: %s: site name */
							esc_html__( 'Sent by %s', 'kdna-events' ),
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
