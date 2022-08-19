<?php

declare( strict_types=1 );

namespace Blockify\Theme;

use function add_action;
use function add_post_type_support;
use function add_theme_support;
use function array_keys;
use function basename;
use function file_get_contents;
use function get_option;
use function get_post;
use function gettype;
use function glob;
use function preg_replace;
use function register_rest_field;
use function remove_post_type_support;
use function str_replace;
use function tgmpa;
use function update_option;
use function wp_send_json;
use function wp_send_json_error;
use function wp_update_post;
use WP_REST_Request;
use WP_REST_Server;

add_action( 'after_setup_theme', NS . 'theme_supports' );
/**
 * Handles theme supports.
 *
 * @since 0.0.2
 *
 * @return void
 */
function theme_supports(): void {
	$theme_supports = get_sub_config( 'themeSupports' );
	$add            = $theme_supports['add'] ?? [];
	$remove         = $theme_supports['remove'] ?? [];

	foreach ( $add as $feature => $args ) {
		add_theme_support( $feature, $args );
	}

	foreach ( $remove as $feature ) {
		remove_theme_support( $feature );
	}
}

add_action( 'after_setup_theme', NS . 'add_post_type_supports' );
/**
 * Handles post type supports.
 *
 * @since 0.0.2
 *
 * @return void
 */
function add_post_type_supports(): void {
	$post_supports = get_sub_config( 'postTypeSupports' );
	$add           = $post_supports['add'] ?? [];
	$remove        = $post_supports['remove'] ?? [];

	foreach ( $add as $post_type => $features ) {
		foreach ( $features as $feature ) {
			add_post_type_support( $post_type, $feature );
		}
	}

	foreach ( $remove as $post_type => $features ) {
		foreach ( $features as $feature ) {
			remove_post_type_support( $post_type, $feature );
		}
	}
}

add_action( 'after_setup_theme', NS . 'add_recommended_plugins' );
/**
 * Adds recommended plugins to TGMPA from theme config.
 *
 * @since 0.0.15
 *
 * @return void
 */
function add_recommended_plugins(): void {
	tgmpa( get_sub_config( 'recommendedPlugins' ) ?? [], [
		'is_automatic' => true,
	] );
}

add_action( 'init', NS . 'register_page_title_rest_field' );
/**
 * Registers page title rest field.
 *
 * @since 0.0.2
 *
 * @return void
 */
function register_page_title_rest_field(): void {
	register_rest_field(
		'blockify-page-title',
		'title',
		[
			'get_callback'    => function ( array $params ): string {
				$post_id = $params['id'];
				$post    = get_post( $post_id );

				return $post->post_title;
			},
			'update_callback' => function ( $value, $object ): void {
				wp_update_post(
					[
						'ID'         => $object->ID,
						'post_title' => $value,
					]
				);
			},
		]
	);
}

add_action( 'wp_ajax_blockify_toggle_dark_mode', NS . 'toggle_dark_mode' );
/**
 * Handles export pattern AJAX request.
 *
 * @since 0.0.1
 *
 * @return void
 */
function toggle_dark_mode(): void {
	if ( ! wp_verify_nonce( $_POST['nonce'], 'blockify' ) ) {
		wp_send_json_error( __( 'Could not verify nonce.', 'blockify' ) );

		die;
	}

	$options = get_option( 'blockify', [] ) ?? [];

	$dark_mode = true;

	if ( isset( $_POST['darkMode'] ) && $_POST['darkMode'] === 'false' ) {
		$dark_mode = false;
	}

	$options['darkMode'] = $dark_mode;

	update_option( 'blockify', $options );

	wp_send_json_success( __( 'Set dark mode to ', 'blockify' ) . $options['darkMode'] );
	die;
}

add_action( 'rest_api_init', NS . 'register_icons_rest_route' );
/**
 * Fetches icon data from endpoint.
 *
 * @since 0.0.1
 *
 * @return void
 */
function register_icons_rest_route(): void {
	register_rest_route( SLUG . '/v1', '/icons/', [
		'permission_callback' => '__return_true',
		'methods'             => WP_REST_Server::READABLE,
		[
			'args' => [
				'sets' => [
					'required' => false,
					'type'     => 'string',
				],
				'set'  => [
					'required' => false,
					'type'     => 'string',
				],
			],
		],
		'callback'            => function ( $request ) {
			$icon_data = get_icon_data();

			/**
			 * @var WP_REST_Request $request
			 */
			if ( $request->get_param( 'set' ) ) {
				$set = $request->get_param( 'set' );

				if ( $request->get_param( 'icon' ) ) {
					return $icon_data[ $set ][ $request->get_param( 'icon' ) ];
				}

				return $icon_data[ $set ];
			}

			if ( $request->get_param( 'sets' ) ) {
				return array_keys( $icon_data );
			}

			return $icon_data;
		},
	] );
}

/**
 * Rest endpoint callback.
 *
 * @since 0.0.1
 *
 * @return array
 */
function get_icon_data(): array {
	$icon_data = [];
	$icon_sets = get_config( 'icons' );

	foreach ( $icon_sets as $icon_set => $set_dir ) {
		$icons = glob( $set_dir . '/*.svg' );

		foreach ( $icons as $icon ) {
			$name = basename( $icon, '.svg' );
			$icon = file_get_contents( $icon );

			if ( $icon_set === 'wordpress' ) {
				$icon = str_replace(
					[ '<svg ', 'fill="none"' ],
					[ '<svg fill="currentColor" ', '' ],
					$icon
				);
			}

			// Remove comments.
			$icon = preg_replace( '/<!--(.|\s)*?-->/', '', $icon );

			$icon_data[ $icon_set ][ $name ] = $icon;
		}
	}

	return $icon_data;
}