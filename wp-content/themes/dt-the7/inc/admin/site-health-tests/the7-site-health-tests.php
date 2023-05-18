<?php
/**
 * The7 site health tests.
 *
 * @since 7.6.1
 *
 * @package The7\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filter that add The7 site health tests.
 *
 * @since 7.6.1
 *
 * @param array $tests Tests.
 *
 * @return array
 */
function the7_add_site_health_tests( $tests ) {
	$async = &$tests['async'];

	$async['the7_server'] = [
		'label' => esc_html__( 'The7 remote content server availability', 'the7mk2' ),
		'test'  => 'the7_site_health_server_availability_test',
	];

	$tests['direct']['the7_orphaned_terms'] = array(
		'label' => __( 'The7 terms from disabled taxonomies', 'the7mk2' ),
		'test'  => 'the7_site_health_orphaned_terms',
	);

	return $tests;
}

add_filter( 'site_status_tests', 'the7_add_site_health_tests' );

/**
 * Ajax handler for The7 remote server test.
 *
 * @since 7.6.1
 */
function the7_site_health_server_availability_test() {
	wp_verify_nonce( 'health-check-site-status' );

	if ( ! current_user_can( 'install_plugins' ) ) {
		wp_send_json_error();
	}

	$result = array(
		'label'       => __( 'The7 remote content server is available', 'the7mk2' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'The7', 'the7mk2' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'Access to The7 remote server allow to auto update theme, bundled plugins and install demo content.', 'the7mk2' )
		),
		'actions'     => '',
		'test'        => 'the7_site_health_server_availability_test',
	);

	$the7_server_code = wp_remote_retrieve_response_code( wp_safe_remote_get( 'https://repo.the7.io/theme/info.json', array( 'decompress' => false ) ) );
	if ( $the7_server_code < 200 || $the7_server_code >= 300 ) {
		$result['status']         = 'recommended';
		$result['label']          = __( 'The7 remote content server is not available', 'the7mk2' );
		$result['badge']['color'] = 'blue';
		$result['description']    = sprintf(
			'<p>%s</p>',
			sprintf(
				// translators: $s - remote server url.
				__(
					'Service is temporary unavailable. Theme update, installation and update of bundled plugins and demo content are not available. Please check back later.
If the issue persists, contact your hosting provider and make sure that %s is not blocked.',
					'the7mk2'
				),
				'https://repo.the7.io/'
			)
		);
	}

	wp_send_json_success( $result );
}

add_action( 'wp_ajax_health-check-the7-site_health_server_availability_test', 'the7_site_health_server_availability_test' );
add_action( 'wp_ajax_health-check-the7_site_health_server_availability_test', 'the7_site_health_server_availability_test' );
add_action( 'wp_ajax_the7_site_health_server_availability_test', 'the7_site_health_server_availability_test' );

/**
 * @return array
 */
function the7_site_health_orphaned_terms() {
	$result = [
		'label'       => __( 'You have no terms from disabled taxonomies', 'the7mk2' ),
		'status'      => 'good',
		'badge'       => [
			'label' => __( 'Performance', 'the7mk2' ),
			'color' => 'blue',
		],
		'description' => '<p>' . __( 'Terms from disabled taxonomies could be the result of a configuration error.', 'the7mk2' ) . '</p>',
		'actions'     => '',
	];

	$taxonomies = array_merge( get_taxonomies( [ 'public' => true ] ), get_taxonomies( [ 'public' => false ] ) );
	$terms      = get_terms( [ 'hide_empty' => false ] );

	$orphaned_terms = [];
	foreach ( $terms as $term ) {
		if ( ! array_key_exists( $term->taxonomy, $taxonomies ) ) {
			$orphaned_terms[] = $term;
		}
	}

	if ( $orphaned_terms ) {
		$orphaned_terms_list_html = '';

		foreach ( $orphaned_terms as $taxonomy => $terms ) {
			$orphaned_terms_list_html .= '<tr>';
			$orphaned_terms_list_html .= '<td>' . $terms->name . '</td>';
			$orphaned_terms_list_html .= '<td>' . $terms->taxonomy . '</td>';
			$orphaned_terms_list_html .= '</tr>';
		}

		$head = '<thead><tr><th>Term</th><th>Taxonomy</th></tr></thead>';
		$orphaned_terms_list_html = '<table class="wp-list-table widefat fixed striped table-view-list">' . $head . '<tbody>' . $orphaned_terms_list_html . '</tbody></table><br>';

		ob_start();
		?>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'the7-dev-tools' ); ?>
			<input type="hidden" name="action" value="the7_use_dev_tool">
			<button type="submit" class="button" name="tool" value="delete_orphaned_terms">Delete terms</button>
		</form>

		<?php
		$actions = ob_get_clean();

		$result = [
			'label'       => __( 'You have terms from disabled taxonomies', 'the7mk2' ),
			'status'      => 'recommended',
			'badge'       => [
				'label' => __( 'Performance', 'the7mk2' ),
				'color' => 'blue',
			],
			'description' => '<p>' . __( 'You may want to delete terms from disabled taxonomies', 'the7mk2' ) . ': </p>' . $orphaned_terms_list_html,
//			'actions'     => $actions,
		];
	}

	$result['test'] = 'the7_site_health_orphaned_terms';

	return $result;
}
