<?php
/**
 * Plugin Name:       راهنمای کمربند سیمرغ‌سایا
 * Description:       تب اختیاری راهنمای سایز و مراقبت کمربند با کنترل مستقل برای هر محصول.
 * Version:           1.0.3
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       simorgh-belt-guide
 * Domain Path:       /languages
 * Update URI:        false
 *
 * @package SimorghSaya\BeltGuide
 */

namespace SimorghSaya\BeltGuide;

defined( 'ABSPATH' ) || exit;

const META_KEY = '_ssbg_enabled';

/**
 * Per-request render state.
 *
 * Both render paths (Elementor tab injection and the classic WooCommerce tab)
 * ask this object before doing any work. Whichever runs first wins, so the
 * guide can never appear twice on the same request.
 */
final class Guide_State {
	/** @var bool Whether the guide has already been written into the page. */
	public static $rendered = false;

	/** @var string 'elementor' | 'wc' | '' — which path rendered it. */
	public static $source = '';
}

/**
 * Plugin version, read once from the file header so the header stays the single source of truth.
 *
 * @return string
 */
function version() {
	static $version = null;

	if ( null === $version ) {
		$version = '1.0.3';
		if ( function_exists( 'get_file_data' ) ) {
			$header = get_file_data( __FILE__, array( 'Version' => 'Version' ), 'plugin' );
			if ( ! empty( $header['Version'] ) ) {
				$version = (string) $header['Version'];
			}
		}
	}

	return $version;
}

/**
 * Title used by both the classic tab and the injected Elementor tab.
 *
 * @return string
 */
function tab_title() {
	$default = 'راهنمای سایز و مراقبت';
	$title   = apply_filters( 'ssbg_tab_title', $default );

	return ( is_string( $title ) && '' !== trim( $title ) ) ? $title : $default;
}

/**
 * `data-id` of the Elementor Atomic Tabs group that receives the guide.
 *
 * The default targets the known product-information group of this site's
 * single-product template. Override it with the `ssbg_elementor_group_id`
 * filter when the template is rebuilt and Elementor assigns a new id.
 *
 * @return string A safe Elementor element id.
 */
function elementor_group_id() {
	$default = '3d04054';
	$id      = apply_filters( 'ssbg_elementor_group_id', $default );

	if ( ! is_string( $id ) || ! preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $id ) ) {
		return $default;
	}

	return $id;
}

/**
 * Whether the guide is switched on for a product.
 *
 * @param mixed $product A WC_Product or null.
 * @return bool
 */
function enabled( $product ) {
	return $product instanceof \WC_Product && 'yes' === $product->get_meta( META_KEY );
}

/**
 * True inside the Elementor editor (iframe preview or editor mode).
 *
 * The content filter also runs while the editor renders its preview iframe.
 * Injecting there would show an un-editable tab, so we opt out.
 *
 * @return bool
 */
function is_elementor_editor() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview flag.
	if ( isset( $_GET['elementor-preview'] ) ) {
		return true;
	}

	if ( class_exists( '\\Elementor\\Plugin' ) ) {
		$elementor = \Elementor\Plugin::$instance;
		if ( $elementor && ! empty( $elementor->editor ) && is_object( $elementor->editor )
			&& method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
			return true;
		}
	}

	return false;
}

/**
 * Register the plugin translations.
 */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'simorgh-belt-guide', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

/**
 * Product edit screen: the on/off switch.
 */
add_action(
	'add_meta_boxes_product',
	static function () {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		add_meta_box(
			'ssbg-settings',
			esc_html__( 'راهنمای کمربند سیمرغ‌سایا', 'simorgh-belt-guide' ),
			__NAMESPACE__ . '\\settings',
			'product',
			'side',
			'default'
		);
	}
);

/**
 * Render the metabox.
 *
 * @param \WP_Post $post Product post.
 */
function settings( $post ) {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
	$enabled = $product && 'yes' === $product->get_meta( META_KEY );

	wp_nonce_field( 'ssbg_save', 'ssbg_nonce' );
	?>
	<div class="ssbg-settings">
		<label class="ssbg-switch">
			<input type="checkbox" role="switch" name="ssbg_enabled" value="yes" <?php checked( $enabled ); ?> aria-describedby="ssbg-help">
			<span class="ssbg-switch-track" aria-hidden="true"></span>
			<span><?php esc_html_e( 'نمایش راهنمای کمربند', 'simorgh-belt-guide' ); ?></span>
		</label>
		<p id="ssbg-help"><?php esc_html_e( 'با فعال‌سازی و ذخیرهٔ محصول، تب «راهنمای سایز و مراقبت» کنار تب‌های محصول نمایش داده می‌شود. محتوا ثابت و شامل جدول سایز، روش اندازه‌گیری و مراقبت است.', 'simorgh-belt-guide' ); ?></p>
		<p class="ssbg-state">
			<span class="ssbg-state-on"><?php esc_html_e( 'فعال', 'simorgh-belt-guide' ); ?></span>
			<span class="ssbg-state-off"><?php esc_html_e( 'غیرفعال', 'simorgh-belt-guide' ); ?></span>
			<?php esc_html_e( '— پس از به‌روزرسانی محصول ذخیره می‌شود.', 'simorgh-belt-guide' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Persist the switch.
 *
 * WooCommerce validates its own product-save nonce before this hook fires;
 * we still validate ours for defense in depth.
 *
 * @param \WC_Product $product Product being saved.
 */
add_action(
	'woocommerce_admin_process_product_object',
	static function ( $product ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified below.
		if ( ! isset( $_POST['ssbg_nonce'] ) || ! is_string( $_POST['ssbg_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ssbg_nonce'] ) ), 'ssbg_save' ) ||
			! current_user_can( 'edit_post', $product->get_id() ) ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$enabled = isset( $_POST['ssbg_enabled'] ) && is_string( $_POST['ssbg_enabled'] )
			&& 'yes' === wp_unslash( $_POST['ssbg_enabled'] );

		$product->update_meta_data( META_KEY, $enabled ? 'yes' : 'no' );
	}
);

/**
 * Admin switch styling, product edit screen only.
 */
add_action(
	'admin_enqueue_scripts',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && 'product' === $screen->post_type && 'post' === $screen->base ) {
			wp_enqueue_style( 'ssbg-admin', plugins_url( 'assets/admin.css', __FILE__ ), array(), version() );
		}
	}
);

/**
 * Dismiss the "PHP DOM missing" notice.
 */
add_action(
	'admin_post_ssbg_dismiss_notice',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'ssbg_dismiss_notice' );
		update_option( 'ssbg_notice_dom_dismissed', 1, false );

		$redirect = wp_get_referer() ? wp_get_referer() : admin_url();
		wp_safe_redirect( $redirect );
		exit;
	}
);

/**
 * Warn once when the Elementor path cannot work because ext-dom is unavailable.
 *
 * Without DOMDocument the injected tab silently never appears. The notice is
 * dismissible and only shown to administrators on product screens.
 */
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) || class_exists( '\\DOMDocument' ) ) {
			return;
		}

		// Only relevant when Elementor (and therefore the DOM-based path) is present.
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return;
		}

		if ( get_option( 'ssbg_notice_dom_dismissed' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		$dismiss = wp_nonce_url(
			admin_url( 'admin-post.php?action=ssbg_dismiss_notice' ),
			'ssbg_dismiss_notice'
		);
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: plugin name */
					esc_html__( '%s: ماژول «DOM» در PHP این سایت فعال نیست؛ بنابراین تب راهنما در قالب المنتور نمایش داده نمی‌شود. برای فعال‌سازی با مدیر سرور تماس بگیرید.', 'simorgh-belt-guide' ),
					'<strong>' . esc_html__( 'راهنمای کمربند سیمرغ‌سایا', 'simorgh-belt-guide' ) . '</strong>'
				);
				?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( $dismiss ); ?>">
					<?php esc_html_e( 'بستن این پیام', 'simorgh-belt-guide' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
);

/**
 * Add the guide tab to the classic WooCommerce product tabs.
 *
 * Skipped when the Elementor path already rendered the guide this request.
 */
add_filter(
	'woocommerce_product_tabs',
	static function ( $tabs ) {
		if ( Guide_State::$rendered ) {
			return $tabs;
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_queried_object_id() ) : null;
		if ( ! enabled( $product ) ) {
			return $tabs;
		}

		$tabs['ssbg_guide'] = array(
			'title'    => tab_title(),
			'priority' => (int) apply_filters( 'ssbg_tab_priority', 25 ),
			'callback' => __NAMESPACE__ . '\\render',
		);

		return $tabs;
	}
);

/**
 * Frontend assets, only on an enabled single product page.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		if ( ! enabled( wc_get_product( get_queried_object_id() ) ) ) {
			return;
		}

		wp_enqueue_style( 'ssbg-guide', plugins_url( 'assets/guide.css', __FILE__ ), array(), version() );
		wp_enqueue_script( 'ssbg-guide', plugins_url( 'assets/guide.js', __FILE__ ), array(), version(), true );
	}
);

/**
 * Callback for the classic WooCommerce tab.
 *
 * @param string $key Tab key.
 * @param array  $tab Tab definition.
 */
function render( $key = '', $tab = array() ) {
	unset( $key, $tab );

	if ( Guide_State::$rendered ) {
		return;
	}

	global $product;

	if ( ! enabled( $product ) ) {
		return;
	}

	Guide_State::$rendered = true;
	Guide_State::$source   = 'wc';

	require __DIR__ . '/guide-content.php';
}

require_once __DIR__ . '/elementor-tabs.php';