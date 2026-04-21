<?php
/**
 * My Tickets widget.
 *
 * Logged-in user dashboard listing every valid ticket owned by the
 * current user, grouped by event, split into Upcoming and Past tabs
 * based on each event's start. Guests get a configurable login prompt
 * with a redirect back to the current URL.
 *
 * Tickets are looked up by joining orders on user_id OR purchaser_email
 * so guest purchases become visible to buyers who later register with
 * the same email.
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
 * Elementor widget rendering the user dashboard ticket list.
 */
class KDNA_Events_Widget_My_Tickets extends KDNA_Events_Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-my-tickets';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'My Tickets', 'kdna-events' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-archive-posts';
	}

	/**
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'kdna-events-frontend' );
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_tabs',
			array(
				'label'        => __( 'Show upcoming / past tabs', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'upcoming_label',
			array(
				'label'   => __( 'Upcoming tab label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Upcoming', 'kdna-events' ),
			)
		);

		$this->add_control(
			'past_label',
			array(
				'label'   => __( 'Past tab label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Past', 'kdna-events' ),
			)
		);

		$this->add_control(
			'show_qr',
			array(
				'label'        => __( 'Show QR code', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_download',
			array(
				'label'        => __( 'Show download PDF button', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Placeholder. PDF ticket generation is scheduled for a post-launch release.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'empty_message',
			array(
				'label'   => __( 'Empty tab message', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'No tickets here yet.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'guest_message',
			array(
				'label'   => __( 'Guest message', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'Log in to see your tickets.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'login_button_label',
			array(
				'label'   => __( 'Login button label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Log in', 'kdna-events' ),
			)
		);

		$this->add_control(
			'monospace_code',
			array(
				'label'        => __( 'Monospace ticket code', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();

		$this->register_tab_style_controls();
		$this->register_heading_style_controls();
		$this->register_card_style_controls();
		$this->register_login_style_controls();
	}

	/**
	 * Register style controls for the Upcoming / Past tabs.
	 *
	 * Normal / Hover / Active state tabs per Section 2.
	 *
	 * @return void
	 */
	protected function register_tab_style_controls() {
		$this->start_controls_section(
			'section_style_tabs',
			array(
				'label'     => __( 'Tabs', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_tabs' => 'yes' ),
			)
		);

		$this->register_typography_control( 'tab_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-my-tickets__tab' );

		$this->add_responsive_control(
			'tab_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 0.5,
					'right'    => 1,
					'bottom'   => 0.5,
					'left'     => 1,
					'unit'     => 'em',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-my-tickets__tab' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'tab_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-my-tickets__tab' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'tab_state' );

		$this->start_controls_tab( 'tab_normal', array( 'label' => __( 'Normal', 'kdna-events' ) ) );
		$this->add_control(
			'tab_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__tab' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'tab_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__tab' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'tab_border',
				'selector' => '{{WRAPPER}} .kdna-events-my-tickets__tab',
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'tab_hover', array( 'label' => __( 'Hover', 'kdna-events' ) ) );
		$this->add_control(
			'tab_hover_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__tab:hover' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'tab_hover_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__tab:hover' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'tab_active', array( 'label' => __( 'Active', 'kdna-events' ) ) );
		$this->add_control(
			'tab_active_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__tab.is-active' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'tab_active_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1d4ed8',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__tab.is-active' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'tab_active_border',
			array(
				'label'     => __( 'Border colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__tab.is-active' => 'border-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Event group heading styles.
	 *
	 * @return void
	 */
	protected function register_heading_style_controls() {
		$this->start_controls_section(
			'section_style_heading',
			array(
				'label' => __( 'Event group heading', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'heading_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__group-heading' => 'color: {{VALUE}};',
				),
			)
		);
		$this->register_typography_control( 'heading_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-my-tickets__group-heading' );
		$this->add_responsive_control(
			'heading_margin',
			array(
				'label'      => __( 'Margin', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-my-tickets__group-heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Per-ticket card styles, reused from the Success Tickets pattern.
	 *
	 * @return void
	 */
	protected function register_card_style_controls() {
		$this->start_controls_section(
			'section_style_card',
			array(
				'label' => __( 'Ticket card', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'card_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-ticket' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .kdna-events-my-ticket',
			)
		);
		$this->add_control(
			'card_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'      => 12,
					'right'    => 12,
					'bottom'   => 12,
					'left'     => 12,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-my-ticket' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
				),
			)
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 16,
					'right'    => 16,
					'bottom'   => 16,
					'left'     => 16,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-my-ticket' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .kdna-events-my-ticket',
			)
		);
		$this->add_responsive_control(
			'card_gap',
			array(
				'label'      => __( 'Gap between tickets', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 48,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 12,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-my-tickets__list' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Attendee name colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-ticket__name' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'code_color',
			array(
				'label'     => __( 'Ticket code colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-ticket__code' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'code_bg',
			array(
				'label'     => __( 'Ticket code background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#f3f4f6',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-ticket__code' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'code_border',
				'selector' => '{{WRAPPER}} .kdna-events-my-ticket__code',
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Login prompt styling.
	 *
	 * @return void
	 */
	protected function register_login_style_controls() {
		$this->start_controls_section(
			'section_style_login',
			array(
				'label' => __( 'Login prompt', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'login_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#f9fafb',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__login' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'login_border',
				'selector' => '{{WRAPPER}} .kdna-events-my-tickets__login',
			)
		);
		$this->add_responsive_control(
			'login_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 24,
					'right'    => 24,
					'bottom'   => 24,
					'left'     => 24,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-my-tickets__login' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_control(
			'login_message_color',
			array(
				'label'     => __( 'Message colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__login-message' => 'color: {{VALUE}};',
				),
			)
		);
		$this->register_typography_control( 'login_message_typography', __( 'Message typography', 'kdna-events' ), '.kdna-events-my-tickets__login-message' );

		$this->start_controls_tabs( 'login_button_state' );
		$this->start_controls_tab( 'login_button_normal', array( 'label' => __( 'Normal', 'kdna-events' ) ) );
		$this->add_control(
			'login_button_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__login-button' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'login_button_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1d4ed8',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__login-button' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'login_button_border',
				'selector' => '{{WRAPPER}} .kdna-events-my-tickets__login-button',
			)
		);
		$this->add_control(
			'login_button_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'      => 6,
					'right'    => 6,
					'bottom'   => 6,
					'left'     => 6,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-my-tickets__login-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'login_button_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 0.6,
					'right'    => 1.2,
					'bottom'   => 0.6,
					'left'     => 1.2,
					'unit'     => 'em',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-my-tickets__login-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'login_button_hover', array( 'label' => __( 'Hover', 'kdna-events' ) ) );
		$this->add_control(
			'login_button_hover_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__login-button:hover' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'login_button_hover_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1e40af',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-my-tickets__login-button:hover' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Fetch valid tickets for the current user.
	 *
	 * Joins orders on user_id OR purchaser_email so guest bookings
	 * surface for buyers who later register with the same email.
	 *
	 * @return array<int,object>
	 */
	protected function fetch_user_tickets() {
		global $wpdb;

		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return array();
		}

		$tickets_table = KDNA_Events_DB::tickets_table();
		$orders_table  = KDNA_Events_DB::orders_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.ticket_id, t.ticket_code, t.attendee_name, t.attendee_email, t.status, t.event_id, t.created_at,
				        o.order_reference, o.user_id, o.purchaser_email
				   FROM {$tickets_table} t
				   INNER JOIN {$orders_table} o ON t.order_id = o.order_id
				   WHERE t.status = %s
				   AND ( o.user_id = %d OR o.purchaser_email = %s )
				   ORDER BY t.ticket_id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'valid',
				(int) $user->ID,
				(string) $user->user_email
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Group tickets by event and partition into upcoming / past.
	 *
	 * @param array<int,object> $tickets Ticket rows.
	 * @return array{upcoming:array<int,array>,past:array<int,array>}
	 */
	protected function group_and_partition( $tickets ) {
		$groups = array();
		foreach ( $tickets as $ticket ) {
			$event_id = (int) $ticket->event_id;
			if ( ! isset( $groups[ $event_id ] ) ) {
				$groups[ $event_id ] = array(
					'event_id' => $event_id,
					'title'    => get_the_title( $event_id ),
					'start'    => (string) get_post_meta( $event_id, '_kdna_event_start', true ),
					'tickets'  => array(),
				);
			}
			$groups[ $event_id ]['tickets'][] = $ticket;
		}

		$now_ts = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		$upcoming = array();
		$past     = array();
		foreach ( $groups as $group ) {
			$start_ts = '' !== $group['start'] ? strtotime( $group['start'] ) : 0;
			if ( $start_ts && $start_ts >= $now_ts ) {
				$group['_ts'] = $start_ts;
				$upcoming[]   = $group;
			} else {
				$group['_ts'] = $start_ts;
				$past[]       = $group;
			}
		}

		usort( $upcoming, static function ( $a, $b ) { return $a['_ts'] <=> $b['_ts']; } );
		usort( $past, static function ( $a, $b ) { return $b['_ts'] <=> $a['_ts']; } );

		return array( 'upcoming' => $upcoming, 'past' => $past );
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! is_user_logged_in() && ! $this->is_editor_mode() ) {
			$this->render_login_prompt( $settings );
			return;
		}

		// Editor preview uses synthetic data so authors can style the widget.
		if ( $this->is_editor_mode() && ! is_user_logged_in() ) {
			$groups = array( 'upcoming' => $this->make_preview_groups( true ), 'past' => $this->make_preview_groups( false ) );
		} else {
			$tickets = $this->fetch_user_tickets();
			if ( empty( $tickets ) && $this->is_editor_mode() ) {
				$groups = array( 'upcoming' => $this->make_preview_groups( true ), 'past' => $this->make_preview_groups( false ) );
			} else {
				$groups = $this->group_and_partition( $tickets );
			}
		}

		$show_tabs     = 'yes' === ( $settings['show_tabs'] ?? 'yes' );
		$upcoming_text = (string) ( $settings['upcoming_label'] ?? __( 'Upcoming', 'kdna-events' ) );
		$past_text     = (string) ( $settings['past_label'] ?? __( 'Past', 'kdna-events' ) );
		$empty_msg     = (string) ( $settings['empty_message'] ?? __( 'No tickets here yet.', 'kdna-events' ) );
		$show_qr       = 'yes' === ( $settings['show_qr'] ?? 'yes' );
		$show_download = 'yes' === ( $settings['show_download'] ?? '' );
		$monospace     = 'yes' === ( $settings['monospace_code'] ?? 'yes' );
		?>
		<div class="kdna-events-my-tickets" data-kdna-events-my-tickets="1">
			<?php if ( $show_tabs ) : ?>
				<div class="kdna-events-my-tickets__tabs" role="tablist">
					<button type="button" class="kdna-events-my-tickets__tab is-active" data-target="upcoming" role="tab" aria-selected="true">
						<?php echo esc_html( $upcoming_text ); ?>
						<span class="kdna-events-my-tickets__tab-count">(<?php echo esc_html( (string) count( $groups['upcoming'] ) ); ?>)</span>
					</button>
					<button type="button" class="kdna-events-my-tickets__tab" data-target="past" role="tab" aria-selected="false">
						<?php echo esc_html( $past_text ); ?>
						<span class="kdna-events-my-tickets__tab-count">(<?php echo esc_html( (string) count( $groups['past'] ) ); ?>)</span>
					</button>
				</div>
			<?php endif; ?>

			<div class="kdna-events-my-tickets__panel is-active" data-panel="upcoming">
				<?php $this->render_groups( $groups['upcoming'], $empty_msg, $show_qr, $show_download, $monospace ); ?>
			</div>
			<div class="kdna-events-my-tickets__panel" data-panel="past">
				<?php $this->render_groups( $groups['past'], $empty_msg, $show_qr, $show_download, $monospace ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a set of event groups or the empty state.
	 *
	 * @param array<int,array> $groups        Grouped event rows.
	 * @param string           $empty_msg     Empty message.
	 * @param bool             $show_qr       Whether to render QR codes.
	 * @param bool             $show_download Whether to render the PDF button.
	 * @param bool             $monospace     Whether to use monospace code.
	 * @return void
	 */
	protected function render_groups( $groups, $empty_msg, $show_qr, $show_download, $monospace ) {
		if ( empty( $groups ) ) {
			echo '<p class="kdna-events-my-tickets__empty">' . esc_html( $empty_msg ) . '</p>';
			return;
		}

		foreach ( $groups as $group ) {
			$date_str = '' !== $group['start'] ? kdna_events_format_datetime( $group['start'], 'j F Y, g:i a', $group['event_id'] ) : '';
			?>
			<section class="kdna-events-my-tickets__group">
				<h3 class="kdna-events-my-tickets__group-heading">
					<?php echo esc_html( (string) $group['title'] ); ?>
					<?php if ( '' !== $date_str ) : ?>
						<span class="kdna-events-my-tickets__group-date"><?php echo esc_html( $date_str ); ?></span>
					<?php endif; ?>
				</h3>
				<div class="kdna-events-my-tickets__list">
					<?php foreach ( $group['tickets'] as $ticket ) :
						$code_class = 'kdna-events-my-ticket__code' . ( $monospace ? ' kdna-events-my-ticket__code--mono' : '' );
						?>
						<article class="kdna-events-my-ticket">
							<div class="kdna-events-my-ticket__details">
								<div class="kdna-events-my-ticket__name"><?php echo esc_html( (string) $ticket->attendee_name ); ?></div>
								<div class="<?php echo esc_attr( $code_class ); ?>" aria-label="<?php esc_attr_e( 'Ticket code', 'kdna-events' ); ?>">
									<?php echo esc_html( (string) $ticket->ticket_code ); ?>
								</div>
								<?php if ( $show_download ) : ?>
									<button type="button" class="kdna-events-my-ticket__download" disabled>
										<?php esc_html_e( 'Download PDF (coming soon)', 'kdna-events' ); ?>
									</button>
								<?php endif; ?>
							</div>
							<?php if ( $show_qr ) : ?>
								<div class="kdna-events-my-ticket__qr">
									<img
										src="<?php echo esc_url( $this->qr_url( (string) $ticket->ticket_code ) ); ?>"
										alt="<?php echo esc_attr( sprintf( /* translators: %s: ticket code */ __( 'QR code for ticket %s', 'kdna-events' ), $ticket->ticket_code ) ); ?>"
										width="108"
										height="108"
										loading="lazy"
									/>
								</div>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
			<?php
		}
	}

	/**
	 * Render the guest login prompt.
	 *
	 * @param array $settings Widget settings.
	 * @return void
	 */
	protected function render_login_prompt( $settings ) {
		$message = (string) ( $settings['guest_message'] ?? __( 'Log in to see your tickets.', 'kdna-events' ) );
		$label   = (string) ( $settings['login_button_label'] ?? __( 'Log in', 'kdna-events' ) );

		$current_url = home_url( add_query_arg( null, null ) );
		$login_url   = wp_login_url( $current_url );
		?>
		<div class="kdna-events-my-tickets__login">
			<p class="kdna-events-my-tickets__login-message"><?php echo esc_html( $message ); ?></p>
			<a class="kdna-events-my-tickets__login-button" href="<?php echo esc_url( $login_url ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Build preview groups for the Elementor editor.
	 *
	 * @param bool $upcoming When true, return one upcoming event; otherwise one past event.
	 * @return array<int,array>
	 */
	protected function make_preview_groups( $upcoming ) {
		$label = $upcoming ? __( 'Sample Upcoming Event', 'kdna-events' ) : __( 'Sample Past Event', 'kdna-events' );
		return array(
			array(
				'event_id' => 0,
				'title'    => $label,
				'start'    => current_time( 'Y-m-d\TH:i' ),
				'tickets'  => array(
					(object) array(
						'ticket_id'     => 1,
						'ticket_code'   => 'ABCD1234',
						'attendee_name' => __( 'Jane Doe', 'kdna-events' ),
						'status'        => 'valid',
					),
				),
			),
		);
	}

	/**
	 * Return the QR image URL for a ticket code. Mirrors the Success Tickets widget.
	 *
	 * @param string $code Ticket code.
	 * @return string
	 */
	protected function qr_url( $code ) {
		return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query(
			array(
				'size'   => '216x216',
				'margin' => 4,
				'data'   => $code,
			)
		);
	}
}
