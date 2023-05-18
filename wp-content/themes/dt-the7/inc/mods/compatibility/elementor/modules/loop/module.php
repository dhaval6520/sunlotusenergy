<?php
/**
 * Elementor tinymce extension.
 *
 * @package The7
 */

namespace The7\Mods\Compatibility\Elementor\Modules\Loop;

use Elementor\Plugin;
use ElementorPro\Modules\LoopBuilder\Documents\Loop as LoopDocument;
use The7\Mods\Compatibility\Elementor\The7_Elementor_Module_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor tinymce extension module.
 */
class Module extends The7_Elementor_Module_Base {

	/**
	/**
	 * Init module.
	 */
	public function __construct() {
		if ( the7_elementor_pro_is_active()) {
			add_action( 'elementor/editor/init', [ __CLASS__, 'add_loop_templates' ] );
			//add_filter( 'elementor/document/config', [ __CLASS__, 'add_document_config' ], 10, 2 );

		}
	}
//
//	public static function add_document_config($additional_config, $doc_id) {
//		$document = Plugin::instance()->documents->get_doc_for_frontend( $doc_id );
//		if ($document && LoopDocument::DOCUMENT_TYPE === $document::get_type() ) {
//			foreach ( static::WIDGETS_TO_HIDE as $widget_to_hide ) {
//				$config['panel']['widgets_settings'][ $widget_to_hide ] = [
//					'show_in_panel' => false,
//				];
//			}
//		}
//		return $additional_config;
//	}

	/**
	 * @return void
	 */
	public static function add_loop_templates() {
		Plugin::instance()->common->add_template( __DIR__ . '/views/cta-template.php' );
	}

	/**
	 * Get module name.
	 * Retrieve the module name.
	 *
	 * @access public
	 * @return string Module name.
	 */
	public function get_name() {
		return 'loop';
	}
}
