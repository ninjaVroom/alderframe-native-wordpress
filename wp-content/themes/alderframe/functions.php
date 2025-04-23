<?php
/**
 * Alderframe theme: presentation only.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/site.css' );
	remove_theme_support( 'core-block-patterns' );
} );

add_action( 'wp_enqueue_scripts', function () {
	$v = wp_get_theme()->get( 'Version' );
	wp_enqueue_style( 'alderframe', get_theme_file_uri( 'assets/css/site.css' ), [], filemtime( get_theme_file_path( 'assets/css/site.css' ) ) );

	wp_enqueue_script_module( 'alderframe/site', get_theme_file_uri( 'assets/js/site.js' ), [], filemtime( get_theme_file_path( 'assets/js/site.js' ) ) );
} );

// Preload the body face (largest share of text).
add_action( 'wp_head', function () {
	printf( '<link rel="preload" href="%s" as="font" type="font/ttf" crossorigin>' . "\n", esc_url( get_theme_file_uri( 'assets/fonts/Manrope-Variable.ttf' ) ) );
	echo '<script>document.documentElement.classList.add("js")</script>' . "\n";
}, 1 );
