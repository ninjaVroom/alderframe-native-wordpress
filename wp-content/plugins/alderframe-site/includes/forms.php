<?php
/**
 * Site-visit intake. Native form → server validation → private af_request record (durable) → outbox status.
 * CRM delivery is an outbox flag ('pending'); the Pipedrive worker plugs in at af_request_created
 * once an API token and sandbox exist. Mail goes through wp_mail (configure SMTP/API at the host).
 */

defined( 'ABSPATH' ) || exit;

const AF_BUDGETS = [
	'under-50k'  => 'Under CA$50,000',
	'50-100k'    => 'CA$50,000 to CA$100,000',
	'100-250k'   => 'CA$100,000 to CA$250,000',
	'over-250k'  => 'Over CA$250,000',
];

function af_postcode_in_area( string $pc ): bool {
	foreach ( array_filter( array_map( 'trim', explode( ',', strtoupper( (string) af_option( 'postcode_prefixes' ) ) ) ) ) as $prefix ) {
		if ( str_starts_with( $pc, $prefix ) ) {
			return true;
		}
	}
	return false;
}

/** Validate a submission; returns [clean values, errors keyed by field]. Pure, so it can be tested. */
function af_validate_visit( array $in ): array {
	$v = [
		'postcode' => strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $in['postcode'] ?? '' ) ) ),
		'service'  => absint( $in['service'] ?? 0 ),
		'budget'   => sanitize_key( $in['budget'] ?? '' ),
		'brief'    => sanitize_textarea_field( $in['brief'] ?? '' ),
		'name'     => sanitize_text_field( $in['name'] ?? '' ),
		'email'    => sanitize_email( $in['email'] ?? '' ),
		'phone'    => sanitize_text_field( $in['phone'] ?? '' ),
		'consent'  => ! empty( $in['consent'] ),
	];
	$e = [];
	if ( ! preg_match( '/^[A-Z]\d[A-Z]\d[A-Z]\d$/', $v['postcode'] ) ) {
		$e['postcode'] = 'Enter a Canadian postcode, for example M5R 1J2.';
	} elseif ( ! af_postcode_in_area( $v['postcode'] ) ) {
		$e['postcode'] = (string) af_option( 'out_of_area' );
	}
	if ( ! $v['service'] || ! af_refs( [ $v['service'] ], 'af_service' ) ) {
		$e['service'] = 'Choose the design service closest to your project.';
	}
	if ( ! isset( AF_BUDGETS[ $v['budget'] ] ) ) {
		$e['budget'] = 'Choose an estimated construction budget.';
	}
	if ( mb_strlen( $v['brief'] ) < 20 ) {
		$e['brief'] = 'Describe the site and what you hope for in at least a sentence.';
	} elseif ( mb_strlen( $v['brief'] ) > 2000 ) {
		$e['brief'] = 'Keep the brief under 2,000 characters.';
	}
	if ( '' === $v['name'] ) {
		$e['name'] = 'Enter your name.';
	}
	if ( ! is_email( $v['email'] ) ) {
		$e['email'] = 'Enter an email address, for example name@example.com.';
	}
	if ( strlen( preg_replace( '/\D+/', '', $v['phone'] ) ) < 10 ) {
		$e['phone'] = 'Enter a phone number with area code.';
	}
	if ( ! $v['consent'] ) {
		$e['consent'] = 'Consent is needed before we can review your enquiry.';
	}
	return [ $v, $e ];
}

add_action( 'admin_post_nopriv_af_site_visit', 'af_handle_site_visit' );
add_action( 'admin_post_af_site_visit', 'af_handle_site_visit' );

function af_handle_site_visit(): void {
	$back = home_url( '/site-visit/' );
	if ( ! isset( $_POST['af_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['af_nonce'] ), 'af_site_visit' ) ) {
		wp_safe_redirect( add_query_arg( 'request', 'expired', $back ) . '#request' );
		exit;
	}
	if ( ! empty( $_POST['website'] ) ) { // honeypot
		wp_safe_redirect( add_query_arg( 'request', 'received', $back ) . '#request' );
		exit;
	}
	if ( ! af_option( 'booking_open' ) ) {
		wp_safe_redirect( add_query_arg( 'request', 'closed', $back ) . '#request' );
		exit;
	}
	[ $v, $errors ] = af_validate_visit( wp_unslash( $_POST ) ); // phpcs:ignore -- sanitised inside
	if ( $errors ) {
		$token = wp_generate_password( 12, false );
		set_transient( 'af_form_' . $token, [ 'v' => $v, 'e' => $errors ], 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'form', $token, $back ) . '#request' );
		exit;
	}
	// Idempotency: the submission ID is the nonce-bound token posted with the form.
	$submission = sanitize_key( $_POST['submission_id'] ?? '' );
	$existing   = $submission ? get_posts( [ 'post_type' => 'af_request', 'post_status' => 'private', 'meta_key' => 'submission_id', 'meta_value' => $submission, 'fields' => 'ids' ] ) : []; // phpcs:ignore
	if ( ! $existing ) {
		$id = wp_insert_post( [
			'post_type'   => 'af_request',
			'post_status' => 'private',
			'post_title'  => sprintf( '%s, %s', $v['name'], $v['postcode'] ),
		] );
		foreach ( $v + [ 'submission_id' => $submission, 'crm_status' => 'pending' ] as $k => $val ) {
			update_post_meta( $id, $k, $val );
		}
		do_action( 'af_request_created', $id, $v );
		wp_mail(
			get_option( 'admin_email' ),
			'New site-visit request: ' . $v['postcode'],
			sprintf( "Service: %s\nBudget: %s\nPostcode: %s\n\n%s\n\nReview: %s", get_the_title( $v['service'] ), AF_BUDGETS[ $v['budget'] ], $v['postcode'], $v['brief'], admin_url( 'post.php?post=' . $id . '&action=edit' ) )
		);
	}
	wp_safe_redirect( add_query_arg( 'request', 'received', $back ) . '#request' );
	exit;
}

add_action( 'add_meta_boxes_af_request', function () {
	add_meta_box( 'af_request', 'Request', function ( $post ) {
		echo '<table class="form-table"><tbody>';
		foreach ( [ 'postcode', 'service', 'budget', 'brief', 'name', 'email', 'phone', 'submission_id', 'crm_status' ] as $k ) {
			$val = get_post_meta( $post->ID, $k, true );
			if ( 'service' === $k ) {
				$val = get_the_title( (int) $val );
			}
			if ( 'budget' === $k ) {
				$val = AF_BUDGETS[ $val ] ?? $val;
			}
			echo '<tr><th>' . esc_html( ucfirst( str_replace( '_', ' ', $k ) ) ) . '</th><td>' . nl2br( esc_html( (string) $val ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}, 'af_request', 'normal', 'high' );
} );

function af_site_visit_form_html(): string {
	$state = sanitize_key( $_GET['request'] ?? '' ); // phpcs:ignore
	if ( 'received' === $state ) {
		return '<div class="af-form-done" role="status" tabindex="-1" data-af-focus><h2 class="af-h2" id="af-form-title">Request received</h2><p class="af-lede">Thank you. We read every request and will reply within two business days to say whether we can help and to find a time.</p>' . af_link( 'See completed gardens', home_url( '/gardens/' ) ) . '</div>';
	}
	if ( ! af_option( 'booking_open' ) || 'closed' === $state ) {
		return '<div class="af-form-done" role="status"><h2 class="af-h2" id="af-form-title">Requests are paused</h2><p class="af-lede">' . esc_html( af_option( 'closed_message' ) ) . '</p></div>';
	}
	$saved  = get_transient( 'af_form_' . sanitize_key( $_GET['form'] ?? '' ) ) ?: [ 'v' => [], 'e' => [] ]; // phpcs:ignore
	$v      = $saved['v'];
	$e      = $saved['e'];
	$pre    = absint( $v['service'] ?? ( $_GET['service'] ?? 0 ) ); // phpcs:ignore
	$err    = fn( $k ) => isset( $e[ $k ] ) ? '<p class="af-field__error" id="err-' . $k . '">' . esc_html( $e[ $k ] ) . '</p>' : '';
	$aria   = fn( $k, $hint = '' ) => ' aria-describedby="' . trim( $hint . ( isset( $e[ $k ] ) ? ' err-' . $k : '' ) ) . '"' . ( isset( $e[ $k ] ) ? ' aria-invalid="true"' : '' );
	$val    = fn( $k ) => esc_attr( (string) ( $v[ $k ] ?? '' ) );

	$summary = '';
	if ( $e ) {
		$summary = '<div class="af-form__summary" role="alert" tabindex="-1" data-af-focus><h3>Please check ' . count( $e ) . ' ' . _n( 'field', 'fields', count( $e ), 'alderframe' ) . '</h3><ul>';
		foreach ( $e as $k => $msg ) {
			$summary .= '<li><a href="#f-' . esc_attr( $k ) . '">' . esc_html( $msg ) . '</a></li>';
		}
		$summary .= '</ul></div>';
	} elseif ( 'expired' === $state ) {
		$summary = '<div class="af-form__summary" role="alert"><p>The form expired. Please submit it again.</p></div>';
	}

	$services = '<option value="">Choose a service</option>';
	foreach ( af_services() as $s ) {
		$services .= sprintf( '<option value="%d"%s>%s</option>', $s->ID, selected( $pre, $s->ID, false ), esc_html( get_the_title( $s ) ) );
	}
	$budgets = '<option value="">Choose a range</option>';
	foreach ( AF_BUDGETS as $k => $label ) {
		$budgets .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $v['budget'] ?? '', $k, false ), esc_html( $label ) );
	}

	return '<form class="af-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>
	<h2 class="af-h2" id="af-form-title">Your site and project</h2>
	' . $summary . '
	<input type="hidden" name="action" value="af_site_visit">
	<input type="hidden" name="submission_id" value="' . esc_attr( wp_generate_uuid4() ) . '">
	' . wp_nonce_field( 'af_site_visit', 'af_nonce', false, false ) . '
	<p class="af-hp" aria-hidden="true"><label>Leave empty <input name="website" tabindex="-1" autocomplete="off"></label></p>
	<p class="af-form__note">All fields are required.</p>
	<div class="af-form__grid">
		<div class="af-field"><label for="f-postcode">Property postcode</label><p class="af-field__hint" id="h-postcode">We visit Toronto (M) and the Golden Horseshoe (L4 to L9).</p><input id="f-postcode" name="postcode" autocomplete="postal-code" inputmode="text" maxlength="7" value="' . $val( 'postcode' ) . '"' . $aria( 'postcode', 'h-postcode' ) . ' required>' . $err( 'postcode' ) . '</div>
		<div class="af-field"><label for="f-service">Design service</label><select id="f-service" name="service"' . $aria( 'service' ) . ' required>' . $services . '</select>' . $err( 'service' ) . '</div>
		<div class="af-field af-field--wide"><label for="f-budget">Estimated construction budget (CAD)</label><select id="f-budget" name="budget"' . $aria( 'budget' ) . ' required>' . $budgets . '</select>' . $err( 'budget' ) . '</div>
		<div class="af-field af-field--wide"><label for="f-brief">Project brief and site conditions</label><textarea id="f-brief" name="brief" rows="5" maxlength="2000"' . $aria( 'brief' ) . ' required>' . esc_textarea( $v['brief'] ?? '' ) . '</textarea>' . $err( 'brief' ) . '</div>
		<div class="af-field"><label for="f-name">Your name</label><input id="f-name" name="name" autocomplete="name" value="' . $val( 'name' ) . '"' . $aria( 'name' ) . ' required>' . $err( 'name' ) . '</div>
		<div class="af-field"><label for="f-email">Email</label><input id="f-email" type="email" name="email" autocomplete="email" value="' . $val( 'email' ) . '"' . $aria( 'email' ) . ' required>' . $err( 'email' ) . '</div>
		<div class="af-field"><label for="f-phone">Phone</label><input id="f-phone" type="tel" name="phone" autocomplete="tel" value="' . $val( 'phone' ) . '"' . $aria( 'phone' ) . ' required>' . $err( 'phone' ) . '</div>
		<div class="af-field af-field--wide af-field--check"><input id="f-consent" type="checkbox" name="consent" value="1"' . ( ! empty( $v['consent'] ) ? ' checked' : '' ) . $aria( 'consent' ) . ' required><label for="f-consent">I consent to Alderframe Studio reviewing my enquiry under the <a href="' . esc_url( home_url( '/privacy/' ) ) . '">Privacy Policy</a>.</label>' . $err( 'consent' ) . '</div>
	</div>
	<div class="af-form__submit"><button class="af-btn af-btn--action" type="submit">Send request</button><p>We reply within two business days.</p></div>
</form>';
}
