<?php
/**
 * Event Filter widget.
 *
 * Filter UI that drives an Event Grid widget on the same page. In
 * AJAX mode (the default) it posts state to the shared filter action
 * and replaces the grid's cards without a page reload. In reload mode
 * it falls back to submitting as a form.
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
 * Elementor widget rendering the grid filter bar.
 */
class KDNA_Events_Widget_Event_Filter extends KDNA_Events_Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-filter';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Filter', 'kdna-events' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-filter';
	}

	/**
	 * Declare the frontend script as a dependency so filter JS is always loaded.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'kdna-events-frontend' );
	}

	/**
	 * Build a list of category options.
	 *
	 * @return array<int,string>
	 */
	protected function category_options() {
		$out   = array( 0 => __( 'All categories', 'kdna-events' ) );
		$terms = get_terms(
			array(
				'taxonomy'   => 'kdna_event_category',
				'hide_empty' => false,
			)
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$out[ (int) $term->term_id ] = $term->name;
			}
		}
		return $out;
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_filters',
			array(
				'label' => __( 'Filters', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$filter_toggles = array(
			'show_type'       => array( __( 'Type', 'kdna-events' ), 'yes' ),
			'show_price'      => array( __( 'Price (free / paid)', 'kdna-events' ), 'yes' ),
			'show_category'   => array( __( 'Category', 'kdna-events' ), 'yes' ),
			'show_date_range' => array( __( 'Date range', 'kdna-events' ), 'yes' ),
			'show_search'     => array( __( 'Search', 'kdna-events' ), 'yes' ),
		);
		foreach ( $filter_toggles as $key => $info ) {
			$this->add_control(
				$key,
				array(
					'label'        => $info[0],
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => $info[1],
				)
			);
		}

		$this->add_control(
			'label_all',
			array(
				'label'   => __( 'All label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'All', 'kdna-events' ),
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
			'direction',
			array(
				'label'   => __( 'Direction', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'horizontal',
				'options' => array(
					'horizontal' => __( 'Horizontal', 'kdna-events' ),
					'vertical'   => __( 'Vertical', 'kdna-events' ),
				),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Gap', 'kdna-events' ),
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
					'unit' => 'px',
					'size' => 16,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-filter' => 'gap: {{SIZE}}{{UNIT}};',
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
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-filter' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_behaviour',
			array(
				'label' => __( 'Behaviour', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'mode',
			array(
				'label'   => __( 'Mode', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'ajax',
				'options' => array(
					'ajax'   => __( 'AJAX', 'kdna-events' ),
					'reload' => __( 'Reload page', 'kdna-events' ),
				),
			)
		);

		$this->add_control(
			'target',
			array(
				'label'   => __( 'Target grid selector', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '.kdna-events-grid__wrapper',
			)
		);

		$this->end_controls_section();

		$this->register_label_style_controls();
		$this->register_input_style_controls();
		$this->register_pill_style_controls();
	}

	/**
	 * Filter label style section.
	 *
	 * @return void
	 */
	protected function register_label_style_controls() {
		$this->start_controls_section(
			'section_style_label',
			array(
				'label' => __( 'Labels', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'label_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-filter__label' => 'color: {{VALUE}};',
				),
			)
		);

		$this->register_typography_control( 'label_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-filter__label' );

		$this->add_responsive_control(
			'label_margin',
			array(
				'label'      => __( 'Margin', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-filter__label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Input (search, date, select) style section.
	 *
	 * @return void
	 */
	protected function register_input_style_controls() {
		$this->start_controls_section(
			'section_style_input',
			array(
				'label' => __( 'Inputs', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$input_selector = '.kdna-events-filter__input, {{WRAPPER}} .kdna-events-filter__select, {{WRAPPER}} .kdna-events-filter__date';

		$this->add_control(
			'input_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} ' . $input_selector => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'input_background',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} ' . $input_selector => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->register_typography_control( 'input_typography', __( 'Typography', 'kdna-events' ), $input_selector );

		$this->add_responsive_control(
			'input_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 0.5,
					'right'    => 0.75,
					'bottom'   => 0.5,
					'left'     => 0.75,
					'unit'     => 'em',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} ' . $input_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'input_border',
				'selector' => '{{WRAPPER}} ' . $input_selector,
			)
		);

		$this->add_control(
			'input_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} ' . $input_selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'input_focus_border_color',
			array(
				'label'     => __( 'Focus border colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-filter__input:focus, {{WRAPPER}} .kdna-events-filter__select:focus, {{WRAPPER}} .kdna-events-filter__date:focus' => 'border-color: {{VALUE}}; outline: none; box-shadow: 0 0 0 3px {{VALUE}}33;',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Pill buttons style section (type and price tabs).
	 *
	 * @return void
	 */
	protected function register_pill_style_controls() {
		$this->start_controls_section(
			'section_style_pills',
			array(
				'label' => __( 'Pill buttons', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->register_typography_control( 'pill_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-filter__pill' );

		$this->add_responsive_control(
			'pill_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 0.4,
					'right'    => 0.9,
					'bottom'   => 0.4,
					'left'     => 0.9,
					'unit'     => 'em',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-filter__pill' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'pill_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'      => 999,
					'right'    => 999,
					'bottom'   => 999,
					'left'     => 999,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-filter__pill' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'pill_state' );

		$this->start_controls_tab( 'pill_normal', array( 'label' => __( 'Normal', 'kdna-events' ) ) );
		$this->add_control(
			'pill_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1f2937',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-filter__pill' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'pill_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#f3f4f6',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-filter__pill' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'pill_border',
				'selector' => '{{WRAPPER}} .kdna-events-filter__pill',
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'pill_hover', array( 'label' => __( 'Hover', 'kdna-events' ) ) );
		$this->add_control(
			'pill_hover_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-filter__pill:hover' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'pill_hover_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-filter__pill:hover' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'pill_active', array( 'label' => __( 'Active', 'kdna-events' ) ) );
		$this->add_control(
			'pill_active_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-filter__pill.is-active' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'pill_active_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1d4ed8',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-filter__pill.is-active' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'pill_active_border',
			array(
				'label'     => __( 'Border colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-filter__pill.is-active' => 'border-color: {{VALUE}};',
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
		$settings = $this->get_settings_for_display();

		$direction = 'vertical' === ( $settings['direction'] ?? 'horizontal' ) ? 'vertical' : 'horizontal';
		$mode      = 'reload' === ( $settings['mode'] ?? 'ajax' ) ? 'reload' : 'ajax';
		$target    = (string) ( $settings['target'] ?? '.kdna-events-grid__wrapper' );
		if ( '' === trim( $target ) ) {
			$target = '.kdna-events-grid__wrapper';
		}

		$show_type       = 'yes' === ( $settings['show_type'] ?? 'yes' );
		$show_price      = 'yes' === ( $settings['show_price'] ?? 'yes' );
		$show_category   = 'yes' === ( $settings['show_category'] ?? 'yes' );
		$show_date_range = 'yes' === ( $settings['show_date_range'] ?? 'yes' );
		$show_search     = 'yes' === ( $settings['show_search'] ?? 'yes' );

		$label_all = (string) ( $settings['label_all'] ?? __( 'All', 'kdna-events' ) );

		$type_options = array(
			''          => $label_all,
			'in-person' => __( 'In-person', 'kdna-events' ),
			'virtual'   => __( 'Virtual', 'kdna-events' ),
			'hybrid'    => __( 'Hybrid', 'kdna-events' ),
		);
		$price_options = array(
			''     => $label_all,
			'free' => __( 'Free', 'kdna-events' ),
			'paid' => __( 'Paid', 'kdna-events' ),
		);
		?>
		<form
			class="kdna-events-filter kdna-events-filter--<?php echo esc_attr( $direction ); ?>"
			data-kdna-events-filter="1"
			data-mode="<?php echo esc_attr( $mode ); ?>"
			data-target="<?php echo esc_attr( $target ); ?>"
			method="get"
			action=""
		>
			<?php if ( $show_type ) : ?>
				<fieldset class="kdna-events-filter__group kdna-events-filter__group--pills" data-filter-key="type">
					<legend class="kdna-events-filter__label"><?php esc_html_e( 'Type', 'kdna-events' ); ?></legend>
					<div class="kdna-events-filter__pills">
						<?php foreach ( $type_options as $value => $label ) : ?>
							<button type="button" class="kdna-events-filter__pill <?php echo '' === $value ? 'is-active' : ''; ?>" data-value="<?php echo esc_attr( $value ); ?>">
								<?php echo esc_html( $label ); ?>
							</button>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="type" value="" />
				</fieldset>
			<?php endif; ?>

			<?php if ( $show_price ) : ?>
				<fieldset class="kdna-events-filter__group kdna-events-filter__group--pills" data-filter-key="price">
					<legend class="kdna-events-filter__label"><?php esc_html_e( 'Price', 'kdna-events' ); ?></legend>
					<div class="kdna-events-filter__pills">
						<?php foreach ( $price_options as $value => $label ) : ?>
							<button type="button" class="kdna-events-filter__pill <?php echo '' === $value ? 'is-active' : ''; ?>" data-value="<?php echo esc_attr( $value ); ?>">
								<?php echo esc_html( $label ); ?>
							</button>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="price" value="" />
				</fieldset>
			<?php endif; ?>

			<?php if ( $show_category ) : ?>
				<div class="kdna-events-filter__group" data-filter-key="category">
					<label class="kdna-events-filter__label" for="<?php echo esc_attr( 'kdna_events_filter_category_' . $this->get_id() ); ?>">
						<?php esc_html_e( 'Category', 'kdna-events' ); ?>
					</label>
					<select
						class="kdna-events-filter__select"
						id="<?php echo esc_attr( 'kdna_events_filter_category_' . $this->get_id() ); ?>"
						name="category"
					>
						<?php foreach ( $this->category_options() as $id => $name ) : ?>
							<option value="<?php echo esc_attr( (string) $id ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if ( $show_date_range ) : ?>
				<div class="kdna-events-filter__group kdna-events-filter__group--dates" data-filter-key="date_range">
					<label class="kdna-events-filter__label" for="<?php echo esc_attr( 'kdna_events_filter_from_' . $this->get_id() ); ?>">
						<?php esc_html_e( 'From', 'kdna-events' ); ?>
					</label>
					<input
						type="date"
						class="kdna-events-filter__date"
						id="<?php echo esc_attr( 'kdna_events_filter_from_' . $this->get_id() ); ?>"
						name="date_from"
					/>
					<label class="kdna-events-filter__label" for="<?php echo esc_attr( 'kdna_events_filter_to_' . $this->get_id() ); ?>">
						<?php esc_html_e( 'To', 'kdna-events' ); ?>
					</label>
					<input
						type="date"
						class="kdna-events-filter__date"
						id="<?php echo esc_attr( 'kdna_events_filter_to_' . $this->get_id() ); ?>"
						name="date_to"
					/>
				</div>
			<?php endif; ?>

			<?php if ( $show_search ) : ?>
				<div class="kdna-events-filter__group" data-filter-key="search">
					<label class="kdna-events-filter__label" for="<?php echo esc_attr( 'kdna_events_filter_search_' . $this->get_id() ); ?>">
						<?php esc_html_e( 'Search', 'kdna-events' ); ?>
					</label>
					<input
						type="search"
						class="kdna-events-filter__input"
						id="<?php echo esc_attr( 'kdna_events_filter_search_' . $this->get_id() ); ?>"
						name="search"
						placeholder="<?php esc_attr_e( 'Search events...', 'kdna-events' ); ?>"
					/>
				</div>
			<?php endif; ?>

			<div class="kdna-events-filter__actions">
				<button type="submit" class="kdna-events-filter__submit">
					<?php esc_html_e( 'Apply', 'kdna-events' ); ?>
				</button>
				<button type="reset" class="kdna-events-filter__reset">
					<?php esc_html_e( 'Reset', 'kdna-events' ); ?>
				</button>
			</div>
		</form>
		<?php
	}
}
