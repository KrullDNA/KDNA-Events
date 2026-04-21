<?php
/**
 * Event Date/Time widget.
 *
 * Renders the event start and end as formatted date and time, with a
 * configurable separator, optional icon, and independent typography
 * and icon styling. Date/time formatting respects the event timezone.
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
 * Elementor widget rendering the event start / end datetime.
 */
class KDNA_Events_Widget_Event_Datetime extends KDNA_Events_Widget_Base {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-datetime';
	}

	/**
	 * Visible title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Date / Time', 'kdna-events' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-clock-o';
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
			'show_start',
			array(
				'label'        => __( 'Show start', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_end',
			array(
				'label'        => __( 'Show end', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'start_date_format',
			array(
				'label'       => __( 'Start date format', 'kdna-events' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'j F Y',
				'description' => __( 'PHP date format. Leave blank to hide the date.', 'kdna-events' ),
				'condition'   => array( 'show_start' => 'yes' ),
			)
		);

		$this->add_control(
			'start_time_format',
			array(
				'label'       => __( 'Start time format', 'kdna-events' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'g:i a',
				'description' => __( 'Leave blank to hide the time.', 'kdna-events' ),
				'condition'   => array( 'show_start' => 'yes' ),
			)
		);

		$this->add_control(
			'end_date_format',
			array(
				'label'     => __( 'End date format', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'j F Y',
				'condition' => array( 'show_end' => 'yes' ),
			)
		);

		$this->add_control(
			'end_time_format',
			array(
				'label'     => __( 'End time format', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'g:i a',
				'condition' => array( 'show_end' => 'yes' ),
			)
		);

		$this->add_control(
			'separator',
			array(
				'label'   => __( 'Separator', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => ' to ',
			)
		);

		$this->add_control(
			'show_icon',
			array(
				'label'        => __( 'Show icon', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'icon',
			array(
				'label'     => __( 'Icon', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array(
					'value'   => 'eicon-calendar',
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

		$this->end_controls_section();

		$this->start_controls_section(
			'section_text_style',
			array(
				'label' => __( 'Text', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-datetime' => 'color: {{VALUE}};',
				),
			)
		);

		$this->register_typography_control( 'typography', __( 'Typography', 'kdna-events' ), '.kdna-events-event-datetime' );

		$this->register_spacing_controls( 'wrapper', '.kdna-events-event-datetime' );

		$this->end_controls_section();

		$this->start_controls_section(
			'section_icon_style',
			array(
				'label'     => __( 'Icon', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_icon' => 'yes' ),
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => __( 'Icon colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-datetime__icon i, {{WRAPPER}} .kdna-events-event-datetime__icon svg' => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'icon_size',
			array(
				'label'      => __( 'Icon size', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 8,
						'max' => 96,
					),
					'em' => array(
						'min'  => 0.5,
						'max'  => 5,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'em',
					'size' => 1,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-datetime__icon i, {{WRAPPER}} .kdna-events-event-datetime__icon svg' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
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
						'max' => 48,
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
					'{{WRAPPER}} .kdna-events-event-datetime' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

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
			$this->render_editor_placeholder( __( 'Create an event to preview the date.', 'kdna-events' ) );
			return;
		}

		$settings = $this->get_settings_for_display();

		$start_raw = (string) get_post_meta( $event_id, '_kdna_event_start', true );
		$end_raw   = (string) get_post_meta( $event_id, '_kdna_event_end', true );

		$show_start = 'yes' === ( $settings['show_start'] ?? 'yes' );
		$show_end   = 'yes' === ( $settings['show_end'] ?? 'yes' );

		$start_str = '';
		$end_str   = '';

		if ( $show_start && '' !== $start_raw ) {
			$start_str = $this->format_datetime_parts(
				$start_raw,
				$event_id,
				$settings['start_date_format'] ?? 'j F Y',
				$settings['start_time_format'] ?? 'g:i a'
			);
		}

		if ( $show_end && '' !== $end_raw ) {
			$end_str = $this->format_datetime_parts(
				$end_raw,
				$event_id,
				$settings['end_date_format'] ?? 'j F Y',
				$settings['end_time_format'] ?? 'g:i a'
			);
		}

		if ( '' === $start_str && '' === $end_str ) {
			$this->render_editor_placeholder( __( 'No event date set.', 'kdna-events' ) );
			return;
		}

		$show_icon     = 'yes' === ( $settings['show_icon'] ?? 'yes' );
		$icon_position = $settings['icon_position'] ?? 'left';

		$classes   = array( 'kdna-events-event-datetime' );
		$classes[] = 'kdna-events-event-datetime--icon-' . ( 'right' === $icon_position ? 'right' : 'left' );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php if ( $show_icon && ! empty( $settings['icon']['value'] ) ) : ?>
				<span class="kdna-events-event-datetime__icon" aria-hidden="true">
					<?php \Elementor\Icons_Manager::render_icon( $settings['icon'] ); ?>
				</span>
			<?php endif; ?>
			<span class="kdna-events-event-datetime__text">
				<?php if ( '' !== $start_str ) : ?>
					<span class="kdna-events-event-datetime__start"><?php echo esc_html( $start_str ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $start_str && '' !== $end_str ) : ?>
					<span class="kdna-events-event-datetime__separator"><?php echo esc_html( (string) ( $settings['separator'] ?? ' to ' ) ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $end_str ) : ?>
					<span class="kdna-events-event-datetime__end"><?php echo esc_html( $end_str ); ?></span>
				<?php endif; ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Format a stored datetime using the date and time format parts.
	 *
	 * Joins the formatted date and time with a single space. Either part
	 * may be blanked to hide that segment.
	 *
	 * @param string $iso         ISO 8601 datetime from meta.
	 * @param int    $event_id    Event post ID.
	 * @param string $date_format PHP date format for the date portion.
	 * @param string $time_format PHP date format for the time portion.
	 * @return string
	 */
	protected function format_datetime_parts( $iso, $event_id, $date_format, $time_format ) {
		$date_format = (string) $date_format;
		$time_format = (string) $time_format;

		$parts = array();
		if ( '' !== trim( $date_format ) ) {
			$parts[] = kdna_events_format_datetime( $iso, $date_format, $event_id );
		}
		if ( '' !== trim( $time_format ) ) {
			$parts[] = kdna_events_format_datetime( $iso, $time_format, $event_id );
		}

		return trim( implode( ' ', array_filter( $parts ) ) );
	}
}
