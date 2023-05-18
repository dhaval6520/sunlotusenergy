<?php
/**
 * Class Products
 *
 * @package The7\Mods\Compatibility\Elementor\Widgets\Woocommerce
 */

namespace The7\Mods\Compatibility\Elementor\Widgets\Woocommerce;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Typography;
use Elementor\Utils;
use stdClass;
use The7\Inc\Mods\Compatibility\WooCommerce\Front\Recently_Viewed_Products;
use The7\Mods\Compatibility\Elementor\Shortcode_Adapters\Query_Adapters\Products_Query;
use The7\Mods\Compatibility\Elementor\The7_Elementor_Less_Vars_Decorator_Interface;
use The7\Mods\Compatibility\Elementor\The7_Elementor_Widget_Base;
use The7\Mods\Compatibility\Elementor\Widget_Templates\General;
use The7\Mods\Compatibility\Elementor\Widget_Templates\Image_Aspect_Ratio;
use The7\Mods\Compatibility\Elementor\Widget_Templates\Image_Size;
use The7\Mods\Compatibility\Elementor\Widget_Templates\Pagination;
use The7\Mods\Compatibility\Elementor\Widget_Templates\Woocommerce\Add_To_Cart_Button;
use The7\Mods\Compatibility\Elementor\Widget_Templates\Woocommerce\Price;
use The7\Mods\Compatibility\Elementor\Widget_Templates\Woocommerce\Products_Query as Query;
use The7\Mods\Compatibility\Elementor\Widget_Templates\Woocommerce\Sale_Flash;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * Class Products
 *
 * @package The7\Mods\Compatibility\Elementor\Widgets\Woocommerce
 */
class Products extends The7_Elementor_Widget_Base {

	/**
	 * Get element name.
	 * Retrieve the element name.
	 *
	 * @return string The name.
	 */
	public function get_name() {
		return 'the7-wc-products';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	protected function the7_title() {
		return __( 'Products', 'the7mk2' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	protected function the7_icon() {
		return 'eicon-products';
	}

	/**
	 * Get the7 widget categories.
	 *
	 * @return string[]
	 */
	protected function the7_categories() {
		return [ 'woocommerce-elements' ];
	}

	/**
	 * Register widget assets.
	 *
	 * @see The7_Elementor_Widget_Base::__construct()
	 */
	protected function register_assets() {
		the7_register_style(
			'the7-wc-products',
			THE7_ELEMENTOR_CSS_URI . '/the7-wc-products-widget.css',
			[ 'the7-filter-decorations-base' ]
		);
		the7_register_script_in_footer(
			'the7-wc-products',
			THE7_ELEMENTOR_JS_URI . '/the7-woocommerce-list-variations.js',
			[ 'the7-elementor-frontend-common' ]
		);
	}

	/**
	 * Get style dependencies.
	 *
	 * Retrieve the list of style dependencies the element requires.
	 *
	 * @return array Element styles dependencies.
	 */
	public function get_style_depends() {
		return [ 'the7-wc-products' ];
	}

	/**
	 * Get script dependencies.
	 *
	 * Retrieve the list of script dependencies the element requires.
	 *
	 * @return array Element scripts dependencies.
	 */
	public function get_script_depends() {
		$scripts = [ 'the7-elementor-masonry', 'the7-wc-products' ];

		if ( $this->is_preview_mode() ) {
			$scripts[] = 'the7-elements-widget-preview';
		}

		return $scripts;
	}

	/**
	 * Render element.
	 *
	 * Generates the final HTML on the frontend.
	 */
	protected function render() {
		$this->print_inline_css();

		$settings = $this->get_settings_for_display();

		if ( $settings['query_post_type'] === 'recently_viewed' && ! $this->is_preview_mode() ) {
			Recently_Viewed_Products::track_via_js();
		}

		// Loop query.
		$query_builder = new Products_Query( $settings, 'query_' );
		$query         = $query_builder->create();

		if ( ! $query->have_posts() ) {
			if ( $settings['query_post_type'] === 'current_query' ) {
				$this->render_nothing_found_message();
			}
			$this->remove_hooks();
			return;
		}

		$this->setup_wrapper_class();
		$this->setup_wrapper_data_attributes();
		$this->template( Pagination::class )->add_containter_attributes( 'wrapper' );

		echo '<div ' . $this->get_render_attribute_string( 'wrapper' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $settings['show_widget_title'] === 'y' && $settings['widget_title_text'] ) {
			echo $this->display_widget_title( $settings['widget_title_text'], $settings['title_tag'] );
		}

		if ( $settings['mode'] === 'grid' ) {
			$class = 'dt-css-grid custom-pagination-handler';
		} else {
			$class = 'iso-container dt-isotope custom-iso-columns';
		}

		$this->add_render_attribute( 'inner_wrapper', 'class', $class );

		echo '<div ' . $this->get_render_attribute_string( 'inner_wrapper' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		wc_setup_loop(
			[
				'is_search'    => $query->is_search(),
				'is_filtered'  => is_filtered(),
				'total'        => $query->found_posts,
				'total_pages'  => $query->max_num_pages,
				'per_page'     => $query->get( 'posts_per_page' ),
				'current_page' => max( 1, $query->get( 'paged', 1 ) ),
			]
		);

		$post_limit = $this->template( Pagination::class )->get_post_limit();

		// Related to print_render_attribute_string( 'woo_buttons_on_img' ); .
		$this->setup_woo_buttons_on_image_attributes();

		// Start loop.
		global $product;

		while ( $query->have_posts() ) {
			$query->the_post();

			$product = wc_get_product( get_the_ID() );

			if ( ! $product ) {
				continue;
			}

			// Post visibility on the first page.
			$visibility = 'visible';
			if ( $post_limit >= 0 && $query->current_post >= $post_limit ) {
				$visibility = 'hidden';
			}

			$this->add_render_attribute( 'article_wrapper', 'class', $visibility, true );

			$this->setup_article_wrapper_attributes();

			echo '<div ' . $this->get_render_attribute_string( 'article_wrapper' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$this->set_article_render_attributes( 'article', $product );
			$this->add_render_attribute( 'article', 'class', 'project-odd' );

			/**
			 * Elements with render attributes:
			 *  - article
			 *  - woo_buttons_on_img
			 */
			$this->render_product_article( $product );

			echo '</div>';
		}

		wc_reset_loop();
		wp_reset_postdata();

		echo '</div>';

		$this->template( Pagination::class )->render( $query->max_num_pages );

		echo '</div>';

		$this->render_added_to_cart_icon_template();
		$this->remove_hooks();
	}

	/**
	 * Setup wrapper class attribute.
	 */
	protected function setup_wrapper_class() {
		$class = [
			'products-shortcode',
			'the7-elementor-widget',
			'loading-effect-none',
		];

		$settings = $this->get_settings_for_display();

		// Unique class.
		$class[] = $this->get_unique_class();

		$mode_classes = [
			'masonry' => 'mode-masonry',
			'grid'    => 'mode-grid dt-css-grid-wrap',
		];

		$mode = $settings['mode'];
		if ( array_key_exists( $mode, $mode_classes ) ) {
			$class[] = $mode_classes[ $mode ];
		}
		$class[] = the7_array_match(
			$settings['button_position'],
			[
				'below_image'    => 'cart-btn-below-img',
				'on_image'       => 'cart-btn-on-img',
			]
		);

		$class[] = the7_array_match(
			$settings['image_hover_style'],
			[
				'quick_scale' => 'quick-scale-img',
				'slow_scale'  => 'scale-img',
				'hover_image' => 'wc-img-hover',
			]
		);

		if ( $settings['responsiveness'] === 'browser_width_based' ) {
			$class[] = 'resize-by-browser-width';
		}

		$this->add_render_attribute( 'wrapper', 'class', $class );
	}

	/**
	 * Setup wrapper data attributes.
	 */
	protected function setup_wrapper_data_attributes() {
		$settings = $this->get_settings_for_display();

		$data_atts = [
			'data-padding'  => $this->combine_slider_value( $settings['gap_between_posts_adapter'] ),
			'data-cur-page' => the7_get_paged_var(),
		];

		$target_width = $settings['pwb_column_min_width'];
		if ( ! empty( $target_width['size'] ) ) {
			$data_atts['data-width'] = absint( $target_width['size'] );
		}

		if ( ! empty( $settings['pwb_columns'] ) ) {
			$data_atts['data-columns'] = absint( $settings['pwb_columns'] );
		}

		if ( $settings['responsiveness'] === 'browser_width_based' ) {
			$columns = [
				'wide-desktop' => $settings['widget_columns_wide_desktop'] ?: $settings['widget_columns'],
				'desktop'      => $settings['widget_columns'],
				'v-tablet'     => $settings['widget_columns_tablet'],
				'phone'        => $settings['widget_columns_mobile'],
			];

			foreach ( $columns as $column => $val ) {
				$data_atts[ 'data-' . $column . '-columns-num' ] = esc_attr( $val );
			}
		}

		$this->add_render_attribute( 'wrapper', $data_atts );
	}

	/**
	 * Setup image wrapper render attributes.
	 */
	protected function setup_woo_buttons_on_image_attributes() {
		if ( $this->get_settings_for_display( 'image_hover_trigger' ) === 'image' ) {
			$this->add_render_attribute( 'woo_buttons_on_img', 'class', 'trigger-img-hover' );
		}

		$this->add_render_attribute( 'woo_buttons_on_img', 'class', 'woo-buttons-on-img' );
	}

	/**
	 * Setup article wrapper attribute.
	 */
	protected function setup_article_wrapper_attributes() {
		global $post;
		global $product;

		$settings = $this->get_settings_for_display();

		$class = [
			'wf-cell',
			'product-wrap',
		];

		if ( $settings['mode'] === 'masonry' ) {
			$class[] = 'iso-item';
		}

		if ( $this->is_show_product_variations( $product ) ) {
			$class[] = 'show-variations-y';
		}

		$this->add_render_attribute( 'article_wrapper', 'class', $class );
		$this->add_render_attribute( 'article_wrapper', 'data-post-id', $post->ID, true );
	}

	/**
	 * Remove nasty hooks.
	 */
	protected function remove_hooks() {
		if ( $this->get_settings_for_display( 'query_post_type' ) === 'top' ) {
			remove_filter( 'posts_clauses', [ 'WC_Shortcodes', 'order_by_rating_post_clauses' ] );
		}
	}

	/**
	 * @param WC_Product $product Product.
	 */
	protected function render_product_image( $product ) {
		$settings = $this->get_settings_for_display();

		$this->template( Sale_Flash::class )->render_sale_flash();

		$img_wrapper_class = implode( ' ', array_filter( [
			'alignnone',
			'img-wrap',
			$this->template( Image_Size::class )->get_wrapper_class(),
			$this->template( Image_Aspect_Ratio::class )->get_wrapper_class(),
		] ) );

		echo '<div class="img-border">';
		echo '<a href="' . esc_url( get_permalink() ) . '" class="' . esc_attr( $img_wrapper_class ) . '">';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->template( Image_Size::class )->apply_filters(
			function ( $size ) use ( $product, $settings ) {
				$result = woocommerce_get_product_thumbnail( $size );
				if ( $settings['image_hover_style'] === 'hover_image' ) {
					$result .= the7_wc_get_the_first_product_gallery_image_html( $product, $size );
				}

				return $result;
			}
		);

		echo '</a>';
		echo '</div>';

		if ( in_array( $this->get_settings_for_display( 'variations_position' ), [ 'on_image', 'on_image_hover' ], true ) ) {
			$this->render_product_variations( $product );
		}

		if ( $this->is_show_add_to_cart() && $settings['button_position'] !== 'below_image' ) {
			$this->render_add_to_cart_button( $product );
		}

		if ( ! $product->is_in_stock() ) {
			echo '<span class="out-stock-label">' . esc_html__( 'Out Of Stock', 'the7mk2' ) . '</span>';
		}

		the7_ti_wishlist_button();
	}

	/**
	 * @param WC_Product $product Product.
	 */
	public function render_product_variations( $product ) {
		$settings = $this->get_settings_for_display();
		if ( $this->is_show_product_variations( $product ) ) {
			$product_attributes = $product->get_attributes();

			/**
			 * @var WC_Product_Variation[] $available_variations
			 */
			$available_variations     = $product->get_available_variations( 'objects' );
			$variations_per_attribute = [];
			foreach ( $available_variations as $variation ) {
				$variation_attributes = $variation->get_variation_attributes( false );
				foreach ( $variation_attributes as $attribute_slug => $variation_slug ) {
					$variations_per_attribute[ $attribute_slug ][ $variation_slug ] = $variation;
				}
			}
			$wrap_class = '';
			if ( $this->is_show_add_to_cart() && $this->is_wc_ajax_add_to_cart_enabled() ) {
				$wrap_class = 'ajax-variation-enabled';
			}
			echo '<div class="products-variations-wrap ' . $wrap_class . ' ">';
			foreach ( $product_attributes as $attribute_slug => $attribute ) {
				/**
				 * @var WC_Product_Attribute $attribute
				 */

				if ( ! isset( $variations_per_attribute[ $attribute_slug ] ) ) {
					continue;
				}

				$attribute_taxonomy = $attribute->get_taxonomy_object();
				if ( $attribute_taxonomy ) {
					// Get terms if this is a taxonomy - ordered. We need the names too.
					$terms = wc_get_product_terms( $product->get_id(), $attribute_slug, [ 'fields' => 'all' ] );

					if ( ! $terms ) {
						continue;
					}

					$attribute_label = $attribute_taxonomy->attribute_label;
				} else {
					$options = $attribute->get_options();
					$terms   = [];
					foreach ( $options as $option ) {
						$term       = new stdClass();
						$term->name = ucfirst( $option );
						$term->slug = $option;
						$terms[]    = $term;
					}

					$attribute_label = $attribute->get_name();
				}

				$prefixed_attribute_slug = 'attribute_' . $attribute_slug;

				echo '<div class="product-variation-row">';
				echo '<span>' . esc_html( $attribute_label ) . '</span>';
				echo '<ul class="products-variations" data-atr="' . esc_attr( $prefixed_attribute_slug ) . '">';

				foreach ( $terms as $term ) {
					$name                = $term->name;
					$attribute_plus_name = $prefixed_attribute_slug . '_' . $term->slug;
					$href                = add_query_arg( [ $prefixed_attribute_slug => $term->slug ], $product->get_permalink() );
					echo '<li><a href="' . esc_url( $href ) . '" data-id="' . esc_attr( $term->slug ) . '" class="' . esc_attr( $attribute_plus_name ) . '">' . esc_html( $name ) . '</a></li>';
				}

				echo '</ul>';
				echo '</div>';
			}

			echo '</div>';
		}
	}

	/**
	 * @param WC_Product $product Product.
	 */
	public function render_product_title( $product ) {
		$settings = $this->get_settings_for_display();

		$html_tag = Utils::validate_html_tag( $this->get_settings_for_display( 'product_title_tag' ) );

		$class = implode(
			' ',
			[
				'product-title',
				( $settings['product_title_width'] === 'crp-to-line' ? 'one-line' : '' ),
			]
		);

		echo "<{$html_tag} class=\"{$class}\">"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$product_name = $product->get_name();
		if ( $settings['product_title_words_limit'] && $settings['product_title_width'] === 'normal' ) {
			$product_name = wp_trim_words( $product_name, $settings['product_title_words_limit'] );
		}

		printf(
			'<a href="%s" title="%s" rel="bookmark">%s</a>',
			esc_url( $product->get_permalink() ),
			the_title_attribute( [ 'echo' => false ] ),
			esc_html( $product_name )
		);
		echo "</{$html_tag}>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param string $text Title text.
	 * @param string $tag Tag.
	 *
	 * @return string
	 */
	protected function display_widget_title( $text, $tag = 'h3' ) {

		$tag = Utils::validate_html_tag( $tag );

		$output  = '<' . $tag . ' class="rp-heading">';
		$output .= esc_html( $text );
		$output .= '</' . $tag . '>';

		return $output;
	}

	/**
	 * @param WC_Product $product Product.
	 */
	protected function render_short_description( $product ) {
		$settings = $this->get_settings_for_display();

		$class = implode(
			' ',
			[
				'woocommerce-product-details__short-description',
				( $settings['description_width'] === 'crp-to-line' ? 'one-line' : '' ),
			]
		);

		$short_description = $product->get_short_description();

		if ( ! $short_description ) {
			$short_description = $product->get_description();
		}

		if ( $settings['description_words_limit'] && $settings['description_width'] === 'normal' ) {
			$short_description = wp_trim_words( $short_description, $settings['description_words_limit'] );
		}
		if ( ! empty( $short_description ) ) {
			printf(
				'<div class="%s">%s</div>',
				esc_attr( $class ),
				wp_kses_post( $short_description )
			);
		}
	}

	/**
	 * @param WC_Product $product Product.
	 */
	protected function render_add_to_cart_content_button( $product ) {
		if ( ! $product ) {
			return;
		}

		echo '<div class="woo-list-buttons">';

		// Cleanup button render attributes.
		$this->remove_render_attribute( 'box-button' );

		if ( $this->is_show_product_variations( $product ) ) {
			// Add ajax handler if it is enabled.
			if ( $this->is_wc_ajax_add_to_cart_enabled() ) {
				$this->add_render_attribute(
					'box-button',
					[
						'class' => 'ajax_add_to_cart variation-btn-disabled',
					]
				);
			}
			$button_text = esc_html__( 'Add to cart', 'the7mk2' );
		} else {
			$button_text = esc_html( $product->add_to_cart_text() );
		}
		$button_text .= $this->get_product_add_to_cart_icon_html( $product, 'elementor-button-icon' );

		$this->template( Add_To_Cart_Button::class )->render_button( 'box-button', $button_text, 'a', $product );

		echo '</div>';
	}

	/**
	 * Render added to cart icon template if any.
	 */
	protected function render_added_to_cart_icon_template() {
		$added_to_cart_icon = $this->get_button_icon_html( $this->get_add_to_cart_icon_setting( 'added_to_cart_icon' ) );
		if ( $added_to_cart_icon ) {
			// Render "Added to Cart" icon as a template.
			echo '<div class="hidden added-to-cart-icon-template">';
			echo $added_to_cart_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
		}
	}

	/**
	 * @param WC_Product $product Product.
	 */
	protected function render_add_to_cart_on_image_button( $product ) {
		if ( ! $product ) {
			return;
		}
		$settings = $this->get_settings_for_display();

		// Cleanup button render attributes.
		$this->remove_render_attribute( 'button' );

		$this->template( Add_To_Cart_Button::class )->add_render_attributes( 'button', $product );
		$this->add_render_attribute( 'button', 'class', 'woo-popup-button box-button elementor-button' );

		$button_icon = $this->get_product_add_to_cart_icon_html( $product, 'popup-icon' );
		$button_text = '';
		if ( $settings['layout'] === 'icon_with_text' ) {
			if ( $this->is_show_product_variations( $product ) ) {
				// Add ajax handler if it is enabled.

				$button_text = esc_html__( 'Add to cart', 'the7mk2' );
			} else {
				$button_text = esc_html( $product->add_to_cart_text() );
			}
			$button_text = sprintf(
				'<span class="filter-popup">%s</span>',
				$button_text
			);
		}
		if ( $this->is_wc_ajax_add_to_cart_enabled() && $this->is_show_product_variations( $product ) ) {
			$this->add_render_attribute(
				'button',
				[
					'class' => 'ajax_add_to_cart variation-btn-disabled',
				]
			);
		}
		echo '<div class="woo-list-buttons">';
		echo '<a ' . $this->get_render_attribute_string( 'button' ) . '>' . $button_text . $button_icon . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	/**
	 * @param WC_Product $product Product.
	 * @param string     $class Icon class attribute value.
	 *
	 * @return array|null
	 */
	protected function get_product_add_to_cart_icon_html( $product, $class ) {
		if ( in_array( $product->get_type(), [ 'variable', 'grouped' ], true ) && ! $this->is_show_variations() ) {
			$icon_setting = $this->get_add_to_cart_icon_setting( 'options_icon' );
		} else {
			$icon_setting = $this->get_add_to_cart_icon_setting();
		}

		return $this->get_button_icon_html( $icon_setting, $class );
	}

	/**
	 * Used as a general entry point to the button icons. Return 'add_to_cart_icon' by default.
	 *
	 * @param string $custom_icon_setting_key Setting key.
	 *
	 * @return array|null
	 */
	protected function get_add_to_cart_icon_setting( $custom_icon_setting_key = null ) {
		if ( $custom_icon_setting_key ) {
			$custom_icon = $this->get_settings_for_display( $custom_icon_setting_key );
			if ( ! empty( $custom_icon['value'] ) ) {
				return $custom_icon;
			}
		}

		return $this->get_settings_for_display( 'add_to_cart_icon' );
	}

	/**
	 * @param  array|null $icon  Icon setting value.
	 * @param  string     $class  CSS class of the icon.
	 *
	 * @return mixed|string
	 */
	protected function get_button_icon_html( $icon, $class = '' ) {
		$icon_html = $this->get_elementor_icon_html( $icon, 'i', [ 'class' => $class ] );

		/**
		 * "Icon on image" skin only.
		 *
		 * Add additional wrapper for svg and empty "add to cart" icon (we have to show something).
		 */
		if ( ( empty( $icon['library'] ) || $icon['library'] === 'svg' ) && ( $this->get_settings_for_display( 'layout' ) !== 'content_below_img' ) ) {
			$icon_html = '<span class="svg-background popup-icon">' . $icon_html . '</span>';
		}

		return $icon_html;
	}

	/**
	 * Return shortcode less file absolute path to output inline.
	 *
	 * @return string
	 */
	protected function get_less_file_name() {
		return PRESSCORE_THEME_DIR . '/css/dynamic-less/elementor/the7-wc-products.less';
	}

	/**
	 * Defines vars for the dynamic less file.
	 */
	protected function less_vars( The7_Elementor_Less_Vars_Decorator_Interface $less_vars ) {
		$settings = $this->get_settings_for_display();

		if ( ! empty( $settings['widget_columns_wide_desktop_breakpoint'] ) ) {
			$less_vars->add_pixel_number( 'wide-desktop-width', $settings['widget_columns_wide_desktop_breakpoint'] );
		}
	}

	/**
	 * @param WC_Product $product WC_Product object.
	 *
	 * @return bool
	 */
	protected function is_show_product_variations( WC_Product $product ) {
		return $product->get_type() === 'variable' && $this->is_show_variations();
	}

	/**
	 * @return bool
	 */
	protected function is_show_variations() {
		return ( $this->get_settings_for_display( 'show_variations' ) === 'y' || $this->get_settings_for_display( 'show_variations_tablet' ) === 'y' || $this->get_settings_for_display( 'show_variations_mobile' ) === 'y' );
	}

	/**
	 * @return bool
	 */
	protected function is_show_add_to_cart() {
		return ( $this->get_settings_for_display( 'show_add_to_cart' ) === 'y' || $this->get_settings_for_display( 'show_add_to_cart_tablet' ) === 'y' || $this->get_settings_for_display( 'show_add_to_cart_mobile' ) === 'y' );
	}

	/**
	 * @return bool
	 */
	protected function is_wc_ajax_add_to_cart_enabled() {
		return get_option( 'woocommerce_enable_ajax_add_to_cart' ) === 'yes';
	}

	/**
	 * @param WC_Product $product Product.
	 * @param string     $element Element.
	 *
	 * @return void
	 */
	protected function maybe_add_product_variations_data_to_element( WC_Product $product, $element ) {
		// Cleanup attribute definition.
		$this->remove_render_attribute( $element, 'data-product_variations' );

		if ( $this->is_show_product_variations( $product ) ) {
			$available_variations = $product->get_available_variations();
			$available_variations = array_map(
				function ( $variation ) {
					$fields_white_list = [
						'attributes',
						'variation_id',
						'is_in_stock',
						'sku',
						'price_html',
					];

					return array_intersect_key( $variation, array_flip( $fields_white_list ) );
				},
				$available_variations
			);

			$this->add_render_attribute( $element, 'data-product_variations', wp_json_encode( $available_variations ), true );
			$this->add_render_attribute( $element, 'data-post-id', $product->get_id(), true );
		}
	}

	/**
	 * @param  WC_Product $product Product.
	 *
	 * @return void
	 */
	protected function render_add_to_cart_button( WC_Product $product ) {
		if ( $this->get_settings_for_display( 'layout' ) !== 'content_below_img' ) {
			$this->render_add_to_cart_on_image_button( $product );
		} else {
			$this->render_add_to_cart_content_button( $product );
		}
	}

	/**
	 * @param  string     $element  Element.
	 * @param  WC_Product $product  Product.
	 *
	 * @return void
	 */
	protected function set_article_render_attributes( $element, WC_Product $product ) {
		$this->maybe_add_product_variations_data_to_element( $product, $element );

		$this->add_render_attribute(
			$element,
			'class',
			wc_get_product_class(
				[
					'post',
					'visible',
				],
				$product->get_id()
			),
			true
		);
	}

	/**
	 * @param  WC_Product $product Product.
	 *
	 * @return void
	 */
	protected function render_product_article( WC_Product $product ) {
		$settings = $this->get_settings_for_display();

		if ( $settings['image_hover_trigger'] === 'box' ) {
			$this->add_render_attribute( 'article', 'class', 'trigger-img-hover' );
		}
		?>

		<article <?php $this->print_render_attribute_string( 'article' ); ?>>
			<figure class="woocom-project">
				<div <?php $this->print_render_attribute_string( 'woo_buttons_on_img' ); ?>>

					<?php $this->render_product_image( $product ); ?>

				</div>
				<figcaption class="woocom-list-content">

					<?php
					if ( in_array( $this->get_settings_for_display( 'variations_position' ), [ 'below_image', 'below_content' ], true ) ) {
						$this->render_product_variations( $product );
					}

					do_action( 'woocommerce_before_shop_loop_item' );

					if ( $settings['show_product_title'] ) {
						$this->render_product_title( $product );
					}

					$this->template( Price::class )->render_product_price( $product );

					if ( $settings['show_rating'] && wc_review_ratings_enabled() ) {
						$price_html = wc_get_rating_html( $product->get_average_rating() );
						if ( $price_html ) {
							echo '<div class="star-rating-wrap">' . $price_html . '</div>'; // PHPCS:Ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					}

					if ( $settings['show_short_description'] ) {
						$this->render_short_description( $product );
					}

					if ( $this->is_show_add_to_cart() && $settings['button_position'] === 'below_image' ) {
						$this->render_add_to_cart_button( $product );
					}

					do_action( 'woocommerce_after_shop_loop_item' );
					?>

				</figcaption>
			</figure>
		</article>

		<?php
	}

	/**
	 * Register widget controls.
	 */
	protected function register_controls() {
		// Content.
		$this->template( Query::class )->add_query_controls();

		/**
		 * Remove current query info because the widget can control query pagination.
		 *
		 * @since 11.1.0
		 *
		 * @see   Query::add_query_group_control()
		 */
		$this->remove_control( 'current_query_info' );

		$this->add_layout_controls();
		$this->add_content_controls();
		$this->add_variations_controls();
		$this->template( Pagination::class )->add_content_controls( 'query_post_type' );

		/**
		 * Inject archive posts per page control.
		 */
		$this->start_injection(
			[
				'type' => 'control',
				'at'   => 'before',
				'of'   => 'loading_mode',
			]
		);

		/**
		 * Add archive posts_per_page setting.
		 *
		 * @see Custom_Pagination_Query_Handler::handle_archive_and_search_posts_per_page()
		 */
		$this->add_control(
			'archive_posts_per_page',
			[
				'label'       => __( 'Number Of Products On One Page', 'the7mk2' ),
				'description' => __( 'Leave empty to display default archive products amount.', 'the7mk2' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => '',
				'condition'   => [
					'query_post_type' => 'current_query',
				],
			]
		);

		$this->end_injection();

		// Style.
		$this->add_widget_title_style_controls();
		$this->template( General::class )->add_box_style_controls();
		$this->add_image_style_controls();
		$this->add_content_style_controls();
		$this->template( Sale_Flash::class )->add_style_controls();
		$this->add_title_style_controls();
		$this->template( Price::class )->add_style_controls();
		$this->add_rating_style_controls();
		$this->add_short_description_style_controls();
		$this->add_variations_style_controls();
		$this->add_button_style_controls();
		$this->template( Pagination::class )->add_style_controls( 'query_post_type' );
	}

	/**
	 * Add widget title style controls.
	 */
	protected function add_widget_title_style_controls() {
		$this->start_controls_section(
			'widget_style_section',
			[
				'label'     => __( 'Widget Title', 'the7mk2' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_widget_title' => 'y',
				],
			]
		);

		$this->add_basic_responsive_control(
			'widget_title_align',
			[
				'label'     => __( 'Alignment', 'the7mk2' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left'   => [
						'title' => __( 'Left', 'the7mk2' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'the7mk2' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'the7mk2' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}} .rp-heading' => 'text-align: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'widget_title_typography',
				'selector' => '{{WRAPPER}} .rp-heading',
			]
		);

		$this->add_control(
			'widget_title_color',
			[
				'label'     => __( 'Font Color', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'alpha'     => true,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .rp-heading' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'widget_title_bottom_margin',
			[
				'label'      => __( 'Spacing Below Title', 'the7mk2' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => [
					'unit' => 'px',
					'size' => 20,
				],
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min'  => 0,
						'max'  => 200,
						'step' => 1,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .rp-heading' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Register layout controls.
	 */
	protected function add_layout_controls() {
		$this->start_controls_section(
			'layout_section',
			[
				'label' => __( 'Layout', 'the7mk2' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_widget_title',
			[
				'label'        => __( 'Widget Title', 'the7mk2' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'the7mk2' ),
				'label_off'    => __( 'Hide', 'the7mk2' ),
				'return_value' => 'y',
				'default'      => '',
			]
		);

		$this->add_control(
			'widget_title_text',
			[
				'label'     => __( 'Title', 'the7mk2' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => 'Widget title',
				'condition' => [
					'show_widget_title' => 'y',
				],
			]
		);

		$this->add_control(
			'title_tag',
			[
				'label'     => __( 'Title HTML Tag', 'the7mk2' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => [
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				],
				'default'   => 'h3',
				'condition' => [
					'show_widget_title' => 'y',
				],
			]
		);

		$this->add_control(
			'mode',
			[
				'label'     => __( 'Mode', 'the7mk2' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'grid',
				'options'   => [
					'masonry' => 'Masonry',
					'grid'    => 'Grid',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'responsiveness',
			[
				'label'     => __( 'Responsiveness mode', 'the7mk2' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'browser_width_based',
				'options'   => [
					'browser_width_based' => 'Browser width based',
					'post_width_based'    => 'Post width based',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'widget_columns_wide_desktop',
			[
				'label'       => esc_html__( 'Columns On A Wide Desktop', 'the7mk2' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => '',
				'min'         => 1,
				'max'         => 12,
				'selectors'   => [
					'{{WRAPPER}} .dt-css-grid' => '--wide-desktop-columns: {{SIZE}}',
				],
				'render_type' => 'template',
				'condition'   => [
					'responsiveness' => 'browser_width_based',
				],
			]
		);

		$this->add_control(
			'widget_columns_wide_desktop_breakpoint',
			[
				'label'              => esc_html__( 'Wide Desktop Breakpoint (px)', 'the7mk2' ),
				'description'        => the7_elementor_get_wide_columns_control_description(),
				'type'               => Controls_Manager::NUMBER,
				'default'            => '',
				'min'                => 0,
				'frontend_available' => true,
				'condition'          => [
					'responsiveness' => 'browser_width_based',
				],
			]
		);

		$this->add_basic_responsive_control(
			'widget_columns',
			[
				'label'          => __( 'Columns', 'the7mk2' ),
				'type'           => Controls_Manager::NUMBER,
				'default'        => 3,
				'tablet_default' => 2,
				'mobile_default' => 1,
				'selectors'      => [
					'{{WRAPPER}} .dt-css-grid' => 'grid-template-columns: repeat({{SIZE}},minmax(0, 1fr))',
					'{{WRAPPER}}'              => '--wide-desktop-columns: {{SIZE}}',
				],
				'render_type'    => 'template',
				'condition'      => [
					'responsiveness' => 'browser_width_based',
				],
			]
		);

		$this->template( Image_Size::class )->add_style_controls();

		$this->add_control(
			'pwb_column_min_width',
			[
				'label'       => __( 'Column minimum width', 'the7mk2' ),
				'type'        => Controls_Manager::SLIDER,
				'default'     => [
					'unit' => 'px',
					'size' => 300,
				],
				'size_units'  => [ 'px' ],
				'range'       => [
					'px' => [
						'min'  => 0,
						'max'  => 500,
						'step' => 1,
					],
				],
				'selectors'   => [
					'{{WRAPPER}} .dt-css-grid' => 'grid-template-columns: repeat(auto-fill, minmax({{SIZE}}{{UNIT}}, 1fr));',
				],
				'render_type' => 'template',
				'condition'   => [
					'responsiveness' => 'post_width_based',
				],
			]
		);

		$this->add_control(
			'pwb_columns',
			[
				'label'     => __( 'Desired columns number', 'the7mk2' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 3,
				'min'       => 1,
				'max'       => 12,
				'condition' => [
					'mode'           => 'masonry',
					'responsiveness' => 'post_width_based',
				],
			]
		);

		$this->add_control(
			'gap_between_posts_adapter',
			[
				'label'       => __( 'Gap between columns', 'the7mk2' ),
				'description' => __( 'Please note that this setting affects post paddings. So, for example: a value 10px will give you 20px gaps between posts)', 'the7mk2' ),
				'type'        => Controls_Manager::SLIDER,
				'default'     => [
					'unit' => 'px',
					'size' => 15,
				],
				'size_units'  => [ 'px' ],
				'range'       => [
					'px' => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'condition'   => [
					'mode' => 'masonry',
				],
				'separator'   => 'before',
			]
		);

		$this->add_basic_responsive_control(
			'columns_gap',
			[
				'label'      => __( 'Columns Gap', 'the7mk2' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'default'    => [
					'size' => '30',
				],
				'range'      => [
					'px' => [
						'max' => 100,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .dt-css-grid' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
				],
				'condition'  => [
					'mode' => 'grid',
				],
				'separator'  => 'before',
			]
		);

		$this->add_basic_responsive_control(
			'rows_gap',
			[
				'label'      => __( 'Rows Gap', 'the7mk2' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'default'    => [
					'size' => '30',
				],
				'range'      => [
					'px' => [
						'max' => 100,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .dt-css-grid' => 'grid-row-gap: {{SIZE}}{{UNIT}};',
				],
				'condition'  => [
					'mode' => 'grid',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Register content controls.
	 */
	protected function add_content_controls() {
		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Product Content', 'the7mk2' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_product_title',
			[
				'label'        => __( 'Title', 'the7mk2' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'the7mk2' ),
				'label_off'    => __( 'Hide', 'the7mk2' ),
				'return_value' => 'y',
				'default'      => 'y',
			]
		);

		$this->add_control(
			'product_title_tag',
			[
				'label'     => __( 'Title HTML Tag', 'the7mk2' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => [
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				],
				'default'   => 'h4',
				'condition' => [
					'show_product_title' => 'y',
				],
			]
		);

		$this->add_control(
			'product_title_width',
			[
				'label'       => __( 'Title Width', 'the7mk2' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => [
					'normal'      => __( 'Normal', 'the7mk2' ),
					'crp-to-line' => __( 'Crop to one line', 'the7mk2' ),
				],
				'default'     => 'normal',
				'condition'   => [
					'show_product_title' => 'y',
				],
				'render_type' => 'template',
			]
		);

		$this->add_control(
			'product_title_words_limit',
			[
				'label'       => __( 'Maximum Number Of Words', 'the7mk2' ),
				'description' => __( 'Leave empty to show the entire title.', 'the7mk2' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => '',
				'min'         => 1,
				'max'         => 20,
				'condition'   => [
					'show_product_title'  => 'y',
					'product_title_width' => 'normal',
				],
			]
		);

		$this->template( Price::class )->add_switch_control();

		$this->add_control(
			'show_rating',
			[
				'label'        => __( 'Rating', 'the7mk2' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'the7mk2' ),
				'label_off'    => __( 'Hide', 'the7mk2' ),
				'return_value' => 'y',
				'default'      => 'y',
				'separator'    => 'before',
			]
		);

		$this->add_control(
			'show_short_description',
			[
				'label'        => __( 'Short Description', 'the7mk2' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'the7mk2' ),
				'label_off'    => __( 'Hide', 'the7mk2' ),
				'return_value' => 'y',
				'default'      => 'y',
				'separator'    => 'before',
			]
		);

		$this->add_control(
			'description_width',
			[
				'label'       => __( 'Width', 'the7mk2' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => [
					'normal'      => __( 'Normal', 'the7mk2' ),
					'crp-to-line' => __( 'Crop to one line', 'the7mk2' ),
				],
				'default'     => 'normal',
				'condition'   => [
					'show_short_description' => 'y',
				],
				'render_type' => 'template',
			]
		);

		$this->add_control(
			'description_words_limit',
			[
				'label'       => __( 'Maximum Number Of Words', 'the7mk2' ),
				'description' => __( 'Leave empty to show the entire title.', 'the7mk2' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => '',
				'min'         => 1,
				'max'         => 20,
				'condition'   => [
					'show_short_description' => 'y',
					'description_width'      => 'normal',
				],
			]
		);

		$this->template( Sale_Flash::class )->add_switch_control();

		$this->end_controls_section();
	}

	/**
	 * Register variations controls.
	 */
	protected function add_variations_controls() {
		$this->start_controls_section(
			'variations_section',
			[
				'label' => __( 'Variations & Add to cart', 'the7mk2' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'variations_heading',
			[
				'label' => __( 'Variations', 'the7mk2' ),
				'type'  => Controls_Manager::HEADING,
			]
		);

		$variations_show_options = [
			'y' => __( 'Show', 'the7mk2' ),
			'n' => __( 'Hide', 'the7mk2' ),
		];
		$device_variations_show_options = [ '' => __( 'No change', 'the7mk2' ) ] + $variations_show_options;

		$this->add_basic_responsive_control(
			'show_variations',
			[
				'label'        => __( 'Visibility', 'the7mk2' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'n',
				'options'      => $variations_show_options,
				'device_args'  => [
					'tablet' => [
						'options' => $device_variations_show_options,
					],
					'mobile' => [
						'options' => $device_variations_show_options,
					],
				],
				'selectors_dictionary' => [
					'y' => 'flex',
					'n' => 'none',
				],
				'selectors'            => [
					'{{WRAPPER}} .products-variations-wrap' => 'display:{{VALUE}};',
				],
				'render_type'  => 'template',
				'frontend_available' => true,
				'prefix_class' => 'variations-visible%s-',
			]
		);

		$this->add_control(
			'variations_position',
			[
				'label'        => __( 'Position', 'the7mk2' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'below_content',
				'options'      => [
					'on_image'       => 'On image',
					'below_image'    => 'Before content',
					'below_content'  => 'After content',
				],
				'render_type'  => 'template',
				'prefix_class' => 'variations-position-',
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[
							'name'  => 'show_variations',
							'value' => 'y',
						],
						[
							'name'  => 'show_variations_tablet',
							'value' => 'y',
						],
						[
							'name'  => 'show_variations_mobile',
							'value' => 'y',
						],
					],
				],
			]
		);

		$variations_visibility_options        = [
			'always'   => __( 'Always', 'the7mk2' ),
			'on-hover' => __( 'On image (box hover)', 'the7mk2' ),
		];
		$device_variations_visibility_options = [ '' => __( 'No change', 'the7mk2' ) ] + $variations_visibility_options;

		$this->add_basic_responsive_control(
			'show_variations_on_hover',
			[
				'label'        => __( 'Display', 'the7mk2' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'always',
				'options'      => $variations_visibility_options,
				'device_args'  => [
					'tablet' => [
						'options' => $device_variations_visibility_options,
					],
					'mobile' => [
						'options' => $device_variations_visibility_options,
					],
				],
				'selectors'            => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'always' => '--variations-opacity:1;',
					'on-hover'  => '--variations-opacity:0;',
				],
				'frontend_available' => true,
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'variations_position',
									'operator' => '==',
									'value'    => 'on_image',
								],
								[
									'name'     => 'show_variations',
									'operator' => '==',
									'value'    => 'y',
								],
							],
						],
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'variations_position',
									'operator' => '==',
									'value'    => 'on_image',
								],
								[
									'name'     => 'show_variations_tablet',
									'operator' => '==',
									'value'    => 'y',
								],
							],
						],
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'variations_position',
									'operator' => '==',
									'value'    => 'on_image',
								],
								[
									'name'     => 'show_variations_mobile',
									'operator' => '==',
									'value'    => 'y',
								],
							],
						],
					],
				],
			]
		);

		$this->add_control(
			'skins_heading',
			[
				'label'     => __( 'Add to cart', 'the7mk2' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$add_to_cart_show_options        = [
			'y'   => __( 'Show', 'the7mk2' ),
			'n' => __( 'Hide', 'the7mk2' ),
		];
		$device_add_to_cart_show_options = [ '' => __( 'No change', 'the7mk2' ) ] + $add_to_cart_show_options;

		$this->add_basic_responsive_control(
			'show_add_to_cart',
			[
				'label'        => __( 'Visibility', 'the7mk2' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'y',
				'options'      => $add_to_cart_show_options,
				'device_args'  => [
					'tablet' => [
						'options' => $device_add_to_cart_show_options,
					],
					'mobile' => [
						'options' => $device_add_to_cart_show_options,
					],
				],
				'selectors_dictionary' => [
					'y'  => 'flex',
					'n' => 'none',
				],
				'selectors'            => [
					'{{WRAPPER}} .woo-list-buttons' => 'display:{{VALUE}};',
				],
				'frontend_available' => true,
			]
		);

		$this->add_control(
			'layout',
			[
				'label'        => __( 'Style', 'the7mk2' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'content_below_img',
				'options'      => [
					'content_below_img' => __( 'Button', 'the7mk2' ),
					'btn_on_img'        => __( 'Icon', 'the7mk2' ),
					'icon_with_text'    => __( 'Icon + Text on hover', 'the7mk2' ),
				],
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[
							'name'  => 'show_add_to_cart',
							'value' => 'y',
						],
						[
							'name'  => 'show_add_to_cart_tablet',
							'value' => 'y',
						],
						[
							'name'  => 'show_add_to_cart_mobile',
							'value' => 'y',
						],
					],
				],
				'render_type'  => 'template',
				'prefix_class' => 'layout-',
			]
		);

		$this->add_control(
			'button_position',
			[
				'label'        => __( 'Position', 'the7mk2' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'below_image',
				'options'      => [
					'on_image'       => 'On image',
					'below_image'    => 'After content',
				],
				'render_type'  => 'template',
				'prefix_class' => 'btn-position-',
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[
							'name'  => 'show_add_to_cart',
							'value' => 'y',
						],
						[
							'name'  => 'show_add_to_cart_tablet',
							'value' => 'y',
						],
						[
							'name'  => 'show_add_to_cart_mobile',
							'value' => 'y',
						],
					],
				],
			]
		);

		$btn_visibility_options        = [
			'always'   => __( 'Always', 'the7mk2' ),
			'on-hover' => __( 'On image (box hover)', 'the7mk2' ),
		];
		$device_btn_visibility_options = [ '' => __( 'No change', 'the7mk2' ) ] + $btn_visibility_options;

		$this->add_basic_responsive_control(
			'show_btn_on_hover',
			[
				'label'        => __( 'Display', 'the7mk2' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'always',
				'options'      => $btn_visibility_options,
				'device_args'  => [
					'tablet' => [
						'options' => $device_btn_visibility_options,
					],
					'mobile' => [
						'options' => $device_btn_visibility_options,
					],
				],
				'prefix_class' => 'show-btn%s-',
				'selectors'            => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'selectors_dictionary' => [
					'always' => '--btn-opacity:1;',
					'on-hover'  => '--btn-opacity:0;',
				],
				'frontend_available' => true,
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'button_position',
									'operator' => '==',
									'value'    => 'on_image',
								],
								[
									'name'     => 'show_add_to_cart',
									'operator' => '==',
									'value'    => 'y',
								],
							],
						],
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'button_position',
									'operator' => '==',
									'value'    => 'on_image',
								],
								[
									'name'     => 'show_add_to_cart_tablet',
									'operator' => '==',
									'value'    => 'y',
								],
							],
						],
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'button_position',
									'operator' => '==',
									'value'    => 'on_image',
								],
								[
									'name'     => 'show_add_to_cart_mobile',
									'operator' => '==',
									'value'    => 'y',
								],
							],
						],
					],
				],
			]
		);

		$this->add_control(
			'icons_heading',
			[
				'label'     => __( 'Icon', 'the7mk2' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[
							'name'  => 'show_add_to_cart',
							'value' => 'y',
						],
						[
							'name'  => 'show_add_to_cart_tablet',
							'value' => 'y',
						],
						[
							'name'  => 'show_add_to_cart_mobile',
							'value' => 'y',
						],
					],
				],
			]
		);

		$this->add_control(
			'add_to_cart_icon',
			[
				'label'       => __( '"Add To Cart" Icon', 'the7mk2' ),
				'type'        => Controls_Manager::ICONS,
				'default'     => [
					'value'   => 'fas fa-shopping-cart',
					'library' => 'fa-solid',
				],
				'skin'        => 'inline',
				'label_block' => false,
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[
							'name'  => 'show_add_to_cart',
							'value' => 'y',
						],
						[
							'name'  => 'show_add_to_cart_tablet',
							'value' => 'y',
						],
						[
							'name'  => 'show_add_to_cart_mobile',
							'value' => 'y',
						],
					],
				],
			]
		);

		$this->add_control(
			'options_icon',
			[
				'label'       => __( '"Options" Icon', 'the7mk2' ),
				'type'        => Controls_Manager::ICONS,
				'description' => esc_html__( 'If none is selected, inherits from “Add To Cart” icon', 'the7mk2' ),
				'default'     => [
					'value'   => '',
					'library' => '',
				],
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'show_add_to_cart',
									'operator' => '==',
									'value'    => 'y',
								],
								[
									'name'     => 'show_variations',
									'operator' => '!==',
									'value'    => 'y',
								],
							],
						],
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'show_add_to_cart_tablet',
									'operator' => '==',
									'value'    => 'y',
								],
								[
									'name'     => 'show_variations_tablet',
									'operator' => '!==',
									'value'    => 'y',
								],
							],
						],
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'show_add_to_cart_mobile',
									'operator' => '==',
									'value'    => 'y',
								],
								[
									'name'     => 'show_variations_mobile',
									'operator' => '!==',
									'value'    => 'y',
								],
							],
						],
					],
				],
				'skin'        => 'inline',
				'label_block' => false,
			]
		);


		$this->add_control(
			'added_to_cart_icon',
			[
				'label'       => __( '"Added To Cart" Icon', 'the7mk2' ),
				'type'        => Controls_Manager::ICONS,
				'description' => esc_html__( 'If none is selected, inherits from “Add To Cart” icon', 'the7mk2' ),
				'default'     => [
					'value'   => '',
					'library' => '',
				],
				'skin'        => 'inline',
				'label_block' => false,
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'show_add_to_cart',
									'operator' => '==',
									'value'    => 'y',
								],
								[
									'name'     => 'show_variations',
									'operator' => '!==',
									'value'    => 'y',
								],
							],
						],
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'show_add_to_cart',
									'operator' => '==',
									'value'    => 'y',
								],
								[
									'name'     => 'show_variations_tablet',
									'operator' => '!==',
									'value'    => 'y',
								],
							],
						],
						[
							'relation' => 'and',
							'terms'    => [
								[
									'name'     => 'show_add_to_cart',
									'operator' => '==',
									'value'    => 'y',
								],
								[
									'name'     => 'show_variations_mobile',
									'operator' => '!==',
									'value'    => 'y',
								],
							],
						],
					],
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Register content style controls.
	 */
	protected function add_content_style_controls() {
		$this->start_controls_section(
			'content_style_section',
			[
				'label' => __( 'Content Area', 'the7mk2' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_basic_responsive_control(
			'post_content_alignment',
			[
				'label'     => __( 'Text Alignment', 'the7mk2' ),
				'type'      => Controls_Manager::CHOOSE,
				'toggle'    => false,
				'default'   => 'left',
				'options'   => [
					'left'   => [
						'title' => __( 'Left', 'the7mk2' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'the7mk2' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'the7mk2' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'{{WRAPPER}} .woocom-list-content' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_basic_responsive_control(
			'post_content_padding',
			[
				'label'      => __( 'Content Area Padding', 'the7mk2' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [
					'top'      => '',
					'right'    => '',
					'bottom'   => '',
					'left'     => '',
					'unit'     => 'px',
					'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}} .woocom-list-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Add image style controls.
	 */
	protected function add_image_style_controls() {
		$this->start_controls_section(
			'section_design_image',
			[
				'label' => __( 'Image', 'the7mk2' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->template( Image_Aspect_Ratio::class )->add_style_controls();

		$this->add_control(
			'img_border',
			[
				'label'      => __( 'Border', 'the7mk2' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .img-border' => 'border-style: solid; border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'img_border_radius',
			[
				'label'      => __( 'Border Radius', 'the7mk2' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .img-border' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'image_hover_style',
			[
				'label'   => __( 'Hover Style', 'the7mk2' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'hover_image',
				'options' => [
					''            => __( 'No Hover', 'the7mk2' ),
					'quick_scale' => __( 'Quick scale', 'the7mk2' ),
					'slow_scale'  => __( 'Slow scale', 'the7mk2' ),
					'hover_image' => __( 'Hover Image', 'the7mk2' ),
				],
			]
		);

		$this->add_control(
			'image_hover_trigger',
			[
				'label'     => __( 'Enable Hover', 'the7mk2' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'image',
				'options'   => [
					'image' => __( 'On image hover', 'the7mk2' ),
					'box'   => __( 'On box hover', 'the7mk2' ),
				],
				'condition' => [
					'image_hover_style!' => '',
				],
			]
		);

		$this->start_controls_tabs( 'thumbnail_effects_tabs' );

		$this->start_controls_tab(
			'normal',
			[
				'label' => __( 'Normal', 'the7mk2' ),
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name'           => 'overlay_background',
				'types'          => [ 'classic', 'gradient' ],
				'exclude'        => [ 'image' ],
				'fields_options' => [
					'background' => [
						'label' => __( 'Background Overlay', 'the7mk2' ),
					],
				],
				'selector'       => '{{WRAPPER}} .img-wrap:before, {{WRAPPER}} .img-wrap:after { transition: none; } {{WRAPPER}} .img-wrap:before, {{WRAPPER}} .img-wrap:after',
			]
		);

		$this->add_control(
			'image_border_color',
			[
				'label'     => __( 'Border', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'alpha'     => true,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .img-border' => 'border-color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'img_shadow',
				'selector' => '{{WRAPPER}} .img-border',
			]
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			[
				'name'     => 'thumbnail_filters',
				'selector' => '{{WRAPPER}} .img-wrap img',
			]
		);

		$this->add_control(
			'thumbnail_opacity',
			[
				'label'      => __( 'Opacity', 'the7mk2' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ '%' ],
				'range'      => [
					'px' => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .img-wrap' => 'opacity: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'hover',
			[
				'label' => __( 'Hover', 'the7mk2' ),
			]
		);
		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name'           => 'overlay_hover_background',
				'types'          => [ 'classic', 'gradient' ],
				'exclude'        => [ 'image' ],
				'fields_options' => [
					'background' => [
						'label' => esc_html__( 'Background Overlay', 'the7mk2' ),
					],
				],
				'selector'       => '{{WRAPPER}} .img-wrap:after',
			]
		);

		$this->add_control(
			'image_hover_border_color',
			[
				'label'     => __( 'Border', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'alpha'     => true,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .img-border' => 'border-color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'img_hover_shadow',
				'selector' => '{{WRAPPER}} .wf-cell:hover {z-index: 1;} {{WRAPPER}} .img-border { transition: all 0.3s; } {{WRAPPER}} .wf-cell:hover .img-border',
			]
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			[
				'name'     => 'thumbnail_hover_filters',
				'selector' => '{{WRAPPER}} .wf-cell:hover .img-wrap img',
			]
		);

		$this->add_control(
			'thumbnail_hover_opacity',
			[
				'label'      => __( 'Opacity', 'the7mk2' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ '%' ],
				'range'      => [
					'px' => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .wf-cell:hover .img-wrap' => 'opacity: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Register title style controls.
	 */
	protected function add_title_style_controls() {
		$this->start_controls_section(
			'post_title_style_section',
			[
				'label'     => __( 'Title', 'the7mk2' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_product_title' => 'y',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'           => 'post_title',
				'label'          => __( 'Typography', 'the7mk2' ),
				'selector'       => '{{WRAPPER}} .product-title',
				'fields_options' => [
					'font_family' => [
						'default' => '',
					],
					'font_size'   => [
						'default' => [
							'unit' => 'px',
							'size' => '',
						],
					],
					'font_weight' => [
						'default' => '',
					],
					'line_height' => [
						'default' => [
							'unit' => 'px',
							'size' => '',
						],
					],
				],
			]
		);

		$this->start_controls_tabs( 'post_title_style_tabs' );

		$this->start_controls_tab(
			'post_title_normal_style',
			[
				'label' => __( 'Normal', 'the7mk2' ),
			]
		);

		$this->add_control(
			'custom_title_color',
			[
				'label'     => __( 'Font Color', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'alpha'     => true,
				'default'   => '',
				'selectors' => [
					'#the7-body {{WRAPPER}} article:not(.class-1):not(.keep-custom-css) .product-title a'       => 'color: {{VALUE}}',
					'#the7-body {{WRAPPER}} article:not(.class-1):not(.keep-custom-css) .product-title a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'post_title_hover_style',
			[
				'label' => __( 'Hover', 'the7mk2' ),
			]
		);

		$this->add_control(
			'post_title_color_hover',
			[
				'label'     => __( 'Font Color', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'alpha'     => true,
				'default'   => '',
				'selectors' => [
					'#the7-body {{WRAPPER}} article:not(.class-1):not(.keep-custom-css) .product-title a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'post_title_bottom_margin',
			[
				'label'      => __( 'Spacing Above Title', 'the7mk2' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => [
					'unit' => 'px',
					'size' => '',
				],
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min'  => 0,
						'max'  => 200,
						'step' => 1,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .product-title' => 'margin-top: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * @param string $prefix_name Prefix.
	 * @param string $box_name Tab label.
	 *
	 * @return void
	 */
	protected function add_variations_tab_controls( $prefix_name, $box_name ) {

		$css_prefix = 'li a';
		// $is_hover = '';
		// $extra_link_class = '';
		switch ( $prefix_name ) {
			case 'hover_':
				$css_prefix = 'li:not(.active) a:not(.out-of-stock):hover';
				break;
			case 'active_':
				$css_prefix = 'li.active a:not(.out-of-stock)';
				break;
			case 'of_stock_':
				$css_prefix = 'li a.out-of-stock';
				break;
		}

		$selector = '{{WRAPPER}} .products-variations ' . $css_prefix;

		$this->start_controls_tab(
			$prefix_name . 'item_count_style',
			[
				'label' => $box_name,
			]
		);

		$this->add_control(
			$prefix_name . 'item_count_color',
			[
				'label'     => __( 'Text  Color', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'alpha'     => true,
				'default'   => '',
				'selectors' => [
					$selector => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			$prefix_name . 'item_count_background_color',
			[
				'label'     => __( 'Background Color', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					$selector => 'background-color: {{VALUE}};',
				],
			]
		);

		$item_count_border_color_selectors = [
			$selector => 'border-color: {{VALUE}};',
		];

		if ( ! in_array( $prefix_name, [ 'hover_', 'active_' ], true ) ) {
			$item_count_border_color_selectors['{{WRAPPER}} .products-variations'] = '--variations-border-color: {{VALUE}};';
		}

		$this->add_control(
			$prefix_name . 'item_count_border_color',
			[
				'label'     => __( 'Border Color', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => $item_count_border_color_selectors,
			]
		);

		$this->end_controls_tab();
	}

	/**
	 * Add variations style controls.
	 */
	protected function add_variations_style_controls() {
		$this->start_controls_section(
			'show_variations_style',
			[
				'label'     => __( 'Variations', 'the7mk2' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[
							'name'  => 'show_variations',
							'value' => 'y',
						],
						[
							'name'  => 'show_variations_tablet',
							'value' => 'y',
						],
						[
							'name'  => 'show_variations_mobile',
							'value' => 'y',
						],
					],
				],
			]
		);

		$this->add_basic_responsive_control(
			'variations_align',
			[
				'label'                => __( 'Alignment', 'the7mk2' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => [
					'left'   => [
						'title' => __( 'Left', 'the7mk2' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'the7mk2' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'the7mk2' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default'              => 'left',
				'selectors_dictionary' => [
					'left'   => '--align-variation-items: flex-start;',
					'center' => '--align-variation-items: center;',
					'right'  => '--align-variation-items: flex-end;',
				],
				'selectors'            => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
			]
		);

		$this->add_basic_responsive_control(
			'variations_row_space',
			[
				'label'     => __( 'Attributes gap', 'the7mk2' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [
					'px' => [
						'min' => 0,
						'max' => 200,
					],
				],
				'default'   => [
					'size' => 10,
				],
				'selectors' => [
					'{{WRAPPER}}  .products-variations-wrap' => 'grid-row-gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_basic_responsive_control(
			'variations_column_space',
			[
				'label'     => __( 'Values gap', 'the7mk2' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [
					'px' => [
						'min' => 0,
						'max' => 200,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .products-variations' => 'grid-column-gap: {{SIZE}}{{UNIT}}; grid-row-gap: {{SIZE}}{{UNIT}};',
				],
				'default'   => [
					'size' => 10,
				],
			]
		);

		$this->add_control(
			'variations_box',
			[
				'label'     => esc_html__( 'Box', 'the7mk2' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_basic_responsive_control(
			'margin_variations',
			[
				'label'      => esc_html__( 'Margins', 'the7mk2' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [
					'top'      => '',
					'right'    => '',
					'bottom'   => '',
					'left'     => '',
					'unit'     => 'px',
					'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}}' => '--variations-margin-top: {{TOP}}{{UNIT}}; --variations-margin-right: {{RIGHT}}{{UNIT}}; --variations-margin-bottom: {{BOTTOM}}{{UNIT}}; --variations-margin-left: {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .products-variations-wrap' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_basic_responsive_control(
			'padding_variations',
			[
				'label'      => esc_html__( 'Paddings', 'the7mk2' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [
					'top'      => '',
					'right'    => '',
					'bottom'   => '',
					'left'     => '',
					'unit'     => 'px',
					'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}} .products-variations-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'border_variations_width',
				'selector' => '{{WRAPPER}} .products-variations-wrap',
				'exclude'  => [ 'color' ],
			]
		);

		$this->add_basic_responsive_control(
			'menu_border_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'the7mk2' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .products-variations-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name'           => 'variations_bg_color',
				'types'          => [ 'classic', 'gradient' ],
				'exclude'        => [ 'image' ],
				'fields_options' => [
					'background' => [
						'label'     => __( 'Background', 'the7mk2' ),
						'selectors' => [
							'{{SELECTOR}}' => 'content: ""',
						],
					],
				],
				'selector'       => '{{WRAPPER}} .products-variations-wrap',
			]
		);

		$this->add_control(
			'variations_border_color',
			[
				'label'     => __( 'Box Border Color', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .products-variations-wrap' => 'border-color: {{VALUE}}',
				],
				'condition' => [
					'border_variations_width_border!' => '',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'variations_shadow',
				'label'    => __( 'Box Shadow', 'the7mk2' ),
				'selector' => '{{WRAPPER}} .products-variations-wrap',
			]
		);

		$this->add_control(
			'variations_label_style',
			[
				'label'     => esc_html__( 'Label', 'the7mk2' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'variations_label',
			[
				'label'        => __( 'Label', 'the7mk2' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'the7mk2' ),
				'label_off'    => __( 'Hide', 'the7mk2' ),
				'return_value' => 'y',
				'default'      => 'y',
				'selectors'    => [
					'{{WRAPPER}} .product-variation-row > span' => 'display: flex;',
				],
			]
		);

		$this->add_control(
			'variations_label_position',
			[
				'label'                => __( 'Label Position', 'the7mk2' ),
				'type'                 => Controls_Manager::CHOOSE,
				'toggle'               => false,
				'default'              => 'left',
				'options'              => [
					'left' => [
						'title' => __( 'Left', 'the7mk2' ),
						'icon'  => 'eicon-h-align-left',
					],
					'top'  => [
						'title' => __( 'Top', 'the7mk2' ),
						'icon'  => 'eicon-v-align-top',
					],
				],
				'prefix_class'         => 'label-position-',
				'selectors_dictionary' => [
					'left' => $this->combine_to_css_vars_definition_string(
						[
							'variations-direction' => 'row',
							'variations-align'     => 'center',
							'variations-justify'   => 'var(--align-variation-items)',
							'label-justify'        => 'flex-start',
							'label-margin'         => 'var(--label-margin-top, 0px) var(--label-margin-right, 10px) var(--label-margin-bottom, 0px) var(--label-margin-left, 0px);',
						]
					),
					'top'  => $this->combine_to_css_vars_definition_string(
						[
							'variations-direction' => 'column',
							'variations-align'     => 'var(--align-variation-items)',
							'variations-justify'   => 'center',
							'label-justify'        => 'var(--align-variation-items)',
							'label-margin'         => 'var(--label-margin-top, 0px) var(--label-margin-right, 0px) var(--label-margin-bottom, 10px) var(--label-margin-left, 0px);',
						]
					),
				],
				'selectors'            => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'condition' => [
					'variations_label' => 'y',
				]
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'variations_label_typography',
				'selector' => ' {{WRAPPER}} .product-variation-row > span',
				'condition' => [
					'variations_label' => 'y',
				]
			]
		);

		$this->add_control(
			'variations_label_bg_color',
			[
				'label'     => __( 'Color', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .product-variation-row > span' => 'color: {{VALUE}};',
				],
				'condition' => [
					'variations_label' => 'y',
				]
			]
		);

		$this->add_responsive_control(
			'variations_label_min_width',
			[
				'label'      => esc_html__( 'Min Width', 'the7mk2' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => [
					'unit' => 'px',
					'size' => '',
				],
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 1,
						'max' => 500,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .product-variation-row > span' => 'min-width: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'variations_label' => 'y',
				]
			]
		);

		$this->add_basic_responsive_control(
			'variations_label_gap',
			[
				'label'      => esc_html__( 'Margins', 'the7mk2' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [
					'top'      => '',
					'right'    => '',
					'bottom'   => '',
					'left'     => '',
					'unit'     => 'px',
					'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}}' => '--label-margin-top: {{TOP}}{{UNIT}}; --label-margin-right: {{RIGHT}}{{UNIT}}; --label-margin-bottom: {{BOTTOM}}{{UNIT}}; --label-margin-left: {{LEFT}}{{UNIT}};',
				],
				'condition' => [
					'variations_label' => 'y',
				]
			]
		);

		$this->add_control(
			'items_heading',
			[
				'label'     => esc_html__( 'Variations', 'the7mk2' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'variations_typography',
				'selector' => ' {{WRAPPER}} .products-variations li a',
			]
		);

		$this->add_basic_responsive_control(
			'variation_width',
			[
				'label'      => __( 'Min Width', 'the7mk2' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => [
					'unit' => 'px',
					'size' => '',
				],
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 1,
						'max' => 500,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .products-variations li a' => 'min-width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_basic_responsive_control(
			'variation_height',
			[
				'label'      => __( 'Min Height', 'the7mk2' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => [
					'unit' => 'px',
					'size' => '',
				],
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 1,
						'max' => 500,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .products-variations li a' => 'min-height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_basic_responsive_control(
			'padding_variable_item',
			[
				'label'      => esc_html__( 'Paddings', 'the7mk2' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [
					'top'      => '',
					'right'    => '',
					'bottom'   => '',
					'left'     => '',
					'unit'     => 'px',
					'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}} .products-variations li a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_basic_responsive_control(
			'border_variation_width',
			[
				'label'      => esc_html__( 'Border width', 'the7mk2' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'default'    => [
					'top'      => '',
					'right'    => '',
					'bottom'   => '',
					'left'     => '',
					'unit'     => 'px',
					'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}} .products-variations-wrap li a' => 'border-style: solid; border-top-width: {{TOP}}{{UNIT}};
					border-right-width: {{RIGHT}}{{UNIT}}; border-bottom-width: {{BOTTOM}}{{UNIT}}; border-left-width:{{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_basic_responsive_control(
			'variation_border_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'the7mk2' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .products-variations-wrap li a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->start_controls_tabs( 'tabs_variations_style' );
		$this->add_variations_tab_controls( 'normal_', __( 'Normal', 'the7mk2' ) );
		$this->add_variations_tab_controls( 'hover_', __( 'Hover', 'the7mk2' ) );
		$this->add_variations_tab_controls( 'active_', __( 'Selected', 'the7mk2' ) );
		$this->add_variations_tab_controls( 'of_stock_', __( 'Out', 'the7mk2' ) );
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Add rating style controls.
	 */
	protected function add_rating_style_controls() {
		$this->start_controls_section(
			'show_rating_style',
			[
				'label'     => __( 'Rating', 'the7mk2' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_rating' => 'y',
				],
			]
		);

		$this->add_basic_responsive_control(
			'stars_size',
			[
				'label'     => __( 'Size', 'the7mk2' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default'   => [
					'size' => 12,
				],
				'selectors' => [
					'{{WRAPPER}} .star-rating' => 'font-size: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}}',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'empty_star_color',
			[
				'label'     => __( 'Empty Star Color', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'alpha'     => true,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .star-rating:before' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'full_star_color',
			[
				'label'     => __( 'Filled Star Color', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'alpha'     => true,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .star-rating span:before' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'gap_above_rating',
			[
				'label'      => __( 'Spacing Above Rating', 'the7mk2' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .star-rating-wrap' => 'margin-top: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Register short description style controls.
	 */
	protected function add_short_description_style_controls() {
		$this->start_controls_section(
			'short_description_style_section',
			[
				'label'     => __( 'Short Description', 'the7mk2' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_short_description' => 'y',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'           => 'short_description_typography',
				'label'          => __( 'Typography', 'the7mk2' ),
				'fields_options' => [
					'font_family' => [
						'default' => '',
					],
					'font_size'   => [
						'default' => [
							'unit' => 'px',
							'size' => '',
						],
					],
					'font_weight' => [
						'default' => '',
					],
					'line_height' => [
						'default' => [
							'unit' => 'px',
							'size' => '',
						],
					],
				],
				'selector'       => '{{WRAPPER}} .woocommerce-product-details__short-description',
			]
		);

		$this->add_control(
			'short_description_color',
			[
				'label'     => __( 'Font Color', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'alpha'     => true,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .woocommerce-product-details__short-description' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'short_description_bottom_margin',
			[
				'label'      => __( 'Spacing Above Description', 'the7mk2' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => [
					'unit' => 'px',
					'size' => '',
				],
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min'  => 0,
						'max'  => 200,
						'step' => 1,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .woocommerce-product-details__short-description' => 'margin-top: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * @return void
	 */
	protected function add_button_style_controls() {
		/**
		 * Override default button icon conditions to respect custom icons set.
		 */
		$button_icon_conditions = [
			'condition'  => [],
			'conditions' => [
				'relation' => 'and',
				'terms'    => [
					[
						'relation' => 'or',
						'terms'    => [
							[
								'name'     => 'add_to_cart_icon[value]',
								'operator' => '!==',
								'value'    => '',
							],
							[
								'name'     => 'options_icon[value]',
								'operator' => '!==',
								'value'    => '',
							],
							[
								'name'     => 'added_to_cart_icon[value]',
								'operator' => '!==',
								'value'    => '',
							],
						],
					],
					[
						'relation' => 'or',
						'terms'    => [
							[
								'relation' => 'and',
								'terms'    => [
									[
										'name'     => 'layout',
										'operator' => '===',
										'value'    => 'content_below_img',
									],
									[
										'name'     => 'hide_icon',
										'operator' => '===',
										'value'    => 'y',
									],
								],
							],
							[
								'name'     => 'layout',
								'operator' => '!==',
								'value'    => 'content_below_img',
							],
						],
					],
				],
			],
		];

		$this->template( Add_To_Cart_Button::class )->add_style_controls(
			Add_To_Cart_Button::ICON_SWITCHER,
			[],
			[
				'button_icon'          => null,
				'gap_above_button'     => null,
				'button_icon_size'     => $button_icon_conditions,
				'button_size'          => [
					'default'   => 'md',
					'condition' => [
						'layout' => 'content_below_img',
					],
				],
				'button_icon_position' => [
					'condition' => [
						'layout'    => 'content_below_img',
						'hide_icon' => 'y',
					],
				],
				'button_icon_spacing'  => [
					'condition' => [
						'layout'    => 'content_below_img',
						'hide_icon' => 'y',
					],
				],
				'button_text_padding'  => [
					'condition' => [
						'layout' => 'content_below_img',
					],
				],
				'button_icon_divider'  => [
					'condition' => [
						'layout!' => 'btn_on_img',
					],
				],
				'button_typography'    => [
					'condition' => [
						'layout!' => 'btn_on_img',
					],
				],
			],
			'',
			'article ',
			esc_html__( 'Add To Cart', 'the7mk2' )
		);

		$this->start_injection(
			[
				'type' => 'control',
				'at'   => 'before',
				'of'   => 'button_size',
			]
		);

		$this->add_basic_responsive_control(
			'add_to_cart_align',
			[
				'label'                => __( 'Horizontal Position', 'the7mk2' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => [
					'left'    => [
						'title' => __( 'Left', 'the7mk2' ),
						'icon'  => 'eicon-h-align-left',
					],
					'center'  => [
						'title' => esc_html__( 'Center', 'the7mk2' ),
						'icon'  => 'eicon-h-align-center',
					],
					'right'   => [
						'title' => __( 'Right', 'the7mk2' ),
						'icon'  => 'eicon-h-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justified', 'the7mk2' ),
						'icon'  => 'eicon-h-align-stretch',
					],
				],
				'default'              => 'left',
				'selectors_dictionary' => [
					'left'    => $this->combine_to_css_vars_definition_string(
						[
							'justify-btn'         => 'flex-start',
							'btn-padding-left'    => 'var(--box-button-width, 32px)',
							'text-margin'         => '0 var(--box-button-padding-right, 10px) 0 var(--box-button-padding-left, 10px);',
							'btn-padding-right'   => '0',
							'btn-width'           => 'auto',
							'icon-order'          => '0',
							'text-order'          => '1',
							'icon-position-left'  => '0',
							'icon-position-right' => 'auto',
							'icon-margin'         => 'margin: 0 0 0 var(--btn-icon-spacing);',
							'expand-padding'      => '0 0 0 var(--image-button-background-size);',
						]
					),
					'right'   => $this->combine_to_css_vars_definition_string(
						[
							'justify-btn'         => 'flex-end',
							'btn-padding-right'   => 'var(--box-button-width, 32px)',
							'text-margin'         => '0 var(--box-button-padding-right, 10px) 0 var(--box-button-padding-left, 10px);',
							'btn-padding-left'    => '0',
							'btn-width'           => 'auto',
							'icon-order'          => '1',
							'text-order'          => '0',
							'icon-position-left'  => 'auto',
							'icon-position-right' => '0',
							'icon-margin'         => 'margin: 0 var(--btn-icon-spacing) 0 0;',
							'expand-padding'      => '0 var(--image-button-background-size) 0 0;',
						]
					),
					'center'  => $this->combine_to_css_vars_definition_string(
						[
							'justify-btn'         => 'center',
							'btn-padding-left'    => 'var(--box-button-width, 32px)',
							'text-margin'         => ' 0 var(--box-button-padding-right, 10px) 0 var(--box-button-padding-left, 10px);',
							'btn-padding-right'   => '0',
							'btn-width'           => 'auto',
							'icon-order'          => '0',
							'text-order'          => '1',
							'icon-position-left'  => '0',
							'icon-position-right' => 'auto',
							'icon-margin'         => 'margin: 0 0 0 var(--btn-icon-spacing);',
							'expand-padding'      => '0 0 0 var(--image-button-background-size);',
						]
					),
					'justify' => $this->combine_to_css_vars_definition_string(
						[
							'justify-btn'         => 'center',
							'btn-padding-left'    => 'var(--box-button-width, 32px)',
							'text-margin'         => '0 var(--box-button-padding-right, 10px) 0 var(--box-button-padding-left, 10px);',
							'btn-padding-right'   => '0',
							'btn-width'           => '100%',
							'icon-order'          => '0',
							'text-order'          => '1',
							'icon-position-left'  => '0',
							'icon-position-right' => 'auto',
							'icon-margin'         => 'margin: 0 0 0 var(--btn-icon-spacing);',
							'expand-padding'      => '0 0 0 var(--image-button-background-size);',
						]
					),
				],
				'condition'            => [
					'button_position' => 'below_image',
				],
				'selectors'            => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
			]
		);

		$this->add_control(
			'product_icon_h_position',
			[
				'label'                => __( 'Horizontal Position', 'the7mk2' ),
				'type'                 => Controls_Manager::CHOOSE,
				'toggle'               => false,
				'default'              => 'right',
				'options'              => [
					'left'    => [
						'title' => __( 'Left', 'the7mk2' ),
						'icon'  => 'eicon-h-align-left',
					],
					'center'  => [
						'title' => esc_html__( 'Center', 'the7mk2' ),
						'icon'  => 'eicon-h-align-center',
					],
					'right'   => [
						'title' => __( 'Right', 'the7mk2' ),
						'icon'  => 'eicon-h-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justified', 'the7mk2' ),
						'icon'  => 'eicon-h-align-stretch',
					],
				],
				'prefix_class'         => 'icon-position-',
				'selectors_dictionary' => [
					'left'    => $this->combine_to_css_vars_definition_string(
						[
							'justify-btn'         => 'flex-start',
							'btn-width'           => 'auto',
							'btn-left-position'   => '0',
							'btn-right-position'  => 'auto',
							'btn-translate-x'     => '0',
							'btn-padding-left'    => 'var(--box-button-width, 32px)',
							'text-margin'         => '0 var(--box-button-padding-right, 10px) 0 var(--box-button-padding-left, 10px);',
							'btn-padding-right'   => '0',
							'icon-order'          => '0',
							'text-order'          => '1',
							'icon-position-left'  => '0',
							'icon-position-right' => 'auto',
							'icon-margin'         => 'margin: 0 0 0 var(--btn-icon-spacing);',
							'expand-padding'      => '0 0 0 var(--image-button-background-size);',
						]
					),
					'right'   => $this->combine_to_css_vars_definition_string(
						[
							'justify-btn'         => 'flex-end',
							'btn-width'           => 'auto',
							'btn-left-position'   => 'auto',
							'btn-right-position'  => '0',
							'btn-translate-x'     => '0',
							'btn-padding-right'   => 'var(--box-button-width, 32px)',
							'text-margin'         => '0 var(--box-button-padding-right, 10px) 0 var(--box-button-padding-left, 10px);',
							'btn-padding-left'    => '0',
							'icon-order'          => '1',
							'text-order'          => '0',
							'icon-position-left'  => 'auto',
							'icon-position-right' => '0',
							'icon-margin'         => 'margin: 0 var(--btn-icon-spacing) 0 0;',
							'expand-padding'      => '0 var(--image-button-background-size) 0 0;',
						]
					),
					'center'  => $this->combine_to_css_vars_definition_string(
						[
							'justify-btn'         => 'center',
							'btn-width'           => 'auto',
							'btn-left-position'   => '50%',
							'btn-right-position'  => 'auto',
							'btn-translate-x'     => '-50%',
							'btn-padding-left'    => 'var(--box-button-width, 32px)',
							'text-margin'         => ' 0 var(--box-button-padding-right, 10px) 0 var(--box-button-padding-left, 10px);',
							'btn-padding-right'   => '0',
							'icon-order'          => '0',
							'text-order'          => '1',
							'icon-position-left'  => '0',
							'icon-position-right' => 'auto',
							'icon-margin'         => 'margin: 0 0 0 var(--btn-icon-spacing);',
							'expand-padding'      => '0 0 0 var(--image-button-background-size);',
						]
					),
					'justify' => $this->combine_to_css_vars_definition_string(
						[
							'justify-btn'         => 'stretch',
							'btn-width'           => '100%',
							'btn-left-position'   => '0',
							'btn-right-position'  => '0',
							'btn-translate-x'     => '0',
							'btn-padding-left'    => 'var(--box-button-width, 32px)',
							'text-margin'         => ' 0 var(--box-button-padding-right, 10px) 0 var(--box-button-padding-left, 10px);',
							'btn-padding-right'   => '0',
							'icon-order'          => '0',
							'text-order'          => '1',
							'icon-position-left'  => '0',
							'icon-position-right' => 'auto',
							'icon-margin'         => 'margin: 0 0 0 var(--btn-icon-spacing);',
							'expand-padding'      => '0 0 0 var(--image-button-background-size);',
						]
					),
				],
				'selectors'            => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'condition'            => [
					'button_position!' => 'below_image',
				],
			]
		);

		$this->add_control(
			'product_icon_v_position',
			[
				'label'                => __( 'Vertical Position', 'the7mk2' ),
				'type'                 => Controls_Manager::CHOOSE,
				'toggle'               => false,
				'default'              => 'bottom',
				'options'              => [
					'top'    => [
						'title' => __( 'Top', 'the7mk2' ),
						'icon'  => 'eicon-v-align-top',
					],
					'center' => [
						'title' => __( 'Middle', 'the7mk2' ),
						'icon'  => 'eicon-v-align-middle',
					],
					'bottom' => [
						'title' => __( 'Bottom', 'the7mk2' ),
						'icon'  => 'eicon-v-align-bottom',
					],
				],
				'selectors_dictionary' => [
					'top'    => $this->combine_to_css_vars_definition_string(
						[
							'btn-top-position'    => '0',
							'btn-bottom-position' => 'auto',
							'btn-translate-y'     => '0',
						]
					),
					'bottom' => $this->combine_to_css_vars_definition_string(
						[
							'btn-top-position'    => 'auto',
							'btn-bottom-position' => '0',
							'btn-translate-y'     => '0',
						]
					),
					'center' => $this->combine_to_css_vars_definition_string(
						[
							'btn-top-position'    => '50%',
							'btn-bottom-position' => 'auto',
							'btn-translate-y'     => '-50%',
						]
					),
				],
				'selectors'            => [
					'{{WRAPPER}}' => '{{VALUE}}',
				],
				'condition'            => [
					'button_position!' => 'below_image',
				],
				'prefix_class'         => 'icon-position-',
			]
		);

		$this->add_basic_responsive_control(
			'add_to_cart_margin',
			[
				'label'      => __( 'Margins', 'the7mk2' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .woo-list-buttons .box-button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'add_to_cart_divider',
			[
				'type' => Controls_Manager::DIVIDER,
			]
		);

		$this->end_injection();

		$this->start_injection(
			[
				'type' => 'control',
				'at'   => 'before',
				'of'   => 'button_icon_size',
			]
		);

		$this->add_control(
			'hide_icon',
			[
				'label'        => __( 'Icon', 'the7mk2' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'the7mk2' ),
				'label_off'    => __( 'Hide', 'the7mk2' ),
				'return_value' => 'y',
				'default'      => 'y',
				'condition'    => [
					'layout' => 'content_below_img',
				],
				'prefix_class' => 'icon-visibility-',
			]
		);

		$this->end_injection();

		$this->start_injection(
			[
				'type' => 'control',
				'at'   => 'before',
				'of'   => 'button_text_padding',
			]
		);

		$this->add_control(
			'icon_on_image_text_background_color',
			[
				'label'     => __( 'Text Background', 'the7mk2' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .woo-list-buttons .woo-popup-button { background-color: transparent;} {{WRAPPER}} .woo-list-buttons .woo-popup-button.elementor-button:hover' => 'background: {{VALUE}};',
				],
				'condition' => [
					'layout' => 'icon_with_text',
				],
			]
		);

		$this->add_basic_responsive_control(
			'icon_on_image_text_padding',
			[
				'label'              => __( 'Padding', 'the7mk2' ),
				'type'               => Controls_Manager::DIMENSIONS,
				'size_units'         => [ 'px' ],
				'allowed_dimensions' => 'horizontal',
				'default'            => [
					'top'      => '0',
					'right'    => '',
					'bottom'   => '0',
					'left'     => '',
					'unit'     => 'px',
					'isLinked' => true,
				],
				'selectors'          => [
					'{{WRAPPER}} .woo-list-buttons .woo-popup-button .filter-popup' => 'padding: 0 {{RIGHT}}{{UNIT}} 0 {{LEFT}}{{UNIT}}',
				],
				'condition'          => [
					'layout' => 'icon_with_text',
				],
			]
		);

		$this->end_injection();
	}
}
