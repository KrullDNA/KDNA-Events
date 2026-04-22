<?php
/**
 * Three-column row of event meta (date, time, type) with a centred
 * location row below, matching the PDF reference.
 *
 * Every value renders only if set; missing columns collapse gracefully.
 *
 * Expects:
 *   $context    Full merge-tag context with event_date, event_time,
 *               event_type, event_location.
 *   $body_stack Resolved body font stack for inline font-family.
 *   $heading_color / $muted_color / $body_color hex strings.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$label_style = 'display:block;font-family:' . $body_stack . ';font-size:14px;font-weight:400;color:' . $muted_color . ';margin-bottom:4px;mso-line-height-rule:exactly;';
$value_style = 'display:block;font-family:' . $body_stack . ';font-size:18px;font-weight:600;color:' . $heading_color . ';line-height:1.3;mso-line-height-rule:exactly;';

$cells = array(
	'event_date' => array( 'label' => __( 'Event date:', 'kdna-events' ), 'value' => (string) ( $context['event_date'] ?? '' ) ),
	'event_time' => array( 'label' => __( 'Event time:', 'kdna-events' ), 'value' => (string) ( $context['event_time'] ?? '' ) ),
	'event_type' => array( 'label' => __( 'Event type:', 'kdna-events' ), 'value' => (string) ( $context['event_type'] ?? '' ) ),
);

$cells = array_filter( $cells, static function ( $c ) { return '' !== $c['value']; } );

$location = (string) ( $context['event_location'] ?? '' );

if ( empty( $cells ) && '' === $location ) {
	return;
}
?>
<tr>
	<td style="padding:8px 0 4px;">
		<table role="presentation" class="kdna-events-email-details" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;">
			<?php if ( ! empty( $cells ) ) : ?>
				<tr>
					<?php foreach ( $cells as $cell ) : ?>
						<td align="center" style="padding:6px 6px 16px;vertical-align:top;text-align:center;">
							<span class="kdna-events-email-detail-label" style="<?php echo esc_attr( $label_style ); ?>"><?php echo esc_html( $cell['label'] ); ?></span>
							<span class="kdna-events-email-detail-value" style="<?php echo esc_attr( $value_style ); ?>"><?php echo esc_html( $cell['value'] ); ?></span>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endif; ?>
			<?php if ( '' !== $location ) : ?>
				<tr>
					<td colspan="<?php echo esc_attr( (string) max( 1, count( $cells ) ) ); ?>" align="center" style="padding:8px 12px 18px;text-align:center;">
						<span class="kdna-events-email-detail-label" style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Event location:', 'kdna-events' ); ?></span>
						<span class="kdna-events-email-detail-value" style="<?php echo esc_attr( $value_style ); ?>"><?php echo esc_html( $location ); ?></span>
					</td>
				</tr>
			<?php endif; ?>
		</table>
	</td>
</tr>
