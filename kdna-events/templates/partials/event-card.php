<?php
/**
 * Shared Event Grid card partial.
 *
 * Rendered by the Event Grid widget and the AJAX filter handler so
 * both paths produce identical markup. Expected variables:
 *   - int   $event_id      Event post ID.
 *   - array $card_settings Element toggles and card configuration.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $event_id ) || ! is_array( $card_settings ?? null ) ) {
	return;
}

$elements = isset( $card_settings['elements'] ) && is_array( $card_settings['elements'] ) ? $card_settings['elements'] : array();
$elements = wp_parse_args(
	$elements,
	array(
		'image'      => 'yes',
		'type_badge' => 'yes',
		'date'       => 'yes',
		'title'      => 'yes',
		'location'   => 'yes',
		'price'      => 'yes',
		'excerpt'    => 'yes',
		'button'     => 'yes',
	)
);

$is_on = static function ( $value ) {
	return 'yes' === $value || true === $value || 1 === $value || '1' === $value;
};

$title_tag       = isset( $card_settings['title_tag'] ) ? (string) $card_settings['title_tag'] : 'h3';
$allowed_tags    = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' );
if ( ! in_array( $title_tag, $allowed_tags, true ) ) {
	$title_tag = 'h3';
}

$excerpt_length = isset( $card_settings['excerpt_length'] ) ? (int) $card_settings['excerpt_length'] : 20;
$date_format    = isset( $card_settings['date_format'] ) && '' !== $card_settings['date_format'] ? (string) $card_settings['date_format'] : 'j M Y, g:i a';
$button_label   = isset( $card_settings['button_label'] ) && '' !== $card_settings['button_label'] ? (string) $card_settings['button_label'] : __( 'View event', 'kdna-events' );
$free_label     = isset( $card_settings['free_label'] ) && '' !== $card_settings['free_label'] ? (string) $card_settings['free_label'] : __( 'Free', 'kdna-events' );

$permalink   = get_permalink( $event_id );
$event_type  = (string) get_post_meta( $event_id, '_kdna_event_type', true );
if ( '' === $event_type ) {
	$event_type = 'in-person';
}
$type_labels = array(
	'in-person' => __( 'In-person', 'kdna-events' ),
	'virtual'   => __( 'Virtual', 'kdna-events' ),
	'hybrid'    => __( 'Hybrid', 'kdna-events' ),
);

$start_raw = (string) get_post_meta( $event_id, '_kdna_event_start', true );
$location  = kdna_events_get_event_location( $event_id );
$is_free   = kdna_events_is_free( $event_id );
?>
<article class="kdna-events-grid__card" data-event-id="<?php echo esc_attr( (string) $event_id ); ?>">

	<?php if ( $is_on( $elements['image'] ) && has_post_thumbnail( $event_id ) ) : ?>
		<a class="kdna-events-grid__card-image" href="<?php echo esc_url( $permalink ); ?>" aria-hidden="true" tabindex="-1">
			<?php
			echo get_the_post_thumbnail(
				$event_id,
				'medium_large',
				array(
					'class' => 'kdna-events-grid__card-image-img',
					'alt'   => '',
				)
			);
			?>
		</a>
	<?php endif; ?>

	<div class="kdna-events-grid__card-body">

		<?php if ( $is_on( $elements['type_badge'] ) ) : ?>
			<span class="kdna-events-event-type-badge kdna-events-event-type-badge--<?php echo esc_attr( $event_type ); ?> kdna-events-grid__card-type">
				<span class="kdna-events-event-type-badge__label">
					<?php echo esc_html( $type_labels[ $event_type ] ?? $type_labels['in-person'] ); ?>
				</span>
			</span>
		<?php endif; ?>

		<?php if ( $is_on( $elements['date'] ) && '' !== $start_raw ) : ?>
			<div class="kdna-events-grid__card-date">
				<?php echo esc_html( kdna_events_format_datetime( $start_raw, $date_format, $event_id ) ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $is_on( $elements['title'] ) ) : ?>
			<<?php echo esc_attr( $title_tag ); ?> class="kdna-events-grid__card-title">
				<a class="kdna-events-grid__card-title-link" href="<?php echo esc_url( $permalink ); ?>">
					<?php echo esc_html( get_the_title( $event_id ) ); ?>
				</a>
			</<?php echo esc_attr( $title_tag ); ?>>
		<?php endif; ?>

		<?php if ( $is_on( $elements['location'] ) && ( '' !== $location['name'] || '' !== $location['address'] ) ) : ?>
			<div class="kdna-events-grid__card-location">
				<?php
				$location_bits = array_filter(
					array( $location['name'], $location['address'] ),
					static function ( $part ) {
						return '' !== $part;
					}
				);
				echo esc_html( implode( ', ', $location_bits ) );
				?>
			</div>
		<?php endif; ?>

		<?php if ( $is_on( $elements['price'] ) ) : ?>
			<div class="kdna-events-grid__card-price">
				<?php if ( $is_free ) : ?>
					<span class="kdna-events-grid__card-price-free"><?php echo esc_html( $free_label ); ?></span>
				<?php else :
					$price    = (float) get_post_meta( $event_id, '_kdna_event_price', true );
					$currency = (string) get_post_meta( $event_id, '_kdna_event_currency', true );
					if ( '' === $currency ) {
						$currency = (string) get_option( 'kdna_events_default_currency', 'AUD' );
					}
					?>
					<?php echo esc_html( kdna_events_format_price( $price, $currency ) ); ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $is_on( $elements['excerpt'] ) ) :
			$excerpt_text = get_the_excerpt( $event_id );
			if ( '' === $excerpt_text ) {
				$excerpt_text = wp_strip_all_tags( (string) get_post_field( 'post_content', $event_id ) );
			}
			$excerpt_text = wp_trim_words( $excerpt_text, max( 1, $excerpt_length ), '...' );
			if ( '' !== $excerpt_text ) : ?>
				<p class="kdna-events-grid__card-excerpt"><?php echo esc_html( $excerpt_text ); ?></p>
			<?php endif;
		endif; ?>

		<?php if ( $is_on( $elements['button'] ) ) : ?>
			<a class="kdna-events-grid__card-button" href="<?php echo esc_url( $permalink ); ?>">
				<?php echo esc_html( $button_label ); ?>
			</a>
		<?php endif; ?>

	</div>
</article>
