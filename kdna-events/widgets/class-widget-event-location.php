<?php
/**
 * Event Location widget.
 *
 * Renders the event venue name, address and an embedded Google Map for
 * in-person and hybrid events. Virtual events hide the map entirely
 * and instead surface a configurable virtual link button. Hybrid events
 * show both the map and the virtual link.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KDNA_Events_Widget_Base' ) ) {
	return;
}

/**
 * Elementor widget rendering the event location block.
 */
class KDNA_Events_Widget_Event_Location extends KDNA_Events_Widget_Base {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-location';
	}

	/**
	 * Visible title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Location', 'kdna-events' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-google-maps';
	}

	/**
	 * Extra scripts this widget depends on.
	 *
	 * Adds the Google Maps init script on top of the base class's
	 * shared frontend stylesheet.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'kdna-events-maps' );
	}

	/**
	 * Return the built-in map style presets list used by controls.
	 *
	 * Keys must match the preset names the JS init script understands
	 * via the data-style-preset attribute.
	 *
	 * @return array<string,string>
	 */
	protected function map_style_options() {
		return array(
			'default'   => __( 'Default', 'kdna-events' ),
			'silver'    => __( 'Silver', 'kdna-events' ),
			'retro'     => __( 'Retro', 'kdna-events' ),
			'dark'      => __( 'Dark', 'kdna-events' ),
			'night'     => __( 'Night', 'kdna-events' ),
			'aubergine' => __( 'Aubergine', 'kdna-events' ),
		);
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {

		$this->start_controls_section(
			'section_location',
			array(
				'label' => __( 'Location', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_name',
			array(
				'label'        => __( 'Show location name', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_address',
			array(
				'label'        => __( 'Show address', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'link_address_to_maps',
			array(
				'label'        => __( 'Link address to Google Maps', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Opens the Maps search for the address in a new tab.', 'kdna-events' ),
				'condition'    => array( 'show_address' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_map',
			array(
				'label' => __( 'Map', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_map',
			array(
				'label'        => __( 'Show map', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Virtual-only events never render the map.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'map_zoom',
			array(
				'label'     => __( 'Zoom', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min' => 1,
						'max' => 20,
					),
				),
				'default'   => array(
					'size' => 15,
				),
				'condition' => array( 'show_map' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'map_height',
			array(
				'label'      => __( 'Height', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range'      => array(
					'px' => array(
						'min' => 120,
						'max' => 1000,
					),
					'vh' => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 400,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-location__map' => 'height: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'show_map' => 'yes' ),
			)
		);

		$this->add_control(
			'marker_icon',
			array(
				'label'       => __( 'Marker icon', 'kdna-events' ),
				'type'        => \Elementor\Controls_Manager::MEDIA,
				'description' => __( 'Optional. Falls back to the default Google pin.', 'kdna-events' ),
				'condition'   => array( 'show_map' => 'yes' ),
			)
		);

		$this->add_control(
			'map_style',
			array(
				'label'     => __( 'Map style', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'default',
				'options'   => $this->map_style_options(),
				'condition' => array( 'show_map' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_virtual',
			array(
				'label' => __( 'Virtual Link', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_virtual_link',
			array(
				'label'        => __( 'Show virtual link', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Only rendered for virtual or hybrid events that have a virtual URL set.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'virtual_link_label',
			array(
				'label'     => __( 'Label', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Join virtual event', 'kdna-events' ),
				'condition' => array( 'show_virtual_link' => 'yes' ),
			)
		);

		$this->add_control(
			'virtual_link_use_button',
			array(
				'label'        => __( 'Render as button', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'show_virtual_link' => 'yes' ),
			)
		);

		$this->add_control(
			'virtual_link_icon',
			array(
				'label'     => __( 'Icon', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array(
					'value'   => 'eicon-external-link-square',
					'library' => 'eicons',
				),
				'condition' => array( 'show_virtual_link' => 'yes' ),
			)
		);

		$this->end_controls_section();

		// Style tabs.
		$this->start_controls_section(
			'section_name_style',
			array(
				'label'     => __( 'Location Name', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_name' => 'yes' ),
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-location__name' => 'color: {{VALUE}};',
				),
			)
		);

		$this->register_typography_control( 'name_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-event-location__name' );

		$this->add_responsive_control(
			'name_margin',
			array(
				'label'      => __( 'Margin', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-location__name' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_address_style',
			array(
				'label'     => __( 'Address', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_address' => 'yes' ),
			)
		);

		$this->add_control(
			'address_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-location__address, {{WRAPPER}} .kdna-events-event-location__address-link' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'address_hover_color',
			array(
				'label'     => __( 'Link hover colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-location__address-link:hover' => 'color: {{VALUE}};',
				),
				'condition' => array( 'link_address_to_maps' => 'yes' ),
			)
		);

		$this->register_typography_control( 'address_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-event-location__address' );

		$this->add_responsive_control(
			'address_margin',
			array(
				'label'      => __( 'Margin', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-location__address' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_map_style',
			array(
				'label'     => __( 'Map', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_map' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'map_border',
				'selector' => '{{WRAPPER}} .kdna-events-event-location__map',
			)
		);

		$this->add_control(
			'map_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-location__map' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'map_box_shadow',
				'selector' => '{{WRAPPER}} .kdna-events-event-location__map',
			)
		);

		$this->add_responsive_control(
			'map_margin',
			array(
				'label'      => __( 'Margin', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-location__map' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->register_virtual_button_style_controls();
	}

	/**
	 * Register the full control surface for the virtual link button.
	 *
	 * Kept in its own method because Section 2 requires the complete
	 * Buttons treatment: typography, text colour, background, border,
	 * border radius, padding, icon support, icon spacing, hover
	 * counterparts and transition duration.
	 *
	 * @return void
	 */
	protected function register_virtual_button_style_controls() {
		$this->start_controls_section(
			'section_virtual_style',
			array(
				'label'     => __( 'Virtual Link Button', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_virtual_link' => 'yes' ),
			)
		);

		$this->register_typography_control( 'virtual_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-event-location__virtual-link' );

		$this->add_responsive_control(
			'virtual_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-location__virtual-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'virtual_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-location__virtual-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'virtual_icon_size',
			array(
				'label'      => __( 'Icon size', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 8,
						'max' => 48,
					),
					'em' => array(
						'min'  => 0.5,
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'em',
					'size' => 1,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-location__virtual-link i, {{WRAPPER}} .kdna-events-event-location__virtual-link svg' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'virtual_icon_spacing',
			array(
				'label'      => __( 'Icon spacing', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 32,
					),
					'em' => array(
						'min'  => 0,
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'em',
					'size' => 0.5,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-location__virtual-link' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->register_hover_transition_control( 'virtual_transition', '.kdna-events-event-location__virtual-link', 200 );

		$this->start_controls_tabs( 'virtual_state_tabs' );

		$this->start_controls_tab(
			'virtual_tab_normal',
			array( 'label' => __( 'Normal', 'kdna-events' ) )
		);

		$this->add_control(
			'virtual_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-location__virtual-link' => 'color: {{VALUE}};',
					'{{WRAPPER}} .kdna-events-event-location__virtual-link i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .kdna-events-event-location__virtual-link svg' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'virtual_background',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-location__virtual-link' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'virtual_border',
				'selector' => '{{WRAPPER}} .kdna-events-event-location__virtual-link',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'virtual_tab_hover',
			array( 'label' => __( 'Hover', 'kdna-events' ) )
		);

		$this->add_control(
			'virtual_color_hover',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-location__virtual-link:hover' => 'color: {{VALUE}};',
					'{{WRAPPER}} .kdna-events-event-location__virtual-link:hover i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .kdna-events-event-location__virtual-link:hover svg' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'virtual_background_hover',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-location__virtual-link:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'virtual_border_hover_color',
			array(
				'label'     => __( 'Border colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-location__virtual-link:hover' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		$event_id = $this->get_event_id();
		if ( ! $event_id ) {
			$this->render_editor_placeholder( __( 'Create an event to preview the location.', 'kdna-events' ) );
			return;
		}

		$settings = $this->get_settings_for_display();
		$type     = (string) get_post_meta( $event_id, '_kdna_event_type', true );
		if ( '' === $type ) {
			$type = 'in-person';
		}

		$location     = kdna_events_get_event_location( $event_id );
		$virtual_url  = (string) get_post_meta( $event_id, '_kdna_event_virtual_url', true );

		$has_location = '' !== $location['name'] || '' !== $location['address'] || 0.0 !== (float) $location['lat'] || 0.0 !== (float) $location['lng'];
		$has_virtual  = '' !== $virtual_url && ( 'virtual' === $type || 'hybrid' === $type );

		if ( ! $has_location && ! $has_virtual ) {
			$this->render_editor_placeholder( __( 'No location set for this event.', 'kdna-events' ) );
			return;
		}

		$show_name    = 'yes' === ( $settings['show_name'] ?? 'yes' ) && '' !== $location['name'];
		$show_address = 'yes' === ( $settings['show_address'] ?? 'yes' ) && '' !== $location['address'];
		$link_address = 'yes' === ( $settings['link_address_to_maps'] ?? 'yes' );

		// Map only renders for in-person and hybrid, and only when coordinates exist.
		$show_map = 'yes' === ( $settings['show_map'] ?? 'yes' )
			&& 'virtual' !== $type
			&& ( 0.0 !== (float) $location['lat'] || 0.0 !== (float) $location['lng'] );

		$show_virtual = 'yes' === ( $settings['show_virtual_link'] ?? 'yes' ) && $has_virtual;
		?>
		<div class="kdna-events-event-location kdna-events-event-location--<?php echo esc_attr( $type ); ?>">

			<?php if ( $show_name ) : ?>
				<div class="kdna-events-event-location__name"><?php echo esc_html( $location['name'] ); ?></div>
			<?php endif; ?>

			<?php if ( $show_address ) : ?>
				<div class="kdna-events-event-location__address">
					<?php if ( $link_address ) : ?>
						<a class="kdna-events-event-location__address-link" href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $location['address'] ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $location['address'] ); ?>
						</a>
					<?php else : ?>
						<?php echo esc_html( $location['address'] ); ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $show_map ) : ?>
				<?php $this->render_map( $settings, $location ); ?>
			<?php endif; ?>

			<?php if ( $show_virtual ) : ?>
				<?php $this->render_virtual_link( $settings, $virtual_url ); ?>
			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Render the map container, or an admin placeholder when the API key is missing.
	 *
	 * Visitors see no map when the API key is absent. Users who can
	 * manage the plugin see a styled placeholder prompting them to
	 * add the key on the Settings screen.
	 *
	 * @param array $settings Widget settings.
	 * @param array $location Parsed location array.
	 * @return void
	 */
	protected function render_map( $settings, $location ) {
		$api_key = (string) get_option( 'kdna_events_google_maps_api_key', '' );

		if ( '' === $api_key ) {
			if ( current_user_can( 'manage_options' ) ) {
				$settings_url = admin_url( 'admin.php?page=kdna-events-settings&tab=maps' );
				?>
				<div class="kdna-events-event-location__map kdna-events-event-location__map--placeholder" role="status">
					<div class="kdna-events-event-location__map-placeholder-content">
						<strong><?php esc_html_e( 'Google Maps API key missing', 'kdna-events' ); ?></strong>
						<span>
							<?php
							printf(
								/* translators: %s: link to settings page */
								esc_html__( 'Add a key on the %s to render the map. This notice is visible only to admins.', 'kdna-events' ),
								'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Google Maps settings tab', 'kdna-events' ) . '</a>'
							);
							?>
						</span>
					</div>
				</div>
				<?php
			}
			return;
		}

		$zoom         = isset( $settings['map_zoom']['size'] ) ? (int) $settings['map_zoom']['size'] : 15;
		$style_preset = isset( $settings['map_style'] ) ? sanitize_key( (string) $settings['map_style'] ) : 'default';
		$marker_icon  = isset( $settings['marker_icon']['url'] ) ? (string) $settings['marker_icon']['url'] : '';
		?>
		<div
			class="kdna-events-event-location__map"
			data-kdna-events-map="1"
			data-lat="<?php echo esc_attr( (string) (float) $location['lat'] ); ?>"
			data-lng="<?php echo esc_attr( (string) (float) $location['lng'] ); ?>"
			data-zoom="<?php echo esc_attr( (string) $zoom ); ?>"
			data-style-preset="<?php echo esc_attr( $style_preset ); ?>"
			data-marker-icon="<?php echo esc_attr( $marker_icon ); ?>"
		></div>
		<?php
	}

	/**
	 * Render the virtual link button or plain link.
	 *
	 * @param array  $settings    Widget settings.
	 * @param string $virtual_url Target URL.
	 * @return void
	 */
	protected function render_virtual_link( $settings, $virtual_url ) {
		$label     = (string) ( $settings['virtual_link_label'] ?? __( 'Join virtual event', 'kdna-events' ) );
		$as_button = 'yes' === ( $settings['virtual_link_use_button'] ?? 'yes' );
		$icon      = $settings['virtual_link_icon'] ?? null;

		$classes = array( 'kdna-events-event-location__virtual-link' );
		if ( $as_button ) {
			$classes[] = 'kdna-events-event-location__virtual-link--button';
		} else {
			$classes[] = 'kdna-events-event-location__virtual-link--plain';
		}
		?>
		<a
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			href="<?php echo esc_url( $virtual_url ); ?>"
			target="_blank"
			rel="noopener noreferrer"
		>
			<?php if ( is_array( $icon ) && ! empty( $icon['value'] ) ) : ?>
				<span class="kdna-events-event-location__virtual-icon" aria-hidden="true">
					<?php \Elementor\Icons_Manager::render_icon( $icon ); ?>
				</span>
			<?php endif; ?>
			<span class="kdna-events-event-location__virtual-label"><?php echo esc_html( $label ); ?></span>
		</a>
		<?php
	}
}
