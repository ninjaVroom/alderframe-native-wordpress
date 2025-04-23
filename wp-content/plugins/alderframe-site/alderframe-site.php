<?php
/**
 * Plugin Name: Alderframe Site
 * Description: Studio logic for Alderframe: records, fields, query contracts, dynamic blocks, site-visit intake.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Text Domain: alderframe
 */

defined( 'ABSPATH' ) || exit;

define( 'AF_PATH', plugin_dir_path( __FILE__ ) );
define( 'AF_URL', plugin_dir_url( __FILE__ ) );
define( 'AF_VERSION', '1.0.0' );

require AF_PATH . 'includes/schema.php';     // post types, taxonomies, fields, meta boxes, publish gate
require AF_PATH . 'includes/options.php';    // af_option() + Site Settings screen
require AF_PATH . 'includes/queries.php';    // one function per list (rights / release rules live here)
require AF_PATH . 'includes/render.php';     // dynamic block renderers
require AF_PATH . 'includes/blocks.php';     // block registration + generic editor UI
require AF_PATH . 'includes/forms.php';      // site-visit intake
require AF_PATH . 'includes/hardening.php';  // small security defaults
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require AF_PATH . 'includes/cli.php';    // wp alderframe seed
}

register_activation_hook( __FILE__, function () {
	af_register_types();
	flush_rewrite_rules();
} );
