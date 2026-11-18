<?php
/**
 * Standalone test bootstrap: minimal WordPress/WooCommerce stubs.
 *
 * Run with:  php tests/run.php
 *
 * @package SimorghSaya\BeltGuide
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['ssbg_filters']    = array();
$GLOBALS['ssbg_actions']    = array();
$GLOBALS['ssbg_options']    = array();
$GLOBALS['ssbg_screen']     = null;
$GLOBALS['ssbg_enqueued']   = array();
$GLOBALS['ssbg_product']    = new WC_Product();
$GLOBALS['ssbg_product']->meta['_ssbg_enabled'] = 'yes';
$GLOBALS['ssbg_product']->id = 42;

class WC_Product {
	public $id   = 0;
	public $meta = array();

	public function get_id() {
		return $this->id;
	}

	public function get_meta( $key ) {
		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}

	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}
}

/* ------------------------------------------------------------------ hooks */

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['ssbg_actions'][ $tag ][ $priority ][] = $callback;
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['ssbg_filters'][ $tag ][ $priority ][] = $callback;
}

function apply_filters( $tag, $value ) {
	if ( empty( $GLOBALS['ssbg_filters'][ $tag ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['ssbg_filters'][ $tag ] as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$value = call_user_func( $callback, $value );
		}
	}

	return $value;
}

/** Test helper: run every filter registered for a tag. */
function ssbg_filter( $tag, $value ) {
	return apply_filters( $tag, $value );
}

function ssbg_clear_filters() {
	$GLOBALS['ssbg_filters'] = array();
}

/* --------------------------------------------------------------- generic */

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return (string) $url;
}

function esc_html__( $text, $domain = '' ) {
	unset( $domain );
	return esc_html( $text );
}

function esc_attr__( $text, $domain = '' ) {
	unset( $domain );
	return esc_attr( $text );
}

function esc_html_e( $text, $domain = '' ) {
	echo esc_html__( $text, $domain );
}

function esc_attr_e( $text, $domain = '' ) {
	echo esc_attr__( $text, $domain );
}

function sanitize_text_field( $value ) {
	return is_string( $value ) ? trim( $value ) : $value;
}

function wp_unslash( $value ) {
	return $value;
}

function wp_verify_nonce( $nonce, $action = -1 ) {
	unset( $nonce, $action );
	return true;
}

function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	unset( $action, $referer );
	$html = '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce" />';
	if ( $display ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	return $html;
}

function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) {
	unset( $action, $name );
	return $url;
}

function check_admin_referer( $action = -1, $name = '_wpnonce' ) {
	unset( $action, $name );
	return true;
}

function current_user_can( $capability ) {
	unset( $capability );
	return true;
}

function wp_die( $message = '', $title = '', $args = array() ) {
	unset( $message, $title, $args );
	return true;
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['ssbg_options'] ) ? $GLOBALS['ssbg_options'][ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	$GLOBALS['ssbg_options'][ $name ] = $value;
	return true;
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function wp_get_referer() {
	return false;
}

function wp_safe_redirect( $location, $status = 302 ) {
	unset( $location, $status );
	return true;
}

function get_current_screen() {
	return $GLOBALS['ssbg_screen'];
}

function checked( $checked, $current = true, $display = true ) {
	unset( $current );
	$html = (string) $checked === '1' || $checked ? " checked='checked'" : '';
	if ( $display ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	return $html;
}

function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
	unset( $src, $deps, $ver, $media );
	$GLOBALS['ssbg_enqueued'][] = $handle;
}

function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
	unset( $src, $deps, $ver, $footer );
	$GLOBALS['ssbg_enqueued'][] = $handle;
}

function plugins_url( $path = '', $plugin = '' ) {
	unset( $plugin );
	return 'https://example.test/wp-content/plugins/simorgh-belt-guide/' . ltrim( $path, '/' );
}

function plugin_basename( $file ) {
	return 'simorgh-belt-guide/' . basename( $file );
}

function load_plugin_textdomain( $domain, $deprecated = false, $path = '' ) {
	unset( $domain, $deprecated, $path );
	return true;
}

function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $args = null ) {
	unset( $id, $title, $callback, $screen, $context, $priority, $args );
	return true;
}

function wp_unique_id( $prefix = '' ) {
	static $counter = 0;
	return $prefix . ( ++$counter );
}

function get_file_data( $path, $headers = array(), $context = '' ) {
	unset( $context );
	$out  = array();
	$head = is_readable( $path ) ? (string) file_get_contents( $path, false, null, 0, 8192 ) : '';

	foreach ( $headers as $key => $field ) {
		$out[ $key ] = '';
		if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $field, '/' ) . ':(.*)$/mi', $head, $matches ) ) {
			$out[ $key ] = trim( $matches[1] );
		}
	}

	return $out;
}

/* ------------------------------------------------------- WooCommerce API */

function is_product() {
	return true;
}

function get_queried_object_id() {
	return 42;
}

function wc_get_product( $id = 0 ) {
	unset( $id );
	return $GLOBALS['ssbg_product'];
}

/* ----------------------------------------------------------------- utils */

function ssbg_reset( $enabled = true ) {
	\SimorghSaya\BeltGuide\Guide_State::$rendered = false;
	\SimorghSaya\BeltGuide\Guide_State::$source   = '';
	$GLOBALS['ssbg_product']->meta['_ssbg_enabled'] = $enabled ? 'yes' : 'no';
	unset( $_GET['elementor-preview'] );
}

/** Remove one exact filter callback (used by tests that add temporary filters). */
function ssbg_remove_filter( $tag, $callback ) {
	foreach ( $GLOBALS['ssbg_filters'][ $tag ] ?? array() as $priority => $callbacks ) {
		foreach ( $callbacks as $index => $registered ) {
			if ( $registered === $callback ) {
				unset( $GLOBALS['ssbg_filters'][ $tag ][ $priority ][ $index ] );
			}
		}
	}
}

require_once dirname( __DIR__ ) . '/simorgh-belt-guide.php';