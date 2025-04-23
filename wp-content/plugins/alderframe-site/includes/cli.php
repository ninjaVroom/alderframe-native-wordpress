<?php
/**
 * wp alderframe seed — idempotent launch fixtures from the approved mockup copy.
 * Every record carries af_source_id, so re-running updates instead of duplicating.
 * Copy that the mockups did not supply legibly (biographies, most FAQs, four journal bodies) is NOT invented.
 */

defined( 'ABSPATH' ) || exit;

function af_b( string $name, array $attrs = [] ): string {
	return '<!-- wp:alderframe/' . $name . ( $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '' ) . " /-->\n\n";
}

function af_prose( string $html ): string {
	return "<!-- wp:group {\"className\":\"af-prose\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group af-prose\">{$html}</div>\n<!-- /wp:group -->\n\n";
}
function af_h2( string $t ): string {
	return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $t ) . "</h2>\n<!-- /wp:heading -->\n";
}
function af_p( string $t ): string {
	return "<!-- wp:paragraph -->\n<p>" . $t . "</p>\n<!-- /wp:paragraph -->\n";
}

class AF_Seed {
	private array $media = [];

	private function upsert( string $type, string $source, array $post, array $meta = [], array $terms = [] ): int {
		$found = get_posts( [ 'post_type' => $type, 'post_status' => 'any', 'meta_key' => 'af_source_id', 'meta_value' => $source, 'fields' => 'ids', 'numberposts' => 1 ] ); // phpcs:ignore
		$post += [ 'post_type' => $type, 'post_status' => 'publish' ];
		if ( $found ) {
			$post['ID'] = $found[0];
		}
		$id = wp_insert_post( wp_slash( $post ), true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		update_post_meta( $id, 'af_source_id', $source );
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		foreach ( $terms as $tax => $names ) {
			wp_set_object_terms( $id, (array) $names, $tax );
		}
		return $id;
	}

	private function img( string $file, string $alt, string $caption = '' ): int {
		if ( isset( $this->media[ $file ] ) ) {
			return $this->media[ $file ];
		}
		$found = get_posts( [ 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => 'af_source_id', 'meta_value' => $file, 'fields' => 'ids', 'numberposts' => 1 ] ); // phpcs:ignore
		if ( $found ) {
			$id = $found[0];
		} else {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$up = wp_upload_bits( $file, null, file_get_contents( AF_PATH . 'seed/images/' . $file ) ); // phpcs:ignore
			if ( ! empty( $up['error'] ) ) {
				WP_CLI::error( $up['error'] );
			}
			$id = wp_insert_attachment( [ 'post_mime_type' => 'image/jpeg', 'post_title' => $alt, 'post_excerpt' => $caption, 'post_status' => 'inherit' ], $up['file'] );
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $up['file'] ) );
			update_post_meta( $id, 'af_source_id', $file );
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		if ( $caption ) {
			wp_update_post( [ 'ID' => $id, 'post_excerpt' => $caption ] );
		}
		// The launch images are generated stand-ins. Label them so none is read as a photograph of finished work;
		// the studio switches each to "Photograph" in the Media Library as real photography replaces it.
		update_post_meta( $id, 'af_image_kind', 'contact-studio-map.jpg' === $file ? '' : 'illustrative' );
		return $this->media[ $file ] = $id;
	}

	public function run(): void {
		update_option( 'permalink_structure', '/journal/%postname%/' );
		update_option( 'category_base', 'journal/category' );
		update_option( 'blogname', 'Alderframe Studio' );
		update_option( 'blogdescription', 'Residential landscape architecture, Toronto' );
		update_option( 'timezone_string', 'America/Toronto' );

		/* services ------------------------------------------------------------- */
		$svc = [];
		$services = [
			[ 'full-garden-masterplan', 'Full garden masterplan', 'Grading, drainage, stonework, lighting and planting for the whole property, drawn in enough detail for contractors to price and build.', 6800, 8, 12, 'Whole property', [ 'Topographic audit', 'Concept and grading', 'Stone details', 'Planting plan', 'Tender package' ] ],
			[ 'courtyard-terrace-architecture', 'Courtyard and terrace design', 'Layout, stone paving, screening and drainage for small city lots and terraces.', 4400, 5, 8, '', [ 'Concept and grading', 'Stone details' ] ],
			[ 'planting-soil-restoration', 'Planting and soil restoration', 'Soil repair and a new planting plan for an existing garden, with native species chosen for the light under your trees and planned to change through the seasons.', 3200, 4, 6, '', [ 'Stone details' ] ],
			[ 'phased-stewardship-seasonal-advisory', 'Construction and seasonal advisory', 'Site visits during the build, nursery tagging, and pruning and aftercare advice for the first years after planting.', 1600, 2, 4, '', [ 'Planting plan', 'Tender package' ] ],
		];
		$steps = [ 'Site survey and soil audit', 'Concept and grading', 'Materials and stone specification', 'Planting plan', 'Construction details and tender', 'Nursery tagging and site observation' ];
		foreach ( $services as $i => [ $slug, $title, $scope, $fee, $min, $max, $eng, $deliv ] ) {
			$svc[ $slug ] = $this->upsert( 'af_service', "service:$slug", [ 'post_title' => $title, 'post_name' => $slug ], [
				'scope_summary' => $scope, 'fee_from_cad' => $fee, 'lead_weeks_min' => $min, 'lead_weeks_max' => $max,
				'engagement_label' => $eng, 'deliverables' => $deliv, 'display_order' => $i + 1,
				// Only the masterplan sheet supplied process steps; other services stay empty until the studio writes them.
				'deliverable_steps' => 0 === $i ? $steps : [],
			] );
		}

		/* gardens --------------------------------------------------------------- */
		$g = [];
		$gardens = [
			[ 'hillside-terrace', 'Hillside Terrace', 'Rosedale Ravine, Toronto', 412.5, 2025, 'Ravine slope', 'Woodland Modern', 'garden-01-hillside-terrace.jpg', 'Three dry-stack limestone terraces stepping down a wooded ravine slope', true, [ 'full-garden-masterplan' ],
				'A steep ravine bank under mature oaks was eroding, and the owners had no level ground to sit or eat outside.',
				'Three dry-stack Algonquin limestone terraces step down the slope, joined by a Corten steel water rill. Ferns and sedges that tolerate shade fill the beds between the walls.',
				[ [ 'garden-01-detail-joinery.jpg', 'Dry-stack limestone wall with tight hand-fitted joints', 'Dry-stack walls: hand-fitted stone, no mortar.' ], [ 'garden-01-detail-planting.jpg', 'Fern and sedge planting bed along a limestone edge', 'Ferns and sedges along a terrace edge.' ] ] ],
			[ 'laneway-courtyard', 'Laneway Courtyard', 'The Annex, Toronto', 148.0, 2025, 'Urban courtyard', '', 'garden-02-courtyard-atelier.jpg', 'Compact courtyard with board-formed planters, a Japanese maple and stone paving', true, [ 'courtyard-terrace-architecture' ], '', '', [] ],
			[ 'oak-canopy-sanctuary', 'Oak Canopy Sanctuary', 'High Park, Toronto', 580.0, 2024, 'Mature shade', '', 'garden-03-canopy-shade.jpg', 'Shaded woodland garden beneath mature red oaks', false, [ 'planting-soil-restoration' ], '', '', [] ],
			[ 'windward-bluff-garden', 'Windward Bluff Garden', 'Oakville Lakeshore', 890.0, 2024, 'Lakeshore bluff', '', 'garden-04-lakeshore-bluff.jpg', 'Grasses and stone seating on a bluff above Lake Ontario', false, [ 'full-garden-masterplan' ], '', '', [] ],
			[ 'rainwater-meadow-court', 'Rainwater Meadow Court', 'Riverdale, Toronto', 310.0, 2023, 'Clay soil', '', 'garden-05-clay-meadow.jpg', 'Boardwalk crossing a planted rain garden with iris and birch', false, [ 'planting-soil-restoration' ], '', '', [] ],
			[ 'heritage-limestone-parterre', 'Heritage Limestone Parterre', 'Cabbagetown, Toronto', 265.0, 2023, 'Urban courtyard', '', 'garden-06-walled-parterre.jpg', 'Clipped boxwood parterre and brick path inside a walled garden', false, [ 'full-garden-masterplan' ], '', '', [] ],
		];
		foreach ( $gardens as [ $slug, $title, $loc, $area, $year, $cond, $style, $lead, $alt, $feat, $svcs, $brief, $resp, $gallery ] ) {
			$photos = array_map( fn( $x ) => $this->img( $x[0], $x[1], $x[2] ), $gallery );
			$id     = $this->upsert( 'af_garden', "garden:$slug", [ 'post_title' => $title, 'post_name' => $slug ], [
				'locality_label' => $loc, 'site_area_m2' => $area, 'completion_year' => $year, 'rights_status' => 'approved',
				'design_brief' => $brief, 'design_response' => $resp, 'photo_series' => $photos, 'featured' => $feat,
				'service_refs' => array_map( fn( $s ) => $svc[ $s ], $svcs ),
			], array_filter( [ 'site_condition' => $cond, 'garden_style' => $style ] ) );
			set_post_thumbnail( $id, $this->img( $lead, $alt ) );
			$g[ $slug ] = $id;
		}
		// The Blender sequence in the theme (dev/blender/ravine_garden.py) models this garden's terraces and rill.
		update_post_meta( $g['hillside-terrace'], 'site_model', true );

		/* designers, quotes, faqs -------------------------------------------------- */
		$people = [
			[ 'claire-alder', 'Claire Alder', 'Principal Landscape Architect, OALA, CSLA', 'designer-01-claire-alder.jpg', [ 'hillside-terrace', 'oak-canopy-sanctuary' ] ],
			[ 'marcus-chen', 'Marcus Chen', 'Senior Planting & Soil Ecologies Lead', 'designer-02-marcus-chen.jpg', [ 'laneway-courtyard', 'rainwater-meadow-court' ] ],
			[ 'elena-rostova', 'Elena Rostova', 'Site Architect & Detailing Lead', 'designer-03-elena-rostova.jpg', [] ], // 0-gardens edge state from the studio sheet
		];
		foreach ( $people as $i => [ $slug, $name, $role, $img, $refs ] ) {
			$id = $this->upsert( 'af_designer', "designer:$slug", [ 'post_title' => $name, 'post_name' => $slug ], [ 'role' => $role, 'display_order' => $i + 1, 'garden_refs' => array_map( fn( $s ) => $g[ $s ], $refs ) ] );
			set_post_thumbnail( $id, $this->img( $img, "Portrait of $name" ) );
		}
		$this->upsert( 'af_feedback', 'quote:halvorsen', [ 'post_title' => 'Halvorsen, Hillside Terrace' ], [ 'quote' => 'Alderframe turned an eroding ravine slope into three calm limestone terraces that feel as though they have belonged to the woods for thirty years.', 'attribution' => 'M. & D. Halvorsen, Hillside Terrace, Rosedale Ravine', 'garden_ref' => $g['hillside-terrace'], 'release_state' => 'pending' ] );
		$this->upsert( 'af_feedback', 'quote:patel', [ 'post_title' => 'Patel, The Annex' ], [ 'quote' => 'Every drawing and plant schedule was ready before spring ground thaw.', 'attribution' => 'R. & S. Patel, The Annex', 'garden_ref' => $g['laneway-courtyard'], 'release_state' => 'pending' ] );
		$this->upsert( 'af_faq', 'faq:budgets', [ 'post_title' => 'How do you estimate construction budgets?' ], [ 'response' => 'Before the drawings go to tender, we check the schematic design against current unit rates from Ontario contractors, so the budget is tested before builders price it.', 'audience_stage' => 'fees', 'display_order' => 1 ] );
		$this->upsert( 'af_faq', 'faq:area', [ 'post_title' => 'Where do you work?' ], [ 'response' => 'Toronto and the Golden Horseshoe: postcodes starting M, L4, L5, L6, L7, L8 or L9. The form checks your postcode before you send it.', 'audience_stage' => 'before-visit', 'display_order' => 1 ] );
		$this->upsert( 'af_faq', 'faq:visit', [ 'post_title' => 'What happens at the site walk, and what does it cost?' ], [ 'response' => 'It is a 90-minute visit for a fixed CA$650. We look at grading, soil, drainage and sun with you, talk through what you want from the garden, and send a written summary with a construction budget range.', 'audience_stage' => 'before-visit', 'display_order' => 2 ] );
		$this->upsert( 'af_faq', 'faq:season', [ 'post_title' => 'When should we start if we want to build in spring?' ], [ 'response' => 'In late autumn. Frozen ground cannot be graded or planted, so spring builds are surveyed in November or December, designed over January and February, and tendered in March, ready to build in April and May.', 'audience_stage' => 'before-visit', 'display_order' => 3 ] );

		/* journal ----------------------------------------------------------------- */
		$authors = [];
		foreach ( [ 'Marcus Chen', 'Elena Rostova', 'Claire Alder' ] as $n ) {
			$login = sanitize_user( strtolower( str_replace( ' ', '.', $n ) ) );
			$u     = get_user_by( 'login', $login );
			$authors[ $n ] = $u ? $u->ID : wp_insert_user( [ 'user_login' => $login, 'user_pass' => wp_generate_password( 32 ), 'display_name' => $n, 'role' => 'author' ] );
		}
		wp_delete_post( 1, true ); // Hello world
		$posts = [
			[ 'designing-for-winter-structure', 'Designing for Winter Structure in Ontario Gardens', 'Marcus Chen', '2026-11-03', 'Planting Ecology', 'How multi-stem birch, clipped yew, and native grass seedheads hold snow and low winter light.', 'journal-01-winter-structure.jpg', 'Snow resting on clipped yew and grass seedheads in low winter light', 'oak-canopy-sanctuary',
				af_p( 'In Southern Ontario, a residential garden spends five months without deciduous leaf cover. Designing structure around bark, stone risers, and persistent seedheads anchors winter views from indoors.' ) ],
			[ 'rebuilding-compacted-urban-clay', 'Rebuilding Compacted Urban Clay Before Planting', 'Marcus Chen', '2026-10-06', 'Seasonal Aftercare', '', 'journal-02-soil-remediation.jpg', 'Hands lifting a core of compacted clay soil', '', '' ],
			[ 'dry-stack-limestone-vs-mortared-walls', 'Dry-Stack Algonquin Limestone vs. Mortared Walls in Freeze-Thaw Zones', 'Elena Rostova', '2026-09-08', 'Stone & Grading', '', '', '', 'hillside-terrace', '' ],
			[ 'understory-ferns-beneath-red-oaks', 'Establishing Understory Ferns Beneath Mature Red Oaks', 'Claire Alder', '2026-08-04', 'Planting Ecology', '', '', '', 'oak-canopy-sanctuary', '' ],
			[ 'rainwater-bioswales-narrow-lots', 'Rainwater Bioswales for Narrow Toronto Lots', 'Claire Alder', '2026-07-07', 'Stone & Grading', '', '', '', 'rainwater-meadow-court', '' ],
		];
		foreach ( $posts as [ $slug, $title, $author, $date, $cat, $excerpt, $img, $alt, $garden, $body ] ) {
			$cid = term_exists( $cat, 'category' ) ?: wp_insert_term( $cat, 'category' );
			$id  = $this->upsert( 'post', "post:$slug", [ 'post_title' => $title, 'post_name' => $slug, 'post_author' => $authors[ $author ], 'post_date' => "$date 09:00:00", 'post_excerpt' => $excerpt, 'post_content' => $body, 'post_category' => [ (int) ( is_array( $cid ) ? $cid['term_id'] : $cid ) ] ], $garden ? [ 'featured_garden' => $g[ $garden ] ] : [] );
			if ( $img ) {
				set_post_thumbnail( $id, $this->img( $img, $alt ) );
			}
		}
		wp_delete_term( (int) get_option( 'default_category' ), 'category' );

		/* pages ------------------------------------------------------------------- */
		$visit = '/site-visit/';
		$pages = [
			'home' => [ 'Home', 'home',
				af_b( 'hero', [ 'size' => 'home', 'heading' => 'We design gardens for difficult Ontario sites.', 'intro' => 'Alderframe is a residential landscape architecture studio in Toronto. Our gardens sit on ravine slopes, under mature trees and on narrow city lots, where grading and drainage decide what can be built.', 'imageId' => $this->img( 'hero-seasonal-spring.jpg', 'Limestone steps and ferns beneath flowering trees in a woodland garden' ), 'ctaLabel' => 'Request a site visit', 'ctaUrl' => $visit, 'secondaryLabel' => 'See completed gardens', 'secondaryUrl' => '/gardens/' ] )
				. af_b( 'garden-grid', [ 'heading' => 'Completed gardens', 'linkLabel' => 'All gardens', 'linkUrl' => '/gardens/', 'slugs' => 'hillside-terrace, laneway-courtyard, oak-canopy-sanctuary, windward-bluff-garden', 'count' => 4, 'layout' => 'case' ] )
				. af_b( 'process-timeline', [ 'heading' => 'How a project runs', 'intro' => 'Frozen ground can’t be graded or planted, so a garden meant for a spring build is designed between November and March.', 'items' => [
					[ 'when' => 'Site walk', 'what' => 'CA$650, fixed', 'text' => 'Ninety minutes on site with you. We check grading, soil, drainage and sun, then send a written summary and a construction budget range.' ],
					[ 'when' => 'Design', 'what' => 'From CA$3,200 · 4–12 weeks', 'text' => 'Survey, concept and grading, then stone, lighting and planting details. The service you choose sets the scope.' ],
					[ 'when' => 'Tender', 'what' => 'Included in full masterplans', 'text' => 'Construction details and a tender set, so contractors quote against the same drawings.' ],
					[ 'when' => 'Build and aftercare', 'what' => 'CA$165 an hour', 'text' => 'Site visits at key stages, plants tagged at the nursery, and a seasonal pruning guide once planting is done.' ],
				] ] )
				. af_b( 'service-cards', [ 'heading' => 'Services and fees', 'linkLabel' => 'Full fee schedule', 'linkUrl' => '/design-fees/' ] )
				. af_b( 'visit-cta' ) ],
			'studio' => [ 'Studio', 'studio',
				af_b( 'hero', [ 'heading' => 'We work out the grading before we draw the planting.', 'intro' => 'Alderframe is a residential landscape architecture studio on Davenport Road in Toronto, founded in 2016. We design gardens across Toronto and the Golden Horseshoe and stay involved through tender, nursery tagging and the first seasons after planting.', 'imageId' => $this->img( 'studio-atelier-interior.jpg', 'Studio drafting tables with planting plans, stone samples and daylight' ) ] )
				. af_prose( af_h2( 'How we work' )
					. af_p( '<strong>We start with the ground.</strong> Before any concept drawing, we map the grading, how water moves through the clay, the root zones of trees worth keeping, and where the sun falls through the year.' )
					. af_p( '<strong>We publish our fees.</strong> Design fees are fixed in Canadian dollars before work starts, and design takes 4 to 12 weeks depending on the service.' )
					. af_p( '<strong>We plant for Ontario winters.</strong> Planting plans use Ontario-hardy native and adapted species, chosen to keep their structure through all four seasons.' ) )
				. af_b( 'designer-directory', [ 'heading' => 'The team' ] )
				. af_b( 'garden-grid', [ 'heading' => 'Selected gardens', 'linkLabel' => 'All gardens', 'linkUrl' => '/gardens/', 'count' => 3 ] )
				. af_prose( af_h2( 'Photography and privacy' ) . af_p( 'We name private gardens by neighbourhood, never by address. We publish photographs and owner comments only with the owners’ consent, and take them down on request.' ) )
				. af_b( 'visit-cta' ) ],
			'design-services' => [ 'Design services', 'services-hub',
				af_b( 'hero', [ 'heading' => 'Services', 'intro' => 'Four ways to work with us, from new planting in an existing garden to a full masterplan. Fees are fixed in Canadian dollars before any work starts. If you’re not sure which fits, start with a site walk.', 'secondaryLabel' => 'See the fee schedule', 'secondaryUrl' => '/design-fees/' ] )
				. af_b( 'service-cards' )
				. af_b( 'service-compare', [ 'heading' => 'What each service includes' ] )
				. af_b( 'faq-list', [ 'stage' => 'design', 'heading' => 'Questions about design' ] )
				. af_b( 'visit-cta' ) ],
			'design-fees' => [ 'Design fees', 'fees',
				af_b( 'hero', [ 'heading' => 'Design fees', 'intro' => 'All fees are in Canadian dollars. A “from” price is a starting point: we confirm the fee in writing after the site walk, and work starts once you’ve signed the brief.', 'secondaryLabel' => 'Compare services', 'secondaryUrl' => '/design-services/' ] )
				. af_b( 'fee-table' )
				. af_b( 'faq-list', [ 'stage' => 'fees', 'heading' => 'Questions about fees' ] )
				. af_b( 'visit-cta' ), [ 'fee_packages' => [
					'Site walk and feasibility | A 90-minute visit to review grading, soil and sun, followed by a written memo with a budget range. | 650 | fixed | 90-minute site walk;Soil and sun check;Budget range;Written summary',
					'Courtyard and city garden | Layout, paving details and a planting plan for city lots under 200 m². | 4400 | from | Measured survey;2D layout;Stone schedule;Planting plan;Lighting plan;Tender set',
					'Full property masterplan | Grading, water management, stonework and planting for the whole property. | 6800 | from | Topographic audit;Masterplan;Grading plan;Joinery details;Planting plan;Soil specification;Contractor tender;Nursery tagging',
					'Construction and planting advisory | Inspections at key construction stages, nursery selection and seasonal aftercare visits. | 165 | hourly | Site observation;Nursery tagging;Soil testing;Seasonal pruning guide',
				] ] ],
			'journal' => [ 'Studio journal', '', '' ],
			'contact' => [ 'Contact', 'contact',
				af_b( 'hero', [ 'heading' => 'Contact the studio', 'intro' => 'For a new garden, the site-visit form is the quickest route. For anything else, call or write.' ] )
				. af_b( 'contact-block', [ 'imageId' => $this->img( 'contact-studio-map.jpg', 'Map showing the studio on Davenport Road, Toronto' ) ] ) ],
			'site-visit' => [ 'Site visit', 'site-visit',
				af_b( 'hero', [ 'heading' => 'Request a site visit', 'intro' => 'Tell us where the garden is and what you’d like to change. We reply within two business days to say whether we can help and to find a time.' ] )
				. af_b( 'site-visit-form' )
				. af_b( 'faq-list', [ 'stage' => 'before-visit', 'heading' => 'Before the visit' ] ) ],
			'garden-brief' => [ 'Garden brief', 'guide',
				af_b( 'hero', [ 'heading' => 'The Residential Garden Brief', 'intro' => 'A short guide to what to gather and think about before we first walk your property.', 'imageId' => $this->img( 'garden-brief-cover.jpg', 'Cover of the Alderframe residential garden brief' ) ] )
				. af_prose( af_h2( 'What the brief covers' ) . "<!-- wp:list {\"className\":\"af-checklist\"} -->\n<ul class=\"wp-block-list af-checklist\"><!-- wp:list-item --><li><strong>Your survey and grading notes</strong> Lot lines, easements and any ravine setbacks.</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Sun, trees and drainage</strong> Where light falls through the year, and where water pools after rain.</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>How you’ll use the garden</strong> Eating outside, children, privacy, and how much upkeep you want.</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Phasing and budget</strong> Typical Ontario costs for stonework and planting, and how to split a project over several years.</li><!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n" )
				. af_b( 'guide-download' )
				. af_b( 'visit-cta' ) ],
			'spring-design' => [ 'Spring design', 'campaign',
				af_b( 'hero', [ 'heading' => 'Building in spring 2027? Start the design this winter.', 'intro' => 'Frozen ground can’t be graded or planted, so spring gardens are designed over winter. To build in April or May, we need to walk the site in November or December.', 'imageId' => $this->img( 'hero-seasonal-spring.jpg', 'Limestone steps and ferns beneath flowering trees in a woodland garden' ), 'ctaLabel' => 'Book a winter site visit', 'ctaUrl' => $visit ] )
				. af_b( 'garden-grid', [ 'slugs' => 'laneway-courtyard, rainwater-meadow-court', 'count' => 2 ] )
				. af_b( 'process-timeline', [ 'heading' => 'From survey to build', 'items' => [ [ 'when' => 'Nov–Dec', 'what' => 'Survey' ], [ 'when' => 'Jan–Feb', 'what' => 'Design' ], [ 'when' => 'Mar', 'what' => 'Tender' ], [ 'when' => 'Apr–May', 'what' => 'Build' ] ] ] )
				. af_b( 'owner-quote', [ 'attribution' => 'Patel' ] )
				. af_b( 'visit-cta', [ 'heading' => 'Book a site walk before the ground freezes', 'label' => 'Book a winter site visit' ] ) ],
			'privacy' => [ 'Privacy Policy', 'legal',
				af_b( 'hero', [ 'heading' => 'Privacy Policy' ] ) . af_b( 'policy-meta' )
				. af_prose( af_h2( 'Site-visit intake data' ) . af_p( 'Collected solely to qualify property postcode, service scope, and budget.' ) . af_h2( 'CRM and mail routing' ) . af_p( 'Stored in Pipedrive by submission ID; never sold or shared for advertising.' ) . af_h2( 'Residential photography' ) . af_p( 'Identified publicly only by approved locality; withdrawn immediately upon request.' ) ), [ 'effective_date' => 'November 1, 2026' ] ],
			'terms' => [ 'Website Terms', 'legal',
				af_b( 'hero', [ 'heading' => 'Website Terms' ] ) . af_b( 'policy-meta' )
				. af_prose( af_p( 'Published fees are CAD starting guides; formal engagement requires a signed brief.' ) ), [ 'effective_date' => 'November 1, 2026' ] ],
			'cookies' => [ 'Cookies & Consent Preferences', 'legal',
				af_b( 'hero', [ 'heading' => 'Cookies and consent preferences' ] ) . af_b( 'policy-meta' )
				. af_prose( "<!-- wp:table {\"className\":\"af-table\"} -->\n<figure class=\"wp-block-table af-table\"><table><thead><tr><th>Category</th><th>Purpose</th><th>Status</th></tr></thead><tbody><tr><td>Strictly necessary</td><td>Session and form security</td><td>Always active</td></tr><tr><td>Analytics (GA4 / GTM)</td><td>Google Consent Mode v2</td><td>Default denied</td></tr><tr><td>Marketing</td><td>Not used on Alderframe</td><td>Disabled</td></tr></tbody></table></figure>\n<!-- /wp:table -->\n" . "<!-- wp:html -->\n<p><button type=\"button\" class=\"af-btn af-btn--moss\" data-af-consent>Change consent preferences</button></p>\n<!-- /wp:html -->\n" ), [ 'effective_date' => 'November 1, 2026' ] ],
		];
		$ids = [];
		foreach ( $pages as $slug => $def ) {
			[ $title, $kind, $content ] = $def;
			$meta                       = ( $def[3] ?? [] ) + [ 'page_kind' => $kind ];
			$ids[ $slug ]               = $this->upsert( 'page', "page:$slug", [ 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ], $meta );
		}
		wp_update_post( [ 'ID' => $ids['spring-design'], 'page_template' => 'page-campaign' ] );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
		update_option( 'page_for_posts', $ids['journal'] );
		update_option( 'wp_page_for_privacy_policy', $ids['privacy'] );
		if ( $sample = get_page_by_path( 'sample-page' ) ) {
			wp_delete_post( $sample->ID, true );
		}
		flush_rewrite_rules();
		WP_CLI::success( sprintf( 'Seeded %d services, %d gardens, %d designers, %d pages, %d posts.', count( $svc ), count( $g ), count( $people ), count( $ids ), count( $posts ) ) );
	}
}

WP_CLI::add_command( 'alderframe seed', fn() => ( new AF_Seed() )->run() );
