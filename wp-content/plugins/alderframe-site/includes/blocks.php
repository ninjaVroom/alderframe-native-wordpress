<?php
/**
 * Block registry. All project blocks are dynamic: the same PHP renders editor preview and front end.
 * one generic editor script (ServerSideRender + text controls for string attributes)
 * instead of a @wordpress/scripts build per block; add per-block React UIs when an editor needs more.
 */

defined( 'ABSPATH' ) || exit;

function af_block_registry(): array {
	$s = fn( $d = '' ) => [ 'type' => 'string', 'default' => $d ];
	$n = fn( $d = 0 ) => [ 'type' => 'number', 'default' => $d ];
	$b = fn( $d = false ) => [ 'type' => 'boolean', 'default' => $d ];
	$arr = [ 'type' => 'array', 'default' => [] ];
	return [
		'site-header'        => [ 'Site header', 'af_render_site_header', [] ],
		'site-footer'        => [ 'Site footer', 'af_render_site_footer', [] ],
		'hero'               => [ 'Hero', 'af_render_hero', [ 'heading' => $s(), 'intro' => $s(), 'imageId' => $n(), 'ctaLabel' => $s(), 'ctaUrl' => $s(), 'secondaryLabel' => $s(), 'secondaryUrl' => $s(), 'size' => $s( 'page' ) ] ],
		'site-model'         => [ 'Site model (3D sequence)', 'af_render_site_model', [ 'heading' => $s(), 'text' => $s(), 'steps' => $arr ] ],
		'garden-grid'        => [ 'Garden grid', 'af_render_garden_grid', [ 'heading' => $s(), 'linkLabel' => $s(), 'linkUrl' => $s(), 'count' => $n( 6 ), 'featured' => $b(), 'slugs' => $s(), 'layout' => $s( 'grid' ) ] ],
		'garden-archive'     => [ 'Garden archive list', 'af_render_garden_archive', [] ],
		'garden-filter'      => [ 'Garden filter', 'af_render_garden_filter', [] ],
		'garden-facts'       => [ 'Garden facts', 'af_render_garden_facts', [] ],
		'garden-narrative'   => [ 'Garden narrative', 'af_render_garden_narrative', [] ],
		'garden-gallery'     => [ 'Garden gallery', 'af_render_garden_gallery', [] ],
		'related-gardens'    => [ 'Related gardens', 'af_render_related_gardens', [ 'count' => $n( 2 ) ] ],
		'related-garden'     => [ 'Related garden (journal)', 'af_render_related_garden', [] ],
		'owner-quote'        => [ 'Owner quote', 'af_render_owner_quote', [ 'attribution' => $s() ] ],
		'designer-directory' => [ 'Designer directory', 'af_render_designer_directory', [ 'heading' => $s(), 'compact' => $b() ] ],
		'service-cards'      => [ 'Service list', 'af_render_service_cards', [ 'heading' => $s(), 'linkLabel' => $s(), 'linkUrl' => $s() ] ],
		'service-compare'    => [ 'Service comparison', 'af_render_service_compare', [ 'heading' => $s( 'Compare deliverables' ) ] ],
		'service-facts'      => [ 'Service facts', 'af_render_service_facts', [] ],
		'process-timeline'   => [ 'Process timeline', 'af_render_process_timeline', [ 'heading' => $s( 'Process timeline' ), 'intro' => $s(), 'items' => $arr ] ],
		'fee-table'          => [ 'Fee table', 'af_render_fee_table', [] ],
		'faq-list'           => [ 'FAQ list', 'af_render_faq_list', [ 'heading' => $s( 'Questions before a visit' ), 'stage' => $s( 'design' ) ] ],
		'visit-cta'          => [ 'Visit call to action', 'af_render_visit_cta', [ 'heading' => $s( 'Tell us about your garden' ), 'text' => $s( 'Most projects start with a 90-minute site walk for a fixed CA$650. We look at grading, soil, drainage and sun with you, then send a written summary and a construction budget range.' ), 'label' => $s() ] ],
		'guide-download'     => [ 'Guide download', 'af_render_guide_download', [] ],
		'policy-meta'        => [ 'Policy effective date', 'af_render_policy_meta', [] ],
		'contact-block'      => [ 'Contact details', 'af_render_contact_block', [ 'imageId' => $n() ] ],
		'site-visit-form'    => [ 'Site-visit request form', 'af_render_site_visit_form', [] ],
		'search-form'        => [ 'Search form', 'af_render_search_form', [] ],
	];
}

add_action( 'init', function () {
	wp_register_script( 'af-blocks-editor', AF_URL . 'assets/editor.js', [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ], AF_VERSION, true );
	wp_add_inline_script( 'af-blocks-editor', 'window.afBlocks = ' . wp_json_encode( array_map( fn( $r ) => [ 'title' => $r[0], 'attributes' => $r[2] ], af_block_registry() ) ) . ';', 'before' );

	foreach ( af_block_registry() as $slug => [ $title, $cb, $attrs ] ) {
		register_block_type( "alderframe/{$slug}", [
			'api_version'     => 3,
			'title'           => $title,
			'category'        => 'alderframe',
			'attributes'      => $attrs,
			'uses_context'    => [ 'postId', 'postType' ],
			'supports'        => [ 'html' => false, 'align' => [ 'wide', 'full' ], 'color' => false, 'typography' => false, 'spacing' => false, 'reusable' => false ],
			'editor_script'   => 'af-blocks-editor',
			'render_callback' => fn( $a, $c, $block ) => $cb( $a, $c, $block ),
		] );
	}
} );

add_filter( 'block_categories_all', fn( $cats ) => array_merge( [ [ 'slug' => 'alderframe', 'title' => 'Alderframe' ] ], $cats ) );
