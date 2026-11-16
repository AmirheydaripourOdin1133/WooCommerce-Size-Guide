<?php
/**
 * Plugin Name: راهنمای کمربند سیمرغ‌سایا
 * Description: تب اختیاری راهنمای سایز و مراقبت کمربند با کنترل مستقل برای هر محصول.
 * Version: 1.0.2
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: simorgh-belt-guide
 */
namespace SimorghSaya\BeltGuide;
defined( 'ABSPATH' ) || exit;
const VERSION = '1.0.2';
const META_KEY = '_ssbg_enabled';

add_action( 'add_meta_boxes_product', static function () {
    if ( ! function_exists( 'wc_get_product' ) ) { return; }
    add_meta_box( 'ssbg-settings', 'راهنمای کمربند سیمرغ‌سایا', __NAMESPACE__ . '\\settings', 'product', 'side', 'default' );
} );

function settings( $post ) {
    $product = wc_get_product( $post->ID );
    $enabled = $product && 'yes' === $product->get_meta( META_KEY );
    wp_nonce_field( 'ssbg_save', 'ssbg_nonce' );
    ?>
    <div class="ssbg-settings">
        <label class="ssbg-switch">
            <input type="checkbox" role="switch" name="ssbg_enabled" value="yes" <?php checked( $enabled ); ?> aria-describedby="ssbg-help">
            <span class="ssbg-switch-track" aria-hidden="true"></span>
            <span>نمایش راهنمای کمربند</span>
        </label>
        <p id="ssbg-help">با فعال‌سازی و ذخیرهٔ محصول، تب «راهنمای سایز و مراقبت» کنار تب‌های محصول نمایش داده می‌شود. محتوا ثابت و شامل جدول سایز، روش اندازه‌گیری و مراقبت است.</p>
        <p class="ssbg-state"><span class="ssbg-state-on">فعال</span><span class="ssbg-state-off">غیرفعال</span> — پس از به‌روزرسانی محصول ذخیره می‌شود.</p>
    </div>
    <?php
}

// WooCommerce validates its product-save nonce before this hook; also validate our own field.
add_action( 'woocommerce_admin_process_product_object', static function ( $product ) {
    if ( ! isset( $_POST['ssbg_nonce'] ) || ! is_string( $_POST['ssbg_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ssbg_nonce'] ) ), 'ssbg_save' ) ||
        ! current_user_can( 'edit_post', $product->get_id() ) ||
        ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { return; }
    $enabled = isset( $_POST['ssbg_enabled'] ) && is_string( $_POST['ssbg_enabled'] ) && 'yes' === wp_unslash( $_POST['ssbg_enabled'] );
    $product->update_meta_data( META_KEY, $enabled ? 'yes' : 'no' );
} );

add_action( 'admin_enqueue_scripts', static function () {
    $screen = get_current_screen();
    if ( $screen && 'product' === $screen->post_type && 'post' === $screen->base ) {
        wp_enqueue_style( 'ssbg-admin', plugins_url( 'assets/admin.css', __FILE__ ), array(), VERSION );
    }
} );

function enabled( $product ) {
    return $product instanceof \WC_Product && 'yes' === $product->get_meta( META_KEY );
}

add_filter( 'woocommerce_product_tabs', static function ( $tabs ) {
    global $product;
    if ( enabled( $product ) ) {
        $tabs['ssbg_guide'] = array( 'title' => 'راهنمای سایز و مراقبت', 'priority' => 25, 'callback' => __NAMESPACE__ . '\\render' );
    }
    return $tabs;
} );

add_action( 'wp_enqueue_scripts', static function () {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
    if ( ! enabled( wc_get_product( get_queried_object_id() ) ) ) { return; }
    wp_enqueue_style( 'ssbg-guide', plugins_url( 'assets/guide.css', __FILE__ ), array(), VERSION );
    wp_enqueue_script( 'ssbg-guide', plugins_url( 'assets/guide.js', __FILE__ ), array(), VERSION, true );
} );

function render( $key = '', $tab = array() ) {
    global $product;
    if ( ! enabled( $product ) ) { return; }
    require __DIR__ . '/guide-content.php';
}

require_once __DIR__ . '/elementor-tabs.php';


