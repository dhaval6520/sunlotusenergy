<?php

namespace The7\Mods\Compatibility\Elementor\Modules\Extended_Widgets;

use Elementor\Controls_Manager;
use Elementor\Controls_Stack;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Extend_Container {

	public function __construct() {
		// inject controls
		add_action( 'elementor/element/before_section_end', [ $this, 'update_controls' ], 20, 3 );
	}

	/**
	 * Before container end.
	 * Fires before Elementor container ends in the editor panel.
	 *
	 * @param Controls_Stack $widget     The control.
	 * @param string         $section_id Section ID.
	 * @param array          $args       Section arguments.
	 *
	 * @since 1.4.0
	 */
	public function update_controls( $widget, $section_id, $args ) {
		$widgets = [
			'container' => [
				'section_name' => [ 'section_layout' ],
			],
		];

		if ( ! array_key_exists( $widget->get_name(), $widgets ) ) {
			return;
		}

		$curr_section = $widgets[ $widget->get_name() ]['section_name'];
		if ( ! in_array( $section_id, $curr_section ) ) {
			return;
		}

		$widget->start_injection( [
			'of' => 'position_description',
			'at' => 'after',
		] );


		$widget->add_responsive_control( 'the7_size_fit_content', [
			'label'        => __( 'Fit Content', 'the7mk2' ),
			'type'         => Controls_Manager::SWITCHER,
			'separator'    => 'before',
			'selectors' => [
				'{{WRAPPER}}.e-container' => 'flex-basis: fit-content;',
			],
			'classes' => 'the7-control',
		] );

		$widget->end_injection();
	}
}
