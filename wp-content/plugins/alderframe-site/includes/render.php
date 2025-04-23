<?php
/**
 * Dynamic block renderers. Each returns '' for an empty optional section (no empty wrappers).
 * Markup only; presentation lives in the theme (assets/css/site.css).
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ helpers */

function af_img( $id, string $size = 'large', array $attr = [] ): string {
	return $id ? wp_get_attachment_image( (int) $id, $size, false, $attr ) : '';
}

/** Short label for non-photographic images ('' for photographs). Set per image in the Media Library. */
function af_image_note( $id ): string {
	return [ 'visualisation' => 'Visualisation', 'illustrative' => 'Illustrative image, not project photography' ][ (string) get_post_meta( (int) $id, 'af_image_kind', true ) ] ?? '';
}

/** Caption + image-type note as one figcaption, or ''. */
function af_figcaption( $id, bool $with_caption = true ): string {
	$cap  = $with_caption ? (string) wp_get_attachment_caption( (int) $id ) : '';
	$note = af_image_note( $id );
	if ( ! $cap && ! $note ) {
		return '';
	}
	return '<figcaption>' . esc_html( $cap ) . ( $note ? '<span class="af-note">' . esc_html( $note ) . '</span>' : '' ) . '</figcaption>';
}

function af_current_id( $block ): int {
	return (int) ( $block->context['postId'] ?? get_queried_object_id() );
}

function af_wrap( string $class, string $inner, string $tag = 'section', array $extra = [] ): string {
	return sprintf( '<%1$s %2$s>%3$s</%1$s>', $tag, get_block_wrapper_attributes( [ 'class' => $class ] + $extra ), $inner );
}

function af_link( string $label, string $url, string $class = 'af-link' ): string {
	return $label && $url ? sprintf( '<a class="%s" href="%s">%s</a>', esc_attr( $class ), esc_url( $url ), esc_html( $label ) ) : '';
}

function af_btn( string $label, string $url, string $variant = 'action' ): string {
	return af_link( $label, $url, 'af-btn af-btn--' . $variant );
}

function af_visit_url( int $service_id = 0 ): string {
	$url = home_url( '/site-visit/' );
	return $service_id ? add_query_arg( 'service', $service_id, $url ) . '#request' : $url;
}

/* ------------------------------------------------------------------ chrome */

function af_nav_items(): array {
	return [
		[ 'Gardens', home_url( '/gardens/' ), 'gardens' ],
		[ 'Services', home_url( '/design-services/' ), 'design-services' ],
		[ 'Fees', home_url( '/design-fees/' ), 'design-fees' ],
		[ 'Studio', home_url( '/studio/' ), 'studio' ],
		[ 'Journal', home_url( '/journal/' ), 'journal' ],
	];
}

function af_is_current( string $section ): bool {
	$path = trim( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '', '/' ); // phpcs:ignore
	$home = trim( wp_parse_url( home_url(), PHP_URL_PATH ) ?? '', '/' );
	$path = trim( substr( $path, strlen( $home ) ), '/' );
	return str_starts_with( $path . '/', $section . '/' );
}

function af_render_site_header( $a, $c, $block ): string {
	$items = '';
	foreach ( af_nav_items() as [ $label, $url, $section ] ) {
		$cur = af_is_current( $section ) ? ' aria-current="page"' : '';
		$sub = '';
		if ( 'design-services' === $section && ( $services = af_services() ) ) {
			$sub = '<ul class="af-nav__sub">';
			foreach ( $services as $s ) {
				$sub .= sprintf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $s ) ), esc_html( get_the_title( $s ) ) );
			}
			$sub .= '</ul>';
		}
		$items .= sprintf( '<li%s><a href="%s"%s>%s</a>%s</li>', $sub ? ' class="has-sub"' : '', esc_url( $url ), $cur, esc_html( $label ), $sub );
	}
	$announce = af_active_announcement();
	$bar      = $announce ? '<p class="af-announce">' . esc_html( $announce ) . '</p>' : '';

	return $bar . '<div class="af-header" data-af-header>
	<a class="af-skip" href="#main">Skip to content</a>
	<div class="af-header__bar">
		<a class="af-logo" href="' . esc_url( home_url( '/' ) ) . '" aria-label="Alderframe Studio, home"><span>Alderframe</span> <span>Studio</span></a>
		<button class="af-menu-toggle" type="button" aria-expanded="false" aria-controls="af-nav" data-af-menu><span class="af-menu-toggle__label">Menu</span><span class="af-menu-toggle__icon" aria-hidden="true"></span></button>
		<nav class="af-nav" id="af-nav" aria-label="Primary">
			<ul class="af-nav__list">' . $items . '</ul>
			' . af_btn( (string) af_option( 'cta_label' ), af_visit_url(), 'action af-nav__cta' ) . '
		</nav>
	</div>
</div>';
}

function af_render_site_footer(): string {
	$links = '';
	foreach ( array_merge( af_nav_items(), [ [ 'Contact', home_url( '/contact/' ), 'contact' ], [ 'Garden brief', home_url( '/garden-brief/' ), 'garden-brief' ] ] ) as [ $label, $url ] ) {
		$links .= '<li>' . af_link( $label, $url ) . '</li>';
	}
	$legal = '';
	foreach ( [ 'Privacy' => '/privacy/', 'Terms' => '/terms/', 'Cookies' => '/cookies/' ] as $label => $path ) {
		$legal .= '<li>' . af_link( $label, home_url( $path ) ) . '</li>';
	}
	return '<div class="af-footer">
		<div class="af-footer__top">
			<div>
				<p class="af-footer__name">Alderframe Studio</p>
				<p class="af-footer__lead">' . esc_html( af_option( 'tagline' ) ) . '</p>
			</div>
		</div>
		<div class="af-footer__grid">
			<nav aria-label="Footer"><h2 class="af-footer__h">Pages</h2><ul class="af-footer__links">' . $links . '</ul></nav>
			<div><h2 class="af-footer__h">Studio</h2>
			<address class="af-footer__contact">
				<span>' . esc_html( af_option( 'address_1' ) ) . '</span>
				<span>' . esc_html( af_option( 'address_2' ) ) . '</span>
				<a href="' . esc_attr( af_tel( af_option( 'phone' ) ) ) . '">' . esc_html( af_option( 'phone' ) ) . '</a>
				<a href="mailto:' . esc_attr( af_option( 'email' ) ) . '">' . esc_html( af_option( 'email' ) ) . '</a>
				<span>' . esc_html( af_option( 'hours' ) ) . '</span>
			</address></div>
			<div><h2 class="af-footer__h">Where we work</h2>
			<p class="af-footer__contact">Toronto and the Golden Horseshoe: postcodes starting ' . esc_html( af_option( 'postcode_prefixes' ) ) . '.</p></div>
		</div>
		<div class="af-footer__base">
		<ul class="af-footer__legal">' . $legal . '<li><button type="button" class="af-linkbtn" data-af-consent>Consent preferences</button></li></ul>
		<p>© ' . esc_html( wp_date( 'Y' ) ) . ' ' . esc_html( af_option( 'company' ) ) . '</p>
	</div>
</div>';
}

/* ------------------------------------------------------------------ page modules */

function af_render_hero( $a ): string {
	$size  = $a['size'] ?? 'page';
	$img   = af_img( $a['imageId'] ?? 0, 'full', [ 'class' => 'af-hero__img', 'fetchpriority' => 'high', 'loading' => 'eager', 'sizes' => '100vw' ] );
	$intro = ! empty( $a['intro'] ) ? '<p class="af-hero__intro">' . esc_html( $a['intro'] ) . '</p>' : '';
	$acts  = af_btn( $a['ctaLabel'] ?? '', $a['ctaUrl'] ?? '' ) . af_link( $a['secondaryLabel'] ?? '', $a['secondaryUrl'] ?? '', 'af-link af-link--quiet' );
	$acts  = $acts ? '<div class="af-hero__actions">' . $acts . '</div>' : '';
	return af_wrap( 'af-hero af-hero--' . sanitize_html_class( $size ), '
	<div class="af-hero__text">
		<h1 class="af-hero__title">' . esc_html( $a['heading'] ?? '' ) . '</h1>
		<div class="af-hero__side">' . $intro . $acts . '</div>
	</div>' . ( $img ? '<figure class="af-hero__media">' . $img . af_figcaption( $a['imageId'], false ) . '</figure>' : '' ) );
}

function af_render_site_model( $a, $c = null, $block = null ): string {
	// On a garden page the model appears only where the garden opts in (it depicts one specific design).
	$id = $block ? af_current_id( $block ) : 0;
	if ( $id && 'af_garden' === get_post_type( $id ) && ! get_post_meta( $id, 'site_model', true ) ) {
		return '';
	}
	$steps = '';
	foreach ( (array) ( $a['steps'] ?? [] ) as $i => $s ) {
		$steps .= sprintf( '<li class="af-model__step" data-step="%d"><span class="af-model__num" aria-hidden="true">%02d</span><h3>%s</h3><p>%s</p></li>', $i, $i + 1, esc_html( $s['title'] ?? '' ), esc_html( $s['text'] ?? '' ) );
	}
	// Photoreal Blender render (dev/blender/ravine_garden.py), played as a scroll-scrubbed frame sequence.
	$base   = get_theme_file_uri( 'assets/seq/' );
	$frames = count( glob( get_theme_file_path( 'assets/seq/f_*.webp' ) ) ?: [] );
	if ( ! $frames ) {
		return '';
	}
	$poster = $base . sprintf( 'f_%03d.webp', $frames );
	return af_wrap( 'af-model', '
	<div class="af-model__sticky">
		<div class="af-model__stage" data-af-seq="' . esc_url( $base ) . '" data-frames="' . (int) $frames . '">
			<img class="af-model__poster" src="' . esc_url( $poster ) . '" alt="Rendered ravine garden: three dry-stack limestone terraces, a corten rill and ferns beneath mature trees" width="1152" height="720" loading="lazy" decoding="async">
			<canvas class="af-model__canvas" width="1152" height="720" aria-hidden="true"></canvas>
			<p class="af-model__note">Visualisation</p>
		</div>
		<div class="af-model__copy">
			<h2 class="af-h2">' . esc_html( $a['heading'] ?? '' ) . '</h2>
			' . ( empty( $a['text'] ) ? '' : '<p class="af-model__text">' . esc_html( $a['text'] ) . '</p>' ) . ( $steps ? '<ol class="af-model__steps">' . $steps . '</ol>' : '' ) . '
		</div>
	</div>', 'section', [ 'data-af-model-section' => '' ] );
}

function af_garden_card( WP_Post $g, string $size = 'large' ): string {
	$meta = [];
	if ( $cond = implode( ', ', wp_get_post_terms( $g->ID, 'site_condition', [ 'fields' => 'names' ] ) ) ) {
		$meta[] = [ 'Site condition', $cond ];
	}
	if ( $loc = af_meta( $g->ID, 'locality_label' ) ) {
		$meta[] = [ 'Locality', $loc ];
	}
	if ( $year = af_meta( $g->ID, 'completion_year' ) ) {
		$meta[] = [ 'Completed', $year ];
	}
	if ( af_image_note( get_post_thumbnail_id( $g ) ) ) {
		$meta[] = [ 'Image', 'Illustrative image' ];
	}
	$dl = '';
	foreach ( $meta as [ $k, $v ] ) {
		$dl .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	$thumb = get_post_thumbnail_id( $g );
	return '<article class="af-card">
		<a class="af-card__link" href="' . esc_url( get_permalink( $g ) ) . '">
			<figure class="af-card__media">' . af_img( $thumb, $size, [ 'loading' => 'lazy', 'sizes' => '(min-width: 1100px) 33vw, (min-width: 720px) 50vw, 100vw' ] ) . '</figure>
			<h3 class="af-card__title">' . esc_html( get_the_title( $g ) ) . '</h3>
		</a>
		<dl class="af-meta">' . $dl . '</dl>
	</article>';
}

function af_render_garden_grid( $a, $c, $block ): string {
	$args = [ 'count' => (int) ( $a['count'] ?? 6 ), 'featured' => ! empty( $a['featured'] ), 'slugs' => $a['slugs'] ?? '' ];
	if ( 'af_service' === get_post_type( af_current_id( $block ) ) ) {
		$args['service'] = af_current_id( $block );
	}
	$gardens = af_gardens( $args );
	if ( ! $gardens ) {
		return '';
	}
	$layout = sanitize_html_class( $a['layout'] ?? 'grid' );
	$lead   = '';
	if ( 'case' === $layout && ( $lead = af_garden_case( $gardens[0] ) ) ) {
		array_shift( $gardens );
	}
	$cards = implode( '', array_map( 'af_garden_card', $gardens ) );
	$head  = ! empty( $a['heading'] ) ? '<div class="af-section-head"><h2 class="af-h2">' . esc_html( $a['heading'] ) . '</h2>' . af_link( $a['linkLabel'] ?? '', $a['linkUrl'] ?? '' ) . '</div>' : '';
	return af_wrap( 'af-gardens af-gardens--' . $layout, $head . $lead . ( $cards ? '<div class="af-gardens__grid">' . $cards . '</div>' : '' ) );
}

function af_story_fields(): array {
	return [ 'design_brief' => 'The site and the brief', 'design_response' => 'Design response', 'outcome' => 'Outcome' ];
}

/** One garden told as site, response, outcome, with its detail images. '' when the story fields are empty. */
function af_garden_case( WP_Post $g ): string {
	$rows = '';
	foreach ( af_story_fields() as $k => $label ) {
		if ( $v = af_meta( $g->ID, $k ) ) {
			$rows .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
		}
	}
	if ( ! $rows ) {
		return '';
	}
	$thumb  = get_post_thumbnail_id( $g );
	$facts  = array_filter( [ implode( ', ', wp_get_post_terms( $g->ID, 'site_condition', [ 'fields' => 'names' ] ) ), af_meta( $g->ID, 'locality_label' ), af_area( $g->ID ), af_meta( $g->ID, 'completion_year' ) ] );
	$detail = '';
	foreach ( array_slice( array_filter( (array) get_post_meta( $g->ID, 'photo_series', true ) ), 0, 2 ) as $img ) {
		$detail .= '<figure>' . af_img( $img, 'medium_large', [ 'loading' => 'lazy', 'sizes' => '(min-width: 900px) 20vw, 50vw' ] ) . af_figcaption( $img ) . '</figure>';
	}
	return '<article class="af-case">
		<figure class="af-case__lead">' . af_img( $thumb, 'large', [ 'loading' => 'lazy', 'sizes' => '(min-width: 900px) 58vw, 100vw' ] ) . af_figcaption( $thumb, false ) . '</figure>
		<div class="af-case__text">
			<p class="af-case__facts">' . esc_html( implode( ' · ', $facts ) ) . '</p>
			<h3 class="af-case__title"><a href="' . esc_url( get_permalink( $g ) ) . '">' . esc_html( get_the_title( $g ) ) . '</a></h3>
			<dl class="af-case__story">' . $rows . '</dl>
			' . ( $detail ? '<div class="af-case__details">' . $detail . '</div>' : '' ) . '
		</div>
	</article>';
}

function af_render_garden_archive(): string {
	$cards = '';
	while ( have_posts() ) {
		the_post();
		$cards .= af_garden_card( get_post() );
	}
	wp_reset_postdata();
	if ( ! $cards ) {
		return af_wrap( 'af-empty', '<p>No completed gardens match this site condition yet.</p>' . af_link( 'Show all gardens', home_url( '/gardens/' ) ) );
	}
	global $wp_query;
	$pages = paginate_links( [ 'total' => $wp_query->max_num_pages, 'prev_text' => 'Newer', 'next_text' => 'Older', 'type' => 'list' ] );
	return af_wrap( 'af-gardens af-gardens--index', '<div class="af-gardens__grid">' . $cards . '</div>' . ( $pages ? '<nav class="af-pager" aria-label="Pagination">' . $pages . '</nav>' : '' ) );
}

function af_render_garden_filter(): string {
	$terms = get_terms( [ 'taxonomy' => 'site_condition', 'hide_empty' => true ] );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '';
	}
	$active = is_tax( 'site_condition' ) ? get_queried_object_id() : 0;
	$items  = sprintf( '<li><a href="%s"%s>All gardens</a></li>', esc_url( home_url( '/gardens/' ) ), $active ? '' : ' aria-current="page"' );
	foreach ( $terms as $t ) {
		$items .= sprintf( '<li><a href="%s"%s>%s</a></li>', esc_url( get_term_link( $t ) ), $active === $t->term_id ? ' aria-current="page"' : '', esc_html( $t->name ) );
	}
	global $wp_query;
	$n = (int) $wp_query->found_posts;
	return af_wrap( 'af-filter', '<ul class="af-filter__list">' . $items . '</ul><p class="af-filter__count" aria-live="polite">' . esc_html( sprintf( _n( '%d garden', '%d gardens', $n, 'alderframe' ), $n ) ) . '</p>', 'nav', [ 'aria-label' => 'Filter by site condition' ] );
}

function af_render_garden_facts( $a, $c, $block ): string {
	$id   = af_current_id( $block );
	$rows = [
		'Locality'  => af_meta( $id, 'locality_label' ),
		'Area'      => af_area( $id ),
		'Completed' => af_meta( $id, 'completion_year' ),
		'Condition' => implode( ', ', wp_get_post_terms( $id, 'site_condition', [ 'fields' => 'names' ] ) ),
		'Style'     => implode( ', ', wp_get_post_terms( $id, 'garden_style', [ 'fields' => 'names' ] ) ),
	];
	$services = array_map( fn( $s ) => af_link( get_the_title( $s ), get_permalink( $s ) ), af_refs( (array) get_post_meta( $id, 'service_refs', true ), 'af_service' ) );
	$dl       = '';
	foreach ( array_filter( $rows ) as $k => $v ) {
		$dl .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	if ( $services ) {
		$dl .= '<div><dt>Services</dt><dd>' . implode( '<br>', $services ) . '</dd></div>';
	}
	return $dl ? af_wrap( 'af-facts', '<dl>' . $dl . '</dl>' ) : '';
}

function af_render_garden_narrative( $a, $c, $block ): string {
	$id    = af_current_id( $block );
	$parts = '';
	foreach ( af_story_fields() as $k => $label ) {
		if ( $v = af_meta( $id, $k ) ) {
			$parts .= '<div><h2 class="af-h3">' . esc_html( $label ) . '</h2><p class="af-lede">' . esc_html( $v ) . '</p></div>';
		}
	}
	return $parts ? af_wrap( 'af-narrative', $parts ) : '';
}

function af_render_garden_gallery( $a, $c, $block ): string {
	$ids = (array) get_post_meta( af_current_id( $block ), 'photo_series', true );
	$out = '';
	foreach ( array_slice( array_filter( $ids ), 0, 12 ) as $i => $img ) {
		$out .= '<figure class="af-gallery__item">' . af_img( $img, 'large', [ 'loading' => 'lazy', 'sizes' => '(min-width: 900px) 50vw, 100vw' ] ) . af_figcaption( $img ) . '</figure>';
	}
	return $out ? af_wrap( 'af-gallery', $out ) : '';
}

function af_render_related_gardens( $a, $c, $block ): string {
	$id      = af_current_id( $block );
	$gardens = 'af_garden' === get_post_type( $id ) ? af_related_gardens( $id, (int) ( $a['count'] ?? 2 ) ) : [];
	return $gardens ? af_wrap( 'af-gardens af-gardens--grid', '<div class="af-section-head"><h2 class="af-h2">More completed gardens</h2>' . af_link( 'All gardens', home_url( '/gardens/' ) ) . '</div><div class="af-gardens__grid">' . implode( '', array_map( 'af_garden_card', $gardens ) ) . '</div>' ) : '';
}

function af_render_related_garden( $a, $c, $block ): string {
	$g = af_refs( [ (int) get_post_meta( af_current_id( $block ), 'featured_garden', true ) ], 'af_garden' );
	if ( ! $g ) {
		return '';
	}
	$g = $g[0];
	return af_wrap( 'af-related', '<a class="af-related__link" href="' . esc_url( get_permalink( $g ) ) . '">' . af_img( get_post_thumbnail_id( $g ), 'medium_large', [ 'loading' => 'lazy' ] ) . '<span class="af-related__text"><span class="af-related__label">Related garden</span><span class="af-related__title">' . esc_html( get_the_title( $g ) ) . '</span><span class="af-link">View garden</span></span></a>', 'aside', [ 'aria-label' => 'Related garden' ] );
}

function af_render_owner_quote( $a, $c, $block ): string {
	$id     = af_current_id( $block );
	$quotes = 'af_garden' === get_post_type( $id ) ? af_quotes( $id ) : af_quotes();
	if ( ! empty( $a['attribution'] ) ) {
		$quotes = array_values( array_filter( $quotes, fn( $q ) => str_contains( (string) get_post_meta( $q->ID, 'attribution', true ), $a['attribution'] ) ) );
	}
	if ( ! $quotes ) {
		return '';
	}
	$q = $quotes[0];
	return af_wrap( 'af-quote', '<figure><blockquote><p>' . esc_html( '“' . get_post_meta( $q->ID, 'quote', true ) . '”' ) . '</p></blockquote><figcaption>' . esc_html( get_post_meta( $q->ID, 'attribution', true ) ) . '</figcaption></figure>' );
}

function af_render_designer_directory( $a ): string {
	$people = af_designers();
	if ( ! $people ) {
		return '';
	}
	$out = '';
	foreach ( $people as $p ) {
		$credits = af_refs( (array) get_post_meta( $p->ID, 'garden_refs', true ), 'af_garden' );
		$cred    = ( empty( $a['compact'] ) && $credits ) ? '<p class="af-person__credits"><span>Gardens</span> ' . implode( ', ', array_map( fn( $g ) => af_link( get_the_title( $g ), get_permalink( $g ) ), $credits ) ) . '</p>' : '';
		$pid     = get_post_thumbnail_id( $p );
		// A person is shown by photograph or not at all: stand-in portraits stay in the CMS but never render.
		$photo   = $pid && ! af_image_note( $pid ) ? '<figure class="af-person__portrait">' . af_img( $pid, 'large', [ 'loading' => 'lazy', 'sizes' => '(min-width: 900px) 33vw, 100vw' ] ) . '</figure>' : '';
		$out    .= '<article class="af-person' . ( $photo ? '' : ' af-person--text' ) . '">' . $photo . '<h3 class="af-person__name">' . esc_html( get_the_title( $p ) ) . '</h3><p class="af-person__role">' . esc_html( get_post_meta( $p->ID, 'role', true ) ) . '</p>' . $cred . '</article>';
	}
	$head = ! empty( $a['heading'] ) ? '<div class="af-section-head"><h2 class="af-h2">' . esc_html( $a['heading'] ) . '</h2></div>' : '';
	return af_wrap( 'af-people', $head . '<div class="af-people__grid">' . $out . '</div>' );
}

function af_render_service_cards( $a ): string {
	$rows = '';
	foreach ( af_services() as $i => $s ) {
		$fee   = get_post_meta( $s->ID, 'fee_from_cad', true );
		$rows .= '<li class="af-service">
			<a class="af-service__link" href="' . esc_url( get_permalink( $s ) ) . '">
				<span class="af-service__num" aria-hidden="true">' . sprintf( '%02d', $i + 1 ) . '</span>
				<span class="af-service__name">' . esc_html( get_the_title( $s ) ) . '</span>
				<span class="af-service__scope">' . esc_html( get_post_meta( $s->ID, 'scope_summary', true ) ) . '</span>
				<span class="af-service__fee">' . esc_html( '' !== $fee ? af_format_fee( (float) $fee ) : '' ) . '</span>
				<span class="af-service__weeks">' . esc_html( af_weeks( $s->ID ) ) . '</span>
			</a>
		</li>';
	}
	if ( ! $rows ) {
		return '';
	}
	$head = ! empty( $a['heading'] ) ? '<div class="af-section-head"><h2 class="af-h2">' . esc_html( $a['heading'] ) . '</h2>' . af_link( $a['linkLabel'] ?? '', $a['linkUrl'] ?? '' ) . '</div>' : '';
	return af_wrap( 'af-services', $head . '<ol class="af-services__list">' . $rows . '</ol>' );
}

function af_render_service_compare( $a ): string {
	$services = af_services();
	$all      = [];
	foreach ( $services as $s ) {
		$all = array_merge( $all, (array) get_post_meta( $s->ID, 'deliverables', true ) );
	}
	$all = array_values( array_unique( array_filter( $all ) ) );
	if ( ! $all ) {
		return '';
	}
	$head = '<tr><th scope="col"><span class="screen-reader-text">Deliverable</span></th>';
	foreach ( $services as $s ) {
		$head .= '<th scope="col">' . af_link( get_the_title( $s ), get_permalink( $s ) ) . '</th>';
	}
	$body = '';
	foreach ( $all as $d ) {
		$body .= '<tr><th scope="row">' . esc_html( $d ) . '</th>';
		foreach ( $services as $s ) {
			$has   = in_array( $d, (array) get_post_meta( $s->ID, 'deliverables', true ), true );
			$body .= '<td>' . ( $has ? '<span class="af-tick" aria-hidden="true"></span><span class="screen-reader-text">Included</span>' : '<span class="screen-reader-text">Not included</span>' ) . '</td>';
		}
		$body .= '</tr>';
	}
	return af_wrap( 'af-compare', '<h2 class="af-h2">' . esc_html( $a['heading'] ?? 'Compare deliverables' ) . '</h2><div class="af-compare__scroll" tabindex="0" role="region" aria-label="Deliverables by service"><table><thead>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>' );
}

function af_render_service_facts( $a, $c, $block ): string {
	$id   = af_current_id( $block );
	$fee  = get_post_meta( $id, 'fee_from_cad', true );
	$rows = array_filter( [
		'Design fee'      => '' !== $fee ? af_format_fee( (float) $fee ) : '',
		'Lead time'       => af_weeks( $id ),
		'Engagement type' => get_post_meta( $id, 'engagement_label', true ),
	] );
	$dl = '';
	foreach ( $rows as $k => $v ) {
		$dl .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	$scope = get_post_meta( $id, 'scope_summary', true );
	return af_wrap( 'af-facts af-facts--service', ( $scope ? '<p class="af-lede">' . esc_html( $scope ) . '</p>' : '' ) . '<dl>' . $dl . '</dl>' );
}

function af_render_process_timeline( $a, $c, $block ): string {
	$id    = af_current_id( $block );
	$steps = 'af_service' === get_post_type( $id ) ? (array) get_post_meta( $id, 'deliverable_steps', true ) : [];
	if ( ! $steps && ! empty( $a['items'] ) ) {
		$steps = array_map( fn( $i ) => $i['when'] . '|' . $i['what'] . ( empty( $i['text'] ) ? '' : '|' . $i['text'] ), $a['items'] );
	}
	$steps = array_filter( $steps );
	if ( ! $steps ) {
		return '';
	}
	$li = '';
	foreach ( array_values( $steps ) as $i => $s ) {
		$parts = array_map( 'trim', explode( '|', $s, 3 ) );
		$big   = isset( $parts[1] ) ? $parts[0] : sprintf( '%02d', $i + 1 );
		$small = $parts[1] ?? $parts[0];
		$text  = empty( $parts[2] ) ? '' : '<p class="af-timeline__text">' . esc_html( $parts[2] ) . '</p>';
		$li   .= '<li><span class="af-timeline__big">' . esc_html( $big ) . '</span><span class="af-timeline__label">' . esc_html( $small ) . '</span>' . $text . '</li>';
	}
	$intro = empty( $a['intro'] ) ? '' : '<p class="af-timeline__intro">' . esc_html( $a['intro'] ) . '</p>';
	return af_wrap( 'af-timeline' . ( $text ? ' af-timeline--detailed' : '' ), '<div class="af-timeline__head"><h2 class="af-h2">' . esc_html( $a['heading'] ?? 'Process timeline' ) . '</h2>' . $intro . '</div><ol class="af-timeline__list">' . $li . '</ol>' );
}

function af_render_fee_table( $a, $c, $block ): string {
	$rows = (array) get_post_meta( af_current_id( $block ), 'fee_packages', true );
	$out  = '';
	foreach ( array_filter( $rows ) as $r ) {
		[ $name, $scope, $amount, $basis, $inc ] = array_pad( array_map( 'trim', explode( '|', $r ) ), 5, '' );
		$basis_label = [ 'fixed' => 'Fixed fee', 'from' => 'Starting from', 'hourly' => 'Hourly basis' ][ $basis ] ?? '';
		$incs        = array_filter( array_map( 'trim', explode( ';', $inc ) ) );
		$out        .= '<article class="af-fee">
			<h3 class="af-fee__name">' . esc_html( $name ) . '</h3>
			<p class="af-fee__amount"><span class="af-fee__basis">' . esc_html( $basis_label ) . '</span> ' . esc_html( af_format_fee( (float) $amount, $basis ) ) . '</p>
			<p class="af-fee__scope">' . esc_html( $scope ) . '</p>
			' . ( $incs ? '<ul class="af-fee__inc">' . implode( '', array_map( fn( $x ) => '<li>' . esc_html( $x ) . '</li>', $incs ) ) . '</ul>' : '' ) . '
		</article>';
	}
	return $out ? af_wrap( 'af-fees', $out ) : '';
}

function af_render_faq_list( $a ): string {
	$faqs = af_faqs( $a['stage'] ?? 'design' );
	if ( ! $faqs ) {
		return '';
	}
	$items = '';
	foreach ( $faqs as $f ) {
		$items .= '<details class="af-faq"><summary>' . esc_html( get_the_title( $f ) ) . '</summary><div class="af-faq__a"><p>' . esc_html( get_post_meta( $f->ID, 'response', true ) ) . '</p></div></details>';
	}
	return af_wrap( 'af-faqs', '<h2 class="af-h2">' . esc_html( $a['heading'] ?? 'Questions before a visit' ) . '</h2><div class="af-faqs__list">' . $items . '</div>' );
}

function af_render_visit_cta( $a, $c, $block ): string {
	$id    = af_current_id( $block );
	$sid   = 'af_service' === get_post_type( $id ) ? $id : 0;
	$label = $a['label'] ?? '' ?: ( $sid ? 'Request a site visit for ' . get_the_title( $sid ) : af_option( 'cta_label' ) );
	return af_wrap( 'af-cta', '<h2 class="af-cta__title">' . esc_html( $a['heading'] ?? 'Tell us about your garden' ) . '</h2>' . ( ! empty( $a['text'] ) ? '<p class="af-cta__text">' . esc_html( $a['text'] ) . '</p>' : '' ) . '<div class="af-cta__action">' . af_btn( $label, af_visit_url( $sid ) ) . '</div>' );
}

function af_render_guide_download( $a, $c, $block ): string {
	$pdf = (int) get_post_meta( af_current_id( $block ), 'brief_pdf', true );
	if ( ! $pdf || 'application/pdf' !== get_post_mime_type( $pdf ) ) {
		return ''; // required on publish; hidden until the client supplies the tagged PDF
	}
	$file = get_attached_file( $pdf );
	$size = $file && file_exists( $file ) ? size_format( filesize( $file ), 1 ) : '';
	return af_wrap( 'af-download', '<a class="af-btn af-btn--action" href="' . esc_url( wp_get_attachment_url( $pdf ) ) . '" download data-af-event="guide_download">Download the garden brief <span class="af-download__meta">PDF ' . esc_html( $size ) . '</span></a>' );
}

function af_render_policy_meta( $a, $c, $block ): string {
	$d = get_post_meta( af_current_id( $block ), 'effective_date', true );
	return $d ? af_wrap( 'af-policy-meta', 'Effective date: ' . esc_html( $d ), 'p' ) : '';
}

function af_render_contact_block( $a ): string {
	$map = af_img( $a['imageId'] ?? 0, 'large', [ 'loading' => 'lazy', 'alt' => 'Map showing the studio on Davenport Road, Toronto' ] );
	return af_wrap( 'af-contact', '<div class="af-contact__details">
		<dl>
			<div><dt>Studio</dt><dd><address>' . esc_html( af_option( 'company' ) ) . '<br>' . esc_html( af_option( 'address_1' ) ) . '<br>' . esc_html( af_option( 'address_2' ) ) . '</address></dd></div>
			<div><dt>Telephone</dt><dd><a href="' . esc_attr( af_tel( af_option( 'phone' ) ) ) . '">' . esc_html( af_option( 'phone' ) ) . '</a></dd></div>
			<div><dt>Email</dt><dd><a href="mailto:' . esc_attr( af_option( 'email' ) ) . '">' . esc_html( af_option( 'email' ) ) . '</a></dd></div>
			<div><dt>Studio hours</dt><dd>' . esc_html( af_option( 'hours' ) ) . '<br>' . esc_html( af_option( 'timezone' ) ) . '</dd></div>
		</dl>
		' . af_btn( (string) af_option( 'cta_label' ), af_visit_url() ) . '
	</div>' . ( $map ? '<figure class="af-contact__map">' . $map . '</figure>' : '' ) );
}

function af_render_site_visit_form(): string {
	return af_wrap( 'af-form-wrap', af_site_visit_form_html(), 'section', [ 'id' => 'request', 'aria-labelledby' => 'af-form-title' ] );
}

function af_render_search_form( $a ): string {
	return af_wrap( 'af-search', '<form role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"><label for="af-s">Search the studio</label><div class="af-search__row"><input id="af-s" type="search" name="s" value="' . esc_attr( get_search_query() ) . '"><button class="af-btn af-btn--moss" type="submit">Search</button></div></form>', 'div' );
}

/* Featured images (garden hero, journal) carry the same image-type note as every other figure. */
add_filter( 'render_block_core/post-featured-image', function ( string $html, array $block ) {
	$id   = get_post_thumbnail_id( $block['attrs']['postId'] ?? get_the_ID() );
	$note = $id ? af_figcaption( $id, false ) : '';
	return $note && str_ends_with( rtrim( $html ), '</figure>' ) ? substr( rtrim( $html ), 0, -9 ) . $note . '</figure>' : $html;
}, 10, 2 );
