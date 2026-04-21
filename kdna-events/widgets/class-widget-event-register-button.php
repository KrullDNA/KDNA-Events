<?php
/**
 * Event Register Button widget.
 *
 * Large CTA on the event page. Tracks the four registration states
 * (active, not yet open, closed, sold out) via kdna_events_is_registration_open
 * and exposes per-state label controls, merge tags on the not-yet-open
 * label, free-event label auto-swap, a small catalogue of size presets
 * and separate Active / Hover / Disabled style surfaces.
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
 * Elementor widget rendering the event register CTA.
 */
class KDNA_Events_Widget_Event_Register_Button extends KDNA_Events_Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-register-button';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Register Button', 'kdna-events' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-button';
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_labels',
			array(
				'label' => __( 'Labels', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'active_label',
			array(
				'label'   => __( 'Active label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Register', 'kdna-events' ),
			)
		);

		$this->add_control(
			'auto_swap_free',
			array(
				'label'        => __( 'Swap label for free events', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'free_label',
			array(
				'label'     => __( 'Free event label', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Reserve Free Spot', 'kdna-events' ),
				'condition' => array( 'auto_swap_free' => 'yes' ),
			)
		);

		$this->add_control(
			'not_yet_open_label',
			array(
				'label'       => __( 'Not yet open label', 'kdna-events' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Registration opens {date}', 'kdna-events' ),
				'description' => __( 'Merge tags: {date}, {time}, {datetime}', 'kdna-events' ),
			)
		);

		$this->add_control(
			'not_yet_open_date_format',
			array(
				'label'   => __( 'Date merge tag format', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'j M Y',
			)
		);

		$this->add_control(
			'not_yet_open_time_format',
			array(
				'label'   => __( 'Time merge tag format', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'g:i a',
			)
		);

		$this->add_control(
			'closed_label',
			array(
				'label'   => __( 'Closed label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Registration closed', 'kdna-events' ),
			)
		);

		$this->add_control(
			'sold_out_label',
			array(
				'label'   => __( 'Sold out label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Sold Out', 'kdna-events' ),
			)
		);

		$this->add_control(
			'preview_state',
			array(
				'label'       => __( 'Preview state (editor only)', 'kdna-events' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'auto',
				'options'     => array(
					'auto'         => __( 'Automatic', 'kdna-events' ),
					'active'       => __( 'Active', 'kdna-events' ),
					'not_yet_open' => __( 'Not yet open', 'kdna-events' ),
					'closed'       => __( 'Closed', 'kdna-events' ),
					'sold_out'     => __( 'Sold out', 'kdna-events' ),
				),
				'description' => __( 'Forces the button state in the editor preview. Front-end always resolves live.', 'kdna-events' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Layout', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_icon',
			array(
				'label'        => __( 'Show icon', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'icon',
			array(
				'label'     => __( 'Icon', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array(
					'value'   => 'eicon-ticket',
					'library' => 'eicons',
				),
				'condition' => array( 'show_icon' => 'yes' ),
			)
		);

		$this->add_control(
			'icon_position',
			array(
				'label'     => __( 'Icon position', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'left',
				'options'   => array(
					'left'  => __( 'Left', 'kdna-events' ),
					'right' => __( 'Right', 'kdna-events' ),
				),
				'condition' => array( 'show_icon' => 'yes' ),
			)
		);

		$this->add_control(
			'icon_spacing',
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
					'{{WRAPPER}} .kdna-events-event-register-button' => 'gap: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'show_icon' => 'yes' ),
			)
		);

		$this->add_control(
			'size_preset',
			array(
				'label'   => __( 'Size preset', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'lg',
				'options' => array(
					'sm' => __( 'Small', 'kdna-events' ),
					'md' => __( 'Medium', 'kdna-events' ),
					'lg' => __( 'Large', 'kdna-events' ),
					'xl' => __( 'Extra large', 'kdna-events' ),
				),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Left', 'kdna-events' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Centre', 'kdna-events' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'kdna-events' ),
						'icon'  => 'eicon-text-align-right',
					),
					'stretch'    => array(
						'title' => __( 'Full width', 'kdna-events' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button-wrap' => 'justify-content: {{VALUE}};',
					'{{WRAPPER}} .kdna-events-event-register-button-wrap[data-align="stretch"] .kdna-events-event-register-button' => 'width: 100%; justify-content: center;',
				),
			)
		);

		$this->end_controls_section();

		$this->register_shared_style_controls();
		$this->register_active_style_controls();
		$this->register_hover_style_controls();
		$this->register_disabled_style_controls();
	}

	/**
	 * Shared typography, size and transition controls applied to every state.
	 *
	 * @return void
	 */
	protected function register_shared_style_controls() {
		$this->start_controls_section(
			'section_style_shared',
			array(
				'label' => __( 'Shared', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->register_typography_control( 'button_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-event-register-button' );

		$this->add_control(
			'icon_size',
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
					'{{WRAPPER}} .kdna-events-event-register-button i, {{WRAPPER}} .kdna-events-event-register-button svg' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'show_icon' => 'yes' ),
			)
		);

		$this->register_hover_transition_control( 'button_transition', '.kdna-events-event-register-button', 200 );

		$this->end_controls_section();
	}

	/**
	 * Active-state style controls.
	 *
	 * @return void
	 */
	protected function register_active_style_controls() {
		$this->start_controls_section(
			'section_style_active',
			array(
				'label' => __( 'Active state', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'active_text_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-active' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'active_background',
				'label'    => __( 'Background', 'kdna-events' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-events-event-register-button.is-active',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'active_border',
				'selector' => '{{WRAPPER}} .kdna-events-event-register-button.is-active',
			)
		);

		$this->add_control(
			'active_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'      => 8,
					'right'    => 8,
					'bottom'   => 8,
					'left'     => 8,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-register-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'active_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'description' => __( 'Overrides the size preset padding when set.', 'kdna-events' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-register-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'active_box_shadow',
				'selector' => '{{WRAPPER}} .kdna-events-event-register-button.is-active',
			)
		);

		$this->add_control(
			'active_icon_color',
			array(
				'label'     => __( 'Icon colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-active i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .kdna-events-event-register-button.is-active svg' => 'fill: {{VALUE}};',
				),
				'condition' => array( 'show_icon' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Hover-state controls (active state only).
	 *
	 * @return void
	 */
	protected function register_hover_style_controls() {
		$this->start_controls_section(
			'section_style_hover',
			array(
				'label' => __( 'Hover (active only)', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'hover_text_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-active:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'hover_background',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-active:hover' => 'background-color: {{VALUE}}; background-image: none;',
				),
			)
		);

		$this->add_control(
			'hover_border_color',
			array(
				'label'     => __( 'Border colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-active:hover' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'hover_box_shadow',
				'selector' => '{{WRAPPER}} .kdna-events-event-register-button.is-active:hover',
			)
		);

		$this->add_control(
			'hover_icon_color',
			array(
				'label'     => __( 'Icon colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-active:hover i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .kdna-events-event-register-button.is-active:hover svg' => 'fill: {{VALUE}};',
				),
				'condition' => array( 'show_icon' => 'yes' ),
			)
		);

		$this->add_control(
			'hover_lift',
			array(
				'label'      => __( 'Lift (translateY)', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 16,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 2,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-active:hover' => 'transform: translateY(-{{SIZE}}{{UNIT}});',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Disabled-state controls (shared across not-yet-open, closed, sold-out).
	 *
	 * @return void
	 */
	protected function register_disabled_style_controls() {
		$this->start_controls_section(
			'section_style_disabled',
			array(
				'label' => __( 'Disabled state', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'disabled_text_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#6b7280',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-disabled' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'disabled_background',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#e5e7eb',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-disabled' => 'background-color: {{VALUE}}; background-image: none;',
				),
			)
		);

		$this->add_control(
			'disabled_border_color',
			array(
				'label'     => __( 'Border colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-disabled' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'disabled_opacity',
			array(
				'label'   => __( 'Opacity', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SLIDER,
				'range'   => array(
					'px' => array(
						'min'  => 30,
						'max'  => 100,
						'step' => 1,
					),
				),
				'default' => array(
					'size' => 90,
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-disabled' => 'opacity: calc({{SIZE}} / 100);',
				),
			)
		);

		$this->add_control(
			'disabled_icon_color',
			array(
				'label'     => __( 'Icon colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-register-button.is-disabled i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .kdna-events-event-register-button.is-disabled svg' => 'fill: {{VALUE}};',
				),
				'condition' => array( 'show_icon' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Determine the button state for an event.
	 *
	 * Fine-grained counterpart to kdna_events_is_registration_open:
	 * returns one of 'active', 'not_yet_open', 'closed', 'sold_out'.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	protected function determine_state( $event_id ) {
		$event_id = (int) $event_id;
		if ( ! $event_id ) {
			return 'closed';
		}

		$timezone_string = (string) get_post_meta( $event_id, '_kdna_event_timezone', true );
		if ( '' === $timezone_string ) {
			$timezone_string = wp_timezone_string();
		}

		try {
			$timezone = new DateTimeZone( $timezone_string );
			$now      = new DateTimeImmutable( 'now', $timezone );
		} catch ( Exception $e ) {
			$timezone = wp_timezone();
			$now      = new DateTimeImmutable( 'now', $timezone );
		}

		$opens_raw  = (string) get_post_meta( $event_id, '_kdna_event_registration_opens', true );
		$closes_raw = (string) get_post_meta( $event_id, '_kdna_event_registration_closes', true );
		$start_raw  = (string) get_post_meta( $event_id, '_kdna_event_start', true );
		$capacity   = (int) get_post_meta( $event_id, '_kdna_event_capacity', true );

		if ( '' !== $opens_raw ) {
			try {
				$opens_at = new DateTimeImmutable( $opens_raw, $timezone );
				if ( $now < $opens_at ) {
					return 'not_yet_open';
				}
			} catch ( Exception $e ) {
				unset( $e );
			}
		}

		$closes_source = '' !== $closes_raw ? $closes_raw : $start_raw;
		if ( '' !== $closes_source ) {
			try {
				$closes_at = new DateTimeImmutable( $closes_source, $timezone );
				if ( $now >= $closes_at ) {
					return 'closed';
				}
			} catch ( Exception $e ) {
				unset( $e );
			}
		}

		if ( $capacity > 0 ) {
			$sold = kdna_events_get_tickets_sold( $event_id );
			if ( $sold >= $capacity ) {
				return 'sold_out';
			}
		}

		return 'active';
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		$event_id = $this->get_event_id();
		if ( ! $event_id ) {
			$this->render_editor_placeholder( __( 'Create an event to preview the register button.', 'kdna-events' ) );
			return;
		}

		$settings = $this->get_settings_for_display();

		// Resolve state. Editor preview may force a state for design purposes.
		$forced_state = isset( $settings['preview_state'] ) ? (string) $settings['preview_state'] : 'auto';
		if ( $this->is_editor_mode() && 'auto' !== $forced_state ) {
			$state = $forced_state;
		} else {
			$state = $this->determine_state( $event_id );
		}

		$checkout_url = kdna_events_get_page_url( 'checkout' );
		$no_checkout  = '' === $checkout_url;
		$is_free      = kdna_events_is_free( $event_id );

		$label = $this->resolve_label( $state, $is_free, $event_id, $settings );

		$size_preset = isset( $settings['size_preset'] ) && in_array( $settings['size_preset'], array( 'sm', 'md', 'lg', 'xl' ), true ) ? $settings['size_preset'] : 'lg';
		$align       = isset( $settings['align'] ) ? (string) $settings['align'] : 'flex-start';

		$is_active = 'active' === $state && ! $no_checkout;

		$classes = array(
			'kdna-events-event-register-button',
			'kdna-events-event-register-button--size-' . $size_preset,
			'kdna-events-event-register-button--state-' . str_replace( '_', '-', $state ),
			$is_active ? 'is-active' : 'is-disabled',
		);

		$icon_position = 'right' === ( $settings['icon_position'] ?? 'left' ) ? 'right' : 'left';
		if ( 'yes' === ( $settings['show_icon'] ?? '' ) ) {
			$classes[] = 'kdna-events-event-register-button--icon-' . $icon_position;
		}

		$href = '';
		if ( $is_active ) {
			$href = add_query_arg( 'event_id', (int) $event_id, $checkout_url );
		}
		?>
		<div class="kdna-events-event-register-button-wrap" data-align="<?php echo esc_attr( $align ); ?>">
			<?php if ( $is_active ) : ?>
				<a class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" href="<?php echo esc_url( $href ); ?>">
					<?php $this->render_button_inner( $settings, $label, $icon_position ); ?>
				</a>
			<?php else : ?>
				<span class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" aria-disabled="true" role="link">
					<?php $this->render_button_inner( $settings, $label, $icon_position ); ?>
				</span>
			<?php endif; ?>
			<?php if ( $no_checkout && current_user_can( 'manage_options' ) ) : ?>
				<p class="kdna-events-event-register-button-notice" role="status">
					<?php
					printf(
						/* translators: %s: link to the KDNA Events Pages settings tab */
						esc_html__( 'No checkout page is assigned. Admins: configure one in %s to enable this button.', 'kdna-events' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=kdna-events-settings&tab=pages' ) ) . '">' . esc_html__( 'Events Settings, Pages tab', 'kdna-events' ) . '</a>'
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the button icon + label inner content.
	 *
	 * @param array  $settings      Widget settings.
	 * @param string $label         Resolved label text.
	 * @param string $icon_position 'left' or 'right'.
	 * @return void
	 */
	protected function render_button_inner( $settings, $label, $icon_position ) {
		$show_icon = 'yes' === ( $settings['show_icon'] ?? '' );
		$icon      = $settings['icon'] ?? null;

		if ( $show_icon && 'left' === $icon_position && is_array( $icon ) && ! empty( $icon['value'] ) ) {
			echo '<span class="kdna-events-event-register-button__icon" aria-hidden="true">';
			\Elementor\Icons_Manager::render_icon( $icon );
			echo '</span>';
		}

		echo '<span class="kdna-events-event-register-button__label">' . esc_html( $label ) . '</span>';

		if ( $show_icon && 'right' === $icon_position && is_array( $icon ) && ! empty( $icon['value'] ) ) {
			echo '<span class="kdna-events-event-register-button__icon" aria-hidden="true">';
			\Elementor\Icons_Manager::render_icon( $icon );
			echo '</span>';
		}
	}

	/**
	 * Resolve the final button label based on state and free-event swap.
	 *
	 * @param string $state    One of active / not_yet_open / closed / sold_out.
	 * @param bool   $is_free  Whether the event is free.
	 * @param int    $event_id Event post ID.
	 * @param array  $settings Widget settings.
	 * @return string
	 */
	protected function resolve_label( $state, $is_free, $event_id, $settings ) {
		switch ( $state ) {
			case 'not_yet_open':
				$raw = (string) ( $settings['not_yet_open_label'] ?? __( 'Registration opens {date}', 'kdna-events' ) );
				return $this->apply_not_yet_open_merge_tags( $raw, $event_id, $settings );

			case 'closed':
				return (string) ( $settings['closed_label'] ?? __( 'Registration closed', 'kdna-events' ) );

			case 'sold_out':
				return (string) ( $settings['sold_out_label'] ?? __( 'Sold Out', 'kdna-events' ) );

			case 'active':
			default:
				$auto_swap = 'yes' === ( $settings['auto_swap_free'] ?? 'yes' );
				if ( $auto_swap && $is_free ) {
					$free = (string) ( $settings['free_label'] ?? __( 'Reserve Free Spot', 'kdna-events' ) );
					if ( '' !== $free ) {
						return $free;
					}
				}
				return (string) ( $settings['active_label'] ?? __( 'Register', 'kdna-events' ) );
		}
	}

	/**
	 * Replace {date}, {time}, {datetime} merge tags in the not-yet-open label.
	 *
	 * Uses the event's timezone (falling back to the site timezone) so the
	 * rendered date matches when the registration window actually opens
	 * for the event's audience.
	 *
	 * @param string $raw      Label containing merge tags.
	 * @param int    $event_id Event post ID.
	 * @param array  $settings Widget settings.
	 * @return string
	 */
	protected function apply_not_yet_open_merge_tags( $raw, $event_id, $settings ) {
		$opens_raw = (string) get_post_meta( $event_id, '_kdna_event_registration_opens', true );
		if ( '' === $opens_raw ) {
			return str_replace( array( '{date}', '{time}', '{datetime}' ), '', $raw );
		}

		$date_format = (string) ( $settings['not_yet_open_date_format'] ?? 'j M Y' );
		$time_format = (string) ( $settings['not_yet_open_time_format'] ?? 'g:i a' );

		$date_str     = kdna_events_format_datetime( $opens_raw, $date_format, $event_id );
		$time_str     = kdna_events_format_datetime( $opens_raw, $time_format, $event_id );
		$datetime_str = trim( $date_str . ' ' . $time_str );

		return strtr(
			$raw,
			array(
				'{date}'     => $date_str,
				'{time}'     => $time_str,
				'{datetime}' => $datetime_str,
			)
		);
	}
}
