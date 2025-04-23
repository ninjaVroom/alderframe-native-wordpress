<?php
/**
 * Site-wide settings, read through one resolver.
 * one options screen gated by manage_options; split into the plan's six capability-scoped screens when roles land.
 */

defined( 'ABSPATH' ) || exit;

function af_option_defaults(): array {
	return [
		'company'           => 'Alderframe Studio Inc.',
		'tagline'           => 'Residential landscape architecture for ravine slopes, mature shade, and urban courtyards across Toronto and the Golden Horseshoe.',
		'address_1'         => '148 Davenport Rd, Suite 200',
		'address_2'         => 'Toronto, ON M5R 1J2',
		'phone'             => '416-555-0194',
		'email'             => 'studio@alderframe.ca',
		'hours'             => 'Monday–Friday, 09:00–17:00',
		'timezone'          => 'America/Toronto',
		'postcode_prefixes' => 'M, L4, L5, L6, L7, L8, L9',
		'booking_open'      => true,
		'closed_message'    => 'The studio is not taking new site-visit requests this season.',
		'out_of_area'       => 'That postcode is outside the regions we currently visit: Toronto (M) and the Golden Horseshoe (L4–L9).',
		'cta_label'         => 'Request a site visit',
		'announcement'      => '',
		'announce_start'    => '',
		'announce_end'      => '',
	];
}

/** af_option( 'phone' ) → typed value with default. */
function af_option( string $key ) {
	static $opts = null;
	$opts ??= wp_parse_args( (array) get_option( 'af_settings', [] ), af_option_defaults() );
	return $opts[ $key ] ?? null;
}

function af_tel( string $phone ): string {
	return 'tel:+1' . preg_replace( '/\D+/', '', $phone );
}

/** Announcement only inside its window (site timezone). */
function af_active_announcement(): string {
	$text = trim( (string) af_option( 'announcement' ) );
	if ( ! $text ) {
		return '';
	}
	$now   = current_datetime()->getTimestamp();
	$start = af_option( 'announce_start' ) ? strtotime( af_option( 'announce_start' ) . ' ' . wp_timezone_string() ) : 0;
	$end   = af_option( 'announce_end' ) ? strtotime( af_option( 'announce_end' ) . ' ' . wp_timezone_string() ) : PHP_INT_MAX;
	return ( $now >= $start && $now < $end ) ? $text : '';
}

add_action( 'admin_menu', function () {
	add_menu_page( 'Site Settings', 'Site Settings', 'manage_options', 'af-settings', 'af_settings_page', 'dashicons-admin-site-alt3', 3 );
} );

add_action( 'admin_init', function () {
	register_setting( 'af_settings', 'af_settings', [
		'type'              => 'array',
		'sanitize_callback' => function ( $in ) {
			$out = [];
			foreach ( af_option_defaults() as $k => $def ) {
				$v         = $in[ $k ] ?? '';
				$out[ $k ] = is_bool( $def ) ? ! empty( $v ) : ( 'email' === $k ? sanitize_email( $v ) : sanitize_text_field( $v ) );
			}
			return $out;
		},
	] );
} );

function af_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="wrap"><h1>Site Settings</h1><form method="post" action="options.php">';
	settings_fields( 'af_settings' );
	echo '<table class="form-table" role="presentation">';
	foreach ( af_option_defaults() as $k => $def ) {
		$id = 'af_opt_' . $k;
		echo '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( ucfirst( str_replace( '_', ' ', $k ) ) ) . '</label></th><td>';
		if ( is_bool( $def ) ) {
			printf( '<input type="hidden" name="af_settings[%1$s]" value="0"><input type="checkbox" id="%2$s" name="af_settings[%1$s]" value="1"%3$s>', esc_attr( $k ), esc_attr( $id ), checked( af_option( $k ), true, false ) );
		} else {
			$type = str_starts_with( $k, 'announce_' ) ? 'datetime-local' : 'text';
			printf( '<input type="%s" class="regular-text" id="%s" name="af_settings[%s]" value="%s">', $type, esc_attr( $id ), esc_attr( $k ), esc_attr( (string) af_option( $k ) ) ); // phpcs:ignore
		}
		echo '</td></tr>';
	}
	echo '</table>';
	submit_button();
	echo '</form></div>';
}
