<?php
/**
 * Event Grid query + rendering controller.
 *
 * Shared by the Event Grid widget and the AJAX filter endpoint so both
 * produce identical markup via the templates/partials/event-card.php
 * partial. Owns nonce verification and parameter sanitisation.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grid query + card rendering.
 */
class KDNA_Events_Grid {

	/**
	 * AJAX action name used by the filter widget.
	 */
	const AJAX_ACTION = 'kdna_events_filter_grid';

	/**
	 * Nonce handle shared with wp_localize_script.
	 */
	const NONCE_ACTION = 'kdna_events_frontend';

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_filter' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_filter' ) );
	}

	/**
	 * Sanitise and normalise filter parameters from a request.
	 *
	 * Accepts a raw array (from $_POST or $_GET) and returns a clean
	 * set of values safe to pass into build_query_args.
	 *
	 * @param array $input Raw filter input.
	 * @return array
	 */
	public static function sanitize_filters( $input ) {
		$input = is_array( $input ) ? $input : array();

		$types_allowed  = array( 'in-person', 'virtual', 'hybrid' );
		$prices_allowed = array( 'free', 'paid' );

		$type = isset( $input['type'] ) ? sanitize_text_field( (string) $input['type'] ) : '';
		if ( ! in_array( $type, $types_allowed, true ) ) {
			$type = '';
		}

		$price = isset( $input['price'] ) ? sanitize_text_field( (string) $input['price'] ) : '';
		if ( ! in_array( $price, $prices_allowed, true ) ) {
			$price = '';
		}

		return array(
			'type'       => $type,
			'price'      => $price,
			'category'   => isset( $input['category'] ) ? absint( $input['category'] ) : 0,
			'date_from'  => isset( $input['date_from'] ) ? sanitize_text_field( (string) $input['date_from'] ) : '',
			'date_to'    => isset( $input['date_to'] ) ? sanitize_text_field( (string) $input['date_to'] ) : '',
			'search'     => isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '',
			'page'       => max( 1, isset( $input['page'] ) ? absint( $input['page'] ) : 1 ),
		);
	}

	/**
	 * Build a WP_Query args array from grid settings and filter state.
	 *
	 * @param array $grid_settings    Grid widget settings map.
	 * @param array $filter_state     Sanitised filter state.
	 * @return array
	 */
	public static function build_query_args( $grid_settings, $filter_state ) {
		$grid_settings = is_array( $grid_settings ) ? $grid_settings : array();
		$filter_state  = self::sanitize_filters( is_array( $filter_state ) ? $filter_state : array() );

		$per_page = isset( $grid_settings['posts_per_page'] ) ? (int) $grid_settings['posts_per_page'] : 9;
		if ( $per_page < 1 ) {
			$per_page = 9;
		}

		$args = array(
			'post_type'           => 'kdna_event',
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $filter_state['page'],
			'ignore_sticky_posts' => true,
		);

		$order_by = isset( $grid_settings['order_by'] ) ? (string) $grid_settings['order_by'] : 'start';
		$order    = isset( $grid_settings['order'] ) && 'DESC' === strtoupper( (string) $grid_settings['order'] ) ? 'DESC' : 'ASC';

		switch ( $order_by ) {
			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = $order;
				break;
			case 'price':
				$args['meta_key'] = '_kdna_event_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['orderby']  = 'meta_value_num';
				$args['order']    = $order;
				break;
			case 'start':
			default:
				$args['meta_key'] = '_kdna_event_start'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['orderby']  = 'meta_value';
				$args['order']    = $order;
		}

		$meta_query = array( 'relation' => 'AND' );

		$upcoming_only      = 'yes' === ( $grid_settings['upcoming_only'] ?? 'yes' );
		$include_past       = 'yes' === ( $grid_settings['include_past'] ?? '' );

		if ( $upcoming_only && ! $include_past ) {
			$meta_query[] = array(
				'key'     => '_kdna_event_start',
				'value'   => current_time( 'Y-m-d\TH:i' ),
				'compare' => '>=',
				'type'    => 'CHAR',
			);
		}

		if ( 'free' === $filter_state['price'] ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_kdna_event_price',
					'value'   => 0,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => '_kdna_event_price',
					'compare' => 'NOT EXISTS',
				),
			);
		} elseif ( 'paid' === $filter_state['price'] ) {
			$meta_query[] = array(
				'key'     => '_kdna_event_price',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			);
		}

		if ( '' !== $filter_state['type'] ) {
			$meta_query[] = array(
				'key'   => '_kdna_event_type',
				'value' => $filter_state['type'],
			);
		}

		if ( '' !== $filter_state['date_from'] ) {
			$meta_query[] = array(
				'key'     => '_kdna_event_start',
				'value'   => $filter_state['date_from'] . 'T00:00',
				'compare' => '>=',
				'type'    => 'CHAR',
			);
		}

		if ( '' !== $filter_state['date_to'] ) {
			$meta_query[] = array(
				'key'     => '_kdna_event_start',
				'value'   => $filter_state['date_to'] . 'T23:59',
				'compare' => '<=',
				'type'    => 'CHAR',
			);
		}

		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		// Category filter. Grid default category comes from settings, filter state overrides.
		$category_id = $filter_state['category'];
		if ( ! $category_id ) {
			$category_id = isset( $grid_settings['category'] ) ? (int) $grid_settings['category'] : 0;
		}
		if ( $category_id ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'kdna_event_category',
					'field'    => 'term_id',
					'terms'    => $category_id,
				),
			);
		}

		if ( '' !== $filter_state['search'] ) {
			$args['s'] = $filter_state['search'];
		}

		return $args;
	}

	/**
	 * Render a single card using the shared partial.
	 *
	 * @param int   $event_id      Event post ID.
	 * @param array $card_settings Card element toggles and options.
	 * @return string
	 */
	public static function render_card( $event_id, $card_settings ) {
		$default = KDNA_EVENTS_PATH . 'templates/partials/event-card.php';

		/**
		 * Filter the resolved path to the shared event card partial.
		 *
		 * Themes can override the card markup by returning a path to a
		 * copy of the partial inside their theme.
		 *
		 * @param string $default Absolute default path.
		 */
		$path = (string) apply_filters( 'kdna_events_event_card_template', $default );
		if ( ! file_exists( $path ) ) {
			$path = $default;
		}

		ob_start();
		$event_id      = (int) $event_id; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$card_settings = is_array( $card_settings ) ? $card_settings : array(); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * Render a run of cards for a query.
	 *
	 * @param WP_Query $query         The executed query.
	 * @param array    $card_settings Card element toggles.
	 * @return string
	 */
	public static function render_cards_for_query( $query, $card_settings ) {
		if ( ! ( $query instanceof WP_Query ) ) {
			return '';
		}

		$out = '';
		while ( $query->have_posts() ) {
			$query->the_post();
			$out .= self::render_card( get_the_ID(), $card_settings );
		}
		wp_reset_postdata();
		return $out;
	}

	/**
	 * Render pagination markup for the grid.
	 *
	 * @param WP_Query $query           The executed query.
	 * @param array    $pagination_args Pagination settings (type, current page, labels).
	 * @return string
	 */
	public static function render_pagination( $query, $pagination_args ) {
		if ( ! ( $query instanceof WP_Query ) ) {
			return '';
		}

		$type = isset( $pagination_args['type'] ) ? (string) $pagination_args['type'] : 'numbered';
		if ( 'none' === $type || $query->max_num_pages < 2 ) {
			return '';
		}

		$current  = max( 1, (int) ( $pagination_args['current'] ?? 1 ) );
		$total    = (int) $query->max_num_pages;
		$prev_txt = (string) ( $pagination_args['prev_label'] ?? __( 'Previous', 'kdna-events' ) );
		$next_txt = (string) ( $pagination_args['next_label'] ?? __( 'Next', 'kdna-events' ) );
		$more_txt = (string) ( $pagination_args['load_more_label'] ?? __( 'Load more', 'kdna-events' ) );

		if ( 'load_more' === $type ) {
			if ( $current >= $total ) {
				return '';
			}
			return sprintf(
				'<div class="kdna-events-grid__pagination kdna-events-grid__pagination--load-more"><button type="button" class="kdna-events-grid__load-more" data-kdna-events-load-more="1" data-current-page="%1$d" data-max-pages="%2$d">%3$s</button></div>',
				(int) $current,
				(int) $total,
				esc_html( $more_txt )
			);
		}

		$html = '<nav class="kdna-events-grid__pagination kdna-events-grid__pagination--numbered" aria-label="' . esc_attr__( 'Pagination', 'kdna-events' ) . '"><ul>';
		for ( $i = 1; $i <= $total; $i++ ) {
			$is_current = $i === $current;
			$html      .= sprintf(
				'<li><button type="button" class="kdna-events-grid__page %1$s" data-kdna-events-page="%2$d"%3$s>%2$d</button></li>',
				$is_current ? 'is-current' : '',
				$i,
				$is_current ? ' aria-current="page" disabled' : ''
			);
		}
		$html .= '</ul></nav>';

		unset( $prev_txt, $next_txt );
		return $html;
	}

	/**
	 * Handle the AJAX filter request.
	 *
	 * Accepts JSON-shaped grid_settings and filters arrays, rebuilds
	 * the query, renders cards via the shared partial and returns the
	 * HTML alongside pagination metadata.
	 *
	 * @return void
	 */
	public static function ajax_filter() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$grid_settings_raw = isset( $_POST['grid_settings'] ) ? wp_unslash( $_POST['grid_settings'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$filters_raw       = isset( $_POST['filters'] ) ? wp_unslash( $_POST['filters'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$grid_settings = is_array( $grid_settings_raw ) ? $grid_settings_raw : json_decode( (string) $grid_settings_raw, true );
		$filters       = is_array( $filters_raw ) ? $filters_raw : json_decode( (string) $filters_raw, true );

		if ( ! is_array( $grid_settings ) ) {
			$grid_settings = array();
		}
		if ( ! is_array( $filters ) ) {
			$filters = array();
		}

		$grid_settings = self::sanitize_grid_settings( $grid_settings );

		$args  = self::build_query_args( $grid_settings, $filters );
		$query = new WP_Query( $args );

		$card_settings = array(
			'elements'       => isset( $grid_settings['elements'] ) ? $grid_settings['elements'] : array(),
			'title_tag'      => isset( $grid_settings['title_tag'] ) ? $grid_settings['title_tag'] : 'h3',
			'excerpt_length' => isset( $grid_settings['excerpt_length'] ) ? (int) $grid_settings['excerpt_length'] : 20,
			'date_format'    => isset( $grid_settings['date_format'] ) ? (string) $grid_settings['date_format'] : 'j M Y, g:i a',
			'button_label'   => isset( $grid_settings['button_label'] ) ? (string) $grid_settings['button_label'] : __( 'View event', 'kdna-events' ),
			'free_label'     => isset( $grid_settings['free_label'] ) ? (string) $grid_settings['free_label'] : __( 'Free', 'kdna-events' ),
		);

		$cards_html      = self::render_cards_for_query( $query, $card_settings );
		$pagination_html = self::render_pagination(
			$query,
			array(
				'type'            => isset( $grid_settings['pagination'] ) ? (string) $grid_settings['pagination'] : 'numbered',
				'current'         => isset( $filters['page'] ) ? (int) $filters['page'] : 1,
				'load_more_label' => isset( $grid_settings['load_more_label'] ) ? (string) $grid_settings['load_more_label'] : __( 'Load more', 'kdna-events' ),
			)
		);

		wp_send_json_success(
			array(
				'cards'      => $cards_html,
				'pagination' => $pagination_html,
				'found'      => (int) $query->found_posts,
				'max_pages'  => (int) $query->max_num_pages,
				'empty_html' => '' === $cards_html ? self::render_empty_state() : '',
			)
		);
	}

	/**
	 * Whitelist and sanitise grid settings accepted from AJAX.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	public static function sanitize_grid_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$clean = array(
			'posts_per_page'  => isset( $settings['posts_per_page'] ) ? max( 1, (int) $settings['posts_per_page'] ) : 9,
			'upcoming_only'   => isset( $settings['upcoming_only'] ) && 'yes' === $settings['upcoming_only'] ? 'yes' : '',
			'include_past'    => isset( $settings['include_past'] ) && 'yes' === $settings['include_past'] ? 'yes' : '',
			'category'        => isset( $settings['category'] ) ? absint( $settings['category'] ) : 0,
			'order_by'        => isset( $settings['order_by'] ) && in_array( $settings['order_by'], array( 'start', 'title', 'price' ), true ) ? $settings['order_by'] : 'start',
			'order'           => isset( $settings['order'] ) && 'DESC' === strtoupper( (string) $settings['order'] ) ? 'DESC' : 'ASC',
			'pagination'      => isset( $settings['pagination'] ) && in_array( $settings['pagination'], array( 'none', 'numbered', 'load_more' ), true ) ? $settings['pagination'] : 'numbered',
			'title_tag'       => isset( $settings['title_tag'] ) && in_array( $settings['title_tag'], array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' ), true ) ? $settings['title_tag'] : 'h3',
			'excerpt_length'  => isset( $settings['excerpt_length'] ) ? max( 1, (int) $settings['excerpt_length'] ) : 20,
			'date_format'     => isset( $settings['date_format'] ) ? sanitize_text_field( (string) $settings['date_format'] ) : 'j M Y, g:i a',
			'button_label'    => isset( $settings['button_label'] ) ? sanitize_text_field( (string) $settings['button_label'] ) : __( 'View event', 'kdna-events' ),
			'free_label'      => isset( $settings['free_label'] ) ? sanitize_text_field( (string) $settings['free_label'] ) : __( 'Free', 'kdna-events' ),
			'load_more_label' => isset( $settings['load_more_label'] ) ? sanitize_text_field( (string) $settings['load_more_label'] ) : __( 'Load more', 'kdna-events' ),
		);

		$elements_in  = isset( $settings['elements'] ) && is_array( $settings['elements'] ) ? $settings['elements'] : array();
		$element_keys = array( 'image', 'type_badge', 'date', 'title', 'location', 'price', 'excerpt', 'button' );
		$elements     = array();
		foreach ( $element_keys as $key ) {
			$elements[ $key ] = isset( $elements_in[ $key ] ) && in_array( $elements_in[ $key ], array( 'yes', true, 1, '1' ), true ) ? 'yes' : '';
		}
		$clean['elements'] = $elements;

		return $clean;
	}

	/**
	 * Render the empty-state markup shown when no events match.
	 *
	 * @return string
	 */
	public static function render_empty_state() {
		return '<div class="kdna-events-grid__empty">' . esc_html__( 'No events match these filters.', 'kdna-events' ) . '</div>';
	}
}
