<?php
/** Small defaults from the security review (09 §4). Host-level items (WAF, MFA, file mods) stay in wp-config / host. */

defined( 'ABSPATH' ) || exit;

add_filter( 'xmlrpc_enabled', '__return_false' );

// No user enumeration through REST or ?author=N for visitors.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );
add_action( 'template_redirect', function () {
	if ( is_author() && ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

// Hide unapproved gardens from search.
add_action( 'pre_get_posts', function ( WP_Query $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_search() ) {
		return;
	}
	$hidden = get_posts( [
		'post_type'      => 'af_garden',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'meta_query'     => [ 'relation' => 'OR', [ 'key' => 'rights_status', 'value' => 'approved', 'compare' => '!=' ], [ 'key' => 'rights_status', 'compare' => 'NOT EXISTS' ] ], // phpcs:ignore
	] );
	if ( $hidden ) {
		$q->set( 'post__not_in', $hidden );
	}
} );

// A missing page answers 404 with recovery links; never a silent redirect to a guessed URL.
add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
