<?php
/**
 * Records, taxonomies and the single field contract.
 * native post meta + one schema-driven meta box instead of ACF Pro (no licence).
 * Swap to ACF local JSON later by mapping AF_FIELDS 1:1 to field groups; meta keys stay the same.
 */

defined( 'ABSPATH' ) || exit;

/** Field contract: post type => key => [label, type, extra]. Types: text, textarea, number, int, select, bool, lines, rows, ids, image. */
function af_fields(): array {
	return [
		'af_service'  => [
			'scope_summary'     => [ 'Scope summary', 'textarea', [ 'max' => 240, 'required' => true ] ],
			'fee_from_cad'      => [ 'Fee from (CAD)', 'number', [ 'min' => 0, 'required' => true ] ],
			'lead_weeks_min'    => [ 'Lead time, min weeks', 'int', [ 'min' => 1, 'required' => true ] ],
			'lead_weeks_max'    => [ 'Lead time, max weeks', 'int', [ 'min' => 1, 'required' => true ] ],
			'engagement_label'  => [ 'Engagement type', 'text', [ 'max' => 60 ] ],
			'deliverables'      => [ 'Deliverables (one per line)', 'lines', [ 'help' => 'Used by the comparison table.' ] ],
			'deliverable_steps' => [ 'Process steps (one per line)', 'lines', [ 'max_rows' => 6 ] ],
			'display_order'     => [ 'Display order', 'int', [] ],
		],
		'af_garden'   => [
			'locality_label'  => [ 'Locality label (never an address)', 'text', [ 'max' => 60, 'required' => true ] ],
			'site_area_m2'    => [ 'Site area (m²)', 'number', [ 'min' => 0 ] ],
			'completion_year' => [ 'Completion year', 'int', [ 'min' => 1990, 'max' => 2100, 'required' => true ] ],
			'rights_status'   => [ 'Photography rights', 'select', [ 'options' => [ 'pending' => 'Pending', 'approved' => 'Approved', 'withdrawn' => 'Withdrawn' ], 'required' => true ] ],
			'design_brief'    => [ 'The site and the brief', 'textarea', [ 'max' => 600, 'help' => 'What the ground was like and what the owners needed.' ] ],
			'design_response' => [ 'Design response', 'textarea', [ 'max' => 600 ] ],
			'outcome'         => [ 'Outcome', 'textarea', [ 'max' => 600, 'help' => 'What changed once built. Leave empty until the studio can state it.' ] ],
			'photo_series'    => [ 'Gallery image IDs', 'ids', [ 'help' => 'Attachment IDs in display order; captions come from the media library.' ] ],
			'service_refs'    => [ 'Service IDs', 'ids', [] ],
			'featured'        => [ 'Feature on home page', 'bool', [] ],
			'site_model'      => [ 'Show the 3D visualisation', 'bool', [ 'help' => 'Plays the rendered terrace sequence on this garden page. Only tick it for the garden the render depicts.' ] ],
		],
		'af_designer' => [
			'role'          => [ 'Role', 'text', [ 'max' => 80, 'required' => true ] ],
			'garden_refs'   => [ 'Credited garden IDs (0–4)', 'ids', [ 'max_rows' => 4 ] ],
			'display_order' => [ 'Display order', 'int', [] ],
		],
		'af_feedback' => [
			'quote'         => [ 'Quote', 'textarea', [ 'max' => 320, 'required' => true ] ],
			'attribution'   => [ 'Attribution', 'text', [ 'max' => 80, 'required' => true ] ],
			'garden_ref'    => [ 'Garden ID', 'int', [] ],
			'release_state' => [ 'Release state', 'select', [ 'options' => [ 'pending' => 'Pending', 'approved' => 'Approved', 'withdrawn' => 'Withdrawn' ], 'required' => true ] ],
		],
		'af_faq'      => [
			'response'       => [ 'Response', 'textarea', [ 'max' => 1200, 'required' => true ] ],
			'audience_stage' => [ 'Audience stage', 'select', [ 'options' => [ 'design' => 'Design', 'before-visit' => 'Before visit', 'fees' => 'Fees' ], 'required' => true ] ],
			'display_order'  => [ 'Display order', 'int', [] ],
		],
		'page'        => [
			'page_kind'      => [ 'Page kind', 'select', [ 'options' => array_combine( AF_PAGE_KINDS, AF_PAGE_KINDS ) ] ],
			'effective_date' => [ 'Effective date (legal)', 'text', [ 'max' => 40 ] ],
			'fee_packages'   => [ 'Fee packages: name | scope | amount | basis(fixed/from/hourly) | inclusions;separated', 'rows', [] ],
			'brief_pdf'      => [ 'Guide PDF attachment ID', 'int', [] ],
		],
		'post'        => [
			'featured_garden' => [ 'Related garden ID', 'int', [] ],
		],
	];
}

const AF_PAGE_KINDS = [ '', 'home', 'studio', 'services-hub', 'fees', 'contact', 'site-visit', 'guide', 'campaign', 'legal' ];

add_action( 'init', 'af_register_types', 5 );
function af_register_types(): void {
	$internal = [ 'public' => false, 'show_ui' => true, 'show_in_rest' => true, 'publicly_queryable' => false, 'exclude_from_search' => true, 'has_archive' => false, 'rewrite' => false ];
	$types    = [
		'af_service'  => [ 'Design services', 'Design service', [ 'public' => true, 'show_in_rest' => true, 'has_archive' => false, 'rewrite' => [ 'slug' => 'design-services', 'with_front' => false ], 'menu_icon' => 'dashicons-clipboard', 'supports' => [ 'title', 'excerpt', 'revisions', 'custom-fields' ] ] ],
		'af_garden'   => [ 'Completed gardens', 'Completed garden', [ 'public' => true, 'show_in_rest' => true, 'has_archive' => 'gardens', 'rewrite' => [ 'slug' => 'gardens', 'with_front' => false ], 'menu_icon' => 'dashicons-palmtree', 'supports' => [ 'title', 'thumbnail', 'revisions', 'custom-fields' ] ] ],
		'af_designer' => [ 'Designers', 'Designer', $internal + [ 'menu_icon' => 'dashicons-groups', 'supports' => [ 'title', 'thumbnail', 'revisions', 'custom-fields' ] ] ],
		'af_feedback' => [ 'Owner feedback', 'Owner quote', $internal + [ 'menu_icon' => 'dashicons-format-quote', 'supports' => [ 'title', 'revisions', 'custom-fields' ] ] ],
		'af_faq'      => [ 'Visit FAQs', 'Visit FAQ', $internal + [ 'menu_icon' => 'dashicons-editor-help', 'supports' => [ 'title', 'revisions', 'custom-fields' ] ] ],
		'af_request'  => [ 'Visit requests', 'Visit request', [ 'public' => false, 'show_ui' => true, 'show_in_rest' => false, 'menu_icon' => 'dashicons-email', 'supports' => [ 'title' ], 'capabilities' => [ 'create_posts' => 'do_not_allow' ], 'map_meta_cap' => true ] ],
	];
	foreach ( $types as $type => [ $plural, $single, $args ] ) {
		register_post_type( $type, $args + [ 'labels' => [ 'name' => $plural, 'singular_name' => $single, 'add_new_item' => "Add {$single}", 'edit_item' => "Edit {$single}" ] ] );
	}

	$tax = [
		'site_condition'  => [ 'af_garden', 'Site conditions', 'gardens/site-condition' ],
		'garden_style'    => [ 'af_garden', 'Garden styles', false ],
		'engagement_type' => [ 'af_service', 'Engagement types', false ],
	];
	foreach ( $tax as $name => [ $type, $label, $slug ] ) {
		register_taxonomy( $name, $type, [
			'label'        => $label,
			'hierarchical' => true,
			'show_in_rest' => true,
			'public'       => (bool) $slug,
			'rewrite'      => $slug ? [ 'slug' => $slug, 'with_front' => false ] : false,
		] );
	}

	foreach ( af_fields() as $type => $fields ) {
		foreach ( $fields as $key => [ , $kind ] ) {
			$is_array = in_array( $kind, [ 'lines', 'rows', 'ids' ], true );
			register_post_meta( $type, $key, [
				'single'        => true,
				'type'          => $is_array ? 'array' : ( 'number' === $kind ? 'number' : ( 'int' === $kind ? 'integer' : ( 'bool' === $kind ? 'boolean' : 'string' ) ) ),
				'show_in_rest'  => $is_array ? [ 'schema' => [ 'type' => 'array', 'items' => [ 'type' => 'ids' === $kind ? 'integer' : 'string' ] ] ] : true,
				'auth_callback' => fn() => current_user_can( 'edit_posts' ),
			] );
		}
	}
}

/* ---------------------------------------------------------------- image type (Media Library) */

/** What an image is, so a render or stand-in is never read as a photograph of finished work. */
const AF_IMAGE_KINDS = [ '' => 'Photograph', 'visualisation' => 'Visualisation (render or drawing)', 'illustrative' => 'Illustrative stand-in (not project photography)' ];

add_action( 'init', fn() => register_post_meta( 'attachment', 'af_image_kind', [ 'single' => true, 'type' => 'string', 'show_in_rest' => true, 'auth_callback' => fn() => current_user_can( 'upload_files' ) ] ) );

add_filter( 'attachment_fields_to_edit', function ( array $fields, WP_Post $post ) {
	$v    = (string) get_post_meta( $post->ID, 'af_image_kind', true );
	$opts = '';
	foreach ( AF_IMAGE_KINDS as $k => $label ) {
		$opts .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $v, $k, false ), esc_html( $label ) );
	}
	$fields['af_image_kind'] = [ 'label' => 'Image type', 'input' => 'html', 'html' => '<select id="attachments-' . $post->ID . '-af_image_kind" name="attachments[' . $post->ID . '][af_image_kind]">' . $opts . '</select>', 'helps' => 'Shown beside the image. Portraits appear only when this is a photograph.' ];
	return $fields;
}, 10, 2 );

add_filter( 'attachment_fields_to_save', function ( array $post, array $in ) {
	if ( isset( $in['af_image_kind'] ) && array_key_exists( $in['af_image_kind'], AF_IMAGE_KINDS ) ) {
		$in['af_image_kind'] ? update_post_meta( $post['ID'], 'af_image_kind', $in['af_image_kind'] ) : delete_post_meta( $post['ID'], 'af_image_kind' );
	}
	return $post;
}, 10, 2 );

/** Read a meta value with the contract's type. */
function af_meta( int $post_id, string $key ) {
	$v = get_post_meta( $post_id, $key, true );
	return '' === $v ? null : $v;
}

/* ---------------------------------------------------------------- meta boxes */

add_action( 'add_meta_boxes', function ( string $type ) {
	if ( empty( af_fields()[ $type ] ) ) {
		return;
	}
	add_meta_box( 'af_fields', __( 'Alderframe fields', 'alderframe' ), 'af_render_meta_box', $type, 'normal', 'high' );
} );

function af_render_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'af_fields', 'af_fields_nonce' );
	echo '<table class="form-table" role="presentation">';
	foreach ( af_fields()[ $post->post_type ] as $key => [ $label, $kind, $x ] ) {
		$v  = get_post_meta( $post->ID, $key, true );
		$id = 'af_' . $key;
		$req = ! empty( $x['required'] ) ? ' <span aria-hidden="true">*</span><span class="screen-reader-text">(required to publish)</span>' : '';
		echo '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $req . '</label></th><td>'; // phpcs:ignore
		$name = 'af[' . esc_attr( $key ) . ']';
		switch ( $kind ) {
			case 'textarea':
				printf( '<textarea id="%s" name="%s" rows="3" class="large-text">%s</textarea>', esc_attr( $id ), $name, esc_textarea( (string) $v ) ); // phpcs:ignore
				break;
			case 'lines':
			case 'rows':
				printf( '<textarea id="%s" name="%s" rows="5" class="large-text code">%s</textarea>', esc_attr( $id ), $name, esc_textarea( implode( "\n", (array) $v ) ) ); // phpcs:ignore
				break;
			case 'ids':
				printf( '<input id="%s" name="%s" class="regular-text" value="%s" placeholder="12, 34">', esc_attr( $id ), $name, esc_attr( implode( ', ', (array) $v ) ) ); // phpcs:ignore
				break;
			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), $name ); // phpcs:ignore
				foreach ( $x['options'] as $ov => $ol ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $ov ), selected( $v, $ov, false ), esc_html( $ol ?: '—' ) );
				}
				echo '</select>';
				break;
			case 'bool':
				printf( '<input type="checkbox" id="%s" name="%s" value="1"%s>', esc_attr( $id ), $name, checked( (bool) $v, true, false ) ); // phpcs:ignore
				break;
			default:
				$type = in_array( $kind, [ 'number', 'int' ], true ) ? 'number' : 'text';
				$step = 'number' === $kind ? ' step="0.1"' : '';
				printf( '<input type="%s"%s id="%s" name="%s" class="regular-text" value="%s">', $type, $step, esc_attr( $id ), $name, esc_attr( (string) $v ) ); // phpcs:ignore
		}
		if ( ! empty( $x['help'] ) ) {
			echo '<p class="description">' . esc_html( $x['help'] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</table>';
}

/** Sanitise one value against the contract. */
function af_clean( $raw, string $kind, array $x ) {
	switch ( $kind ) {
		case 'number':
			return '' === $raw ? '' : max( $x['min'] ?? -INF, (float) $raw );
		case 'int':
			return '' === $raw ? '' : (int) min( $x['max'] ?? PHP_INT_MAX, max( $x['min'] ?? PHP_INT_MIN, (int) $raw ) );
		case 'bool':
			return $raw ? true : false;
		case 'select':
			return array_key_exists( $raw, $x['options'] ) ? $raw : '';
		case 'ids':
			$ids = array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $raw ) ) ) );
			return isset( $x['max_rows'] ) ? array_slice( $ids, 0, $x['max_rows'] ) : $ids;
		case 'lines':
		case 'rows':
			$rows = array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\R/', (string) $raw ) ) ) );
			return isset( $x['max_rows'] ) ? array_slice( $rows, 0, $x['max_rows'] ) : $rows;
		case 'textarea':
			$s = sanitize_textarea_field( (string) $raw );
			return isset( $x['max'] ) ? mb_substr( $s, 0, $x['max'] ) : $s;
		default:
			$s = sanitize_text_field( (string) $raw );
			return isset( $x['max'] ) ? mb_substr( $s, 0, $x['max'] ) : $s;
	}
}

add_action( 'save_post', function ( int $post_id, WP_Post $post ) {
	if ( ! isset( $_POST['af_fields_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['af_fields_nonce'] ), 'af_fields' ) ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$input = wp_unslash( $_POST['af'] ?? [] ); // phpcs:ignore -- sanitised per field below
	foreach ( af_fields()[ $post->post_type ] ?? [] as $key => [ , $kind, $x ] ) {
		$val = af_clean( $input[ $key ] ?? '', $kind, $x );
		( '' === $val || [] === $val ) ? delete_post_meta( $post_id, $key ) : update_post_meta( $post_id, $key, $val );
	}
	af_publish_gate( $post_id );
}, 20, 2 );

/* ---------------------------------------------------------------- publish gate */

/** Errors that block publication; drafts may be incomplete. One rule set for every save path. */
function af_validate_record( int $post_id ): array {
	$post   = get_post( $post_id );
	$errors = [];
	foreach ( af_fields()[ $post->post_type ] ?? [] as $key => [ $label, , $x ] ) {
		$v = get_post_meta( $post_id, $key, true );
		if ( ! empty( $x['required'] ) && ( '' === $v || [] === $v || null === $v ) ) {
			$errors[] = sprintf( '%s is required to publish.', $label );
		}
	}
	if ( 'af_service' === $post->post_type ) {
		$min = (int) get_post_meta( $post_id, 'lead_weeks_min', true );
		$max = (int) get_post_meta( $post_id, 'lead_weeks_max', true );
		if ( $min && $max && $max < $min ) {
			$errors[] = 'Maximum lead weeks must be at least the minimum.';
		}
	}
	if ( 'af_garden' === $post->post_type && 'approved' === get_post_meta( $post_id, 'rights_status', true ) && ! has_post_thumbnail( $post_id ) ) {
		$errors[] = 'An approved garden needs a lead photograph.';
	}
	return $errors;
}

function af_publish_gate( int $post_id ): void {
	if ( 'publish' !== get_post_status( $post_id ) || ! str_starts_with( get_post_type( $post_id ), 'af_' ) ) {
		return;
	}
	$errors = af_validate_record( $post_id );
	if ( $errors ) {
		remove_action( 'save_post', __FUNCTION__ );
		wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
		set_transient( 'af_errors_' . get_current_user_id(), $errors, 60 );
	}
}

// REST saves skip the meta box: run the same gate after insert.
add_action( 'init', function () {
	foreach ( array_keys( af_fields() ) as $type ) {
		if ( str_starts_with( $type, 'af_' ) ) {
			add_action( "rest_after_insert_{$type}", fn( $post ) => af_publish_gate( $post->ID ) );
		}
	}
}, 20 );

add_action( 'admin_notices', function () {
	$errors = get_transient( 'af_errors_' . get_current_user_id() );
	if ( ! $errors ) {
		return;
	}
	delete_transient( 'af_errors_' . get_current_user_id() );
	echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Saved as draft. Publication needs:', 'alderframe' ) . '</strong></p><ul>';
	foreach ( $errors as $e ) {
		echo '<li>' . esc_html( $e ) . '</li>';
	}
	echo '</ul></div>';
} );
