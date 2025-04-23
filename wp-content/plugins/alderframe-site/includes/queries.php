<?php
/**
 * Query contracts. Rights / release rules are query rules here, never template ifs.
 */

defined( 'ABSPATH' ) || exit;

function af_rights_clause(): array {
	return [ 'key' => 'rights_status', 'value' => 'approved' ];
}

/** Published services by display_order then title. */
function af_services(): array {
	return get_posts( [
		'post_type'      => 'af_service',
		'posts_per_page' => 20,
		'meta_key'       => 'display_order', // phpcs:ignore
		'orderby'        => [ 'meta_value_num' => 'ASC', 'title' => 'ASC' ],
	] );
}

/** Published, rights-approved gardens, newest first. */
function af_gardens( array $args = [] ): array {
	$q = [
		'post_type'      => 'af_garden',
		'posts_per_page' => $args['count'] ?? 12,
		'meta_query'     => [ af_rights_clause() ], // phpcs:ignore
		'meta_key'       => 'completion_year', // phpcs:ignore
		'orderby'        => [ 'meta_value_num' => 'DESC', 'ID' => 'DESC' ],
		'post__not_in'   => $args['exclude'] ?? [],
	];
	if ( ! empty( $args['featured'] ) ) {
		$q['meta_query'][] = [ 'key' => 'featured', 'value' => '1' ];
	}
	if ( ! empty( $args['slugs'] ) ) {
		$q['post_name__in'] = array_map( 'sanitize_title', array_map( 'trim', explode( ',', $args['slugs'] ) ) );
		$q['orderby']       = 'post_name__in';
	}
	if ( ! empty( $args['condition'] ) ) {
		$q['tax_query'] = [ [ 'taxonomy' => 'site_condition', 'field' => 'slug', 'terms' => $args['condition'] ] ]; // phpcs:ignore
	}
	if ( ! empty( $args['service'] ) ) {
		$q['meta_query'][] = [ 'key' => 'service_refs', 'value' => 'i:' . (int) $args['service'] . ';', 'compare' => 'LIKE' ];
	}
	return get_posts( $q );
}

/** Prefer shared services, then shared condition, fill to $n. */
function af_related_gardens( int $garden_id, int $n = 3 ): array {
	$picked = [];
	foreach ( (array) get_post_meta( $garden_id, 'service_refs', true ) as $sid ) {
		$picked = array_merge( $picked, wp_list_pluck( af_gardens( [ 'service' => $sid, 'exclude' => [ $garden_id ] ] ), 'ID' ) );
	}
	foreach ( wp_get_post_terms( $garden_id, 'site_condition', [ 'fields' => 'slugs' ] ) as $slug ) {
		$picked = array_merge( $picked, wp_list_pluck( af_gardens( [ 'condition' => $slug, 'exclude' => [ $garden_id ] ] ), 'ID' ) );
	}
	$picked = array_merge( $picked, wp_list_pluck( af_gardens( [ 'exclude' => [ $garden_id ] ] ), 'ID' ) );
	return array_map( 'get_post', array_slice( array_values( array_unique( $picked ) ), 0, $n ) );
}

function af_designers(): array {
	return get_posts( [
		'post_type'      => 'af_designer',
		'posts_per_page' => 20,
		'meta_key'       => 'display_order', // phpcs:ignore
		'orderby'        => [ 'meta_value_num' => 'ASC', 'title' => 'ASC' ],
	] );
}

/** Approved quotes whose garden (if any) is published and rights-approved. */
function af_quotes( ?int $garden_id = null ): array {
	$q = [
		'post_type'      => 'af_feedback',
		'posts_per_page' => 20,
		'meta_query'     => [ [ 'key' => 'release_state', 'value' => 'approved' ] ], // phpcs:ignore
	];
	if ( $garden_id ) {
		$q['meta_query'][] = [ 'key' => 'garden_ref', 'value' => $garden_id ];
	}
	return array_values( array_filter( get_posts( $q ), function ( $quote ) {
		$g = (int) get_post_meta( $quote->ID, 'garden_ref', true );
		return ! $g || af_refs( [ $g ], 'af_garden' );
	} ) );
}

function af_faqs( string $stage ): array {
	return get_posts( [
		'post_type'      => 'af_faq',
		'posts_per_page' => 20,
		'meta_query'     => [ [ 'key' => 'audience_stage', 'value' => $stage ] ], // phpcs:ignore
		'meta_key'       => 'display_order', // phpcs:ignore
		'orderby'        => [ 'meta_value_num' => 'ASC', 'title' => 'ASC' ],
	] );
}

/** Drop missing / unpublished / unapproved refs at render. */
function af_refs( array $ids, string $type ): array {
	$out = [];
	foreach ( array_filter( array_map( 'absint', $ids ) ) as $id ) {
		$p = get_post( $id );
		if ( ! $p || $type !== $p->post_type || 'publish' !== $p->post_status ) {
			continue;
		}
		if ( 'af_garden' === $type && 'approved' !== get_post_meta( $id, 'rights_status', true ) ) {
			continue;
		}
		$out[] = $p;
	}
	return $out;
}

/** "From CA$6,800" / "CA$650" / "CA$165 / hour". */
function af_format_fee( float $amount, string $basis = 'from' ): string {
	$n = 'CA$' . number_format( $amount, floor( $amount ) == $amount ? 0 : 2 );
	return match ( $basis ) {
		'fixed'  => $n,
		'hourly' => $n . ' / hour',
		default  => 'From ' . $n,
	};
}

function af_weeks( int $id ): string {
	$min = (int) get_post_meta( $id, 'lead_weeks_min', true );
	$max = (int) get_post_meta( $id, 'lead_weeks_max', true );
	if ( ! $min ) {
		return '';
	}
	return ( $max > $min ? "{$min}–{$max}" : $min ) . ' weeks';
}

function af_area( int $id ): string {
	$a = get_post_meta( $id, 'site_area_m2', true );
	return '' === $a ? '' : number_format( (float) $a, 1 ) . ' m²';
}

/* Main archive and term archives obey the same rule. Withdrawn gardens answer 410. */
add_action( 'pre_get_posts', function ( WP_Query $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( $q->is_post_type_archive( 'af_garden' ) || $q->is_tax( 'site_condition' ) ) {
		$q->set( 'meta_query', [ af_rights_clause() ] );
		$q->set( 'meta_key', 'completion_year' );
		$q->set( 'orderby', [ 'meta_value_num' => 'DESC', 'ID' => 'DESC' ] );
		$q->set( 'posts_per_page', 12 );
	}
	if ( $q->is_search() ) {
		$q->set( 'post_type', [ 'page', 'post', 'af_service', 'af_garden' ] );
	}
} );

add_action( 'template_redirect', function () {
	if ( is_singular( 'af_garden' ) && 'approved' !== get_post_meta( get_queried_object_id(), 'rights_status', true ) ) {
		status_header( 410 );
		nocache_headers();
		include get_query_template( '404' );
		exit;
	}
} );

// Core sitemaps: never list unapproved gardens or internal records.
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $type ) {
	if ( 'af_garden' === $type ) {
		$args['meta_query'] = [ af_rights_clause() ]; // phpcs:ignore
	}
	return $args;
}, 10, 2 );
