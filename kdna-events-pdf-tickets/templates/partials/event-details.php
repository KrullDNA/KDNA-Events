<?php
/**
 * Event details block: title, subtitle, date + time, location,
 * organiser.
 *
 * Expects: $data.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title    = (string) $data['event_title'];
$subtitle = (string) $data['event_subtitle'];
$date     = (string) $data['event_date'];
$time     = (string) $data['event_time'];
$type     = (string) $data['event_type'];
$location = (string) $data['event_location'];
$virtual  = (string) $data['virtual_url'];
$org      = (array) $data['organiser'];
?>
<div class="tkt-details">
	<h1 class="tkt-details__title" style="color:<?php echo esc_attr( (string) $data['design']['heading_color'] ); ?>;">
		<?php echo esc_html( $title ); ?>
	</h1>
	<?php if ( '' !== $subtitle ) : ?>
		<p class="tkt-details__subtitle"><?php echo esc_html( $subtitle ); ?></p>
	<?php endif; ?>
	<table class="tkt-details__meta">
		<?php if ( '' !== $date ) : ?>
			<tr><td class="label"><?php esc_html_e( 'Date', 'kdna-events-pdf-tickets' ); ?></td><td class="value"><?php echo esc_html( trim( $date . ' ' . $time ) ); ?></td></tr>
		<?php endif; ?>
		<?php if ( '' !== $location ) : ?>
			<tr><td class="label"><?php esc_html_e( 'Location', 'kdna-events-pdf-tickets' ); ?></td><td class="value"><?php echo esc_html( $location ); ?></td></tr>
		<?php endif; ?>
		<?php if ( in_array( $type, array( 'virtual', 'hybrid' ), true ) && '' !== $virtual ) : ?>
			<tr><td class="label"><?php esc_html_e( 'Join link', 'kdna-events-pdf-tickets' ); ?></td><td class="value" style="word-break:break-all;"><?php echo esc_html( $virtual ); ?></td></tr>
		<?php endif; ?>
		<?php if ( ! empty( $org['name'] ) ) : ?>
			<tr><td class="label"><?php esc_html_e( 'Organiser', 'kdna-events-pdf-tickets' ); ?></td><td class="value"><?php echo esc_html( (string) $org['name'] ); ?></td></tr>
		<?php endif; ?>
	</table>
</div>
