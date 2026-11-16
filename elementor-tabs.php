<?php
namespace SimorghSaya\BeltGuide;
defined( 'ABSPATH' ) || exit;

/** Resolve the actual single product, even when a related-products loop changed the global. */
function current_product() {
    return function_exists( 'is_product' ) && is_product() ? wc_get_product( get_queried_object_id() ) : null;
}

/**
 * This site's product template uses Elementor Atomic Tabs rather than WC Product Data Tabs.
 * Add the pair before Alpine initializes; its native handler derives tab IDs from DOM order.
 * Only this known product-information group is extended. Other tabs remain untouched.
 */
function elementor_tabs( $html ) {
    if ( ! enabled( current_product() ) || false === strpos( $html, 'data-id="3d04054"' ) ||
        false !== strpos( $html, 'data-ssbg-tab' ) || ! class_exists( '\DOMDocument' ) ) {
        return $html;
    }
    $previous = libxml_use_internal_errors( true );
    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $loaded = $dom->loadHTML( '<?xml encoding="UTF-8"><div id="ssbg-fragment">' . $html . '</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NONET );
    libxml_clear_errors();
    libxml_use_internal_errors( $previous );
    if ( ! $loaded ) { return $html; }
    $xpath = new \DOMXPath( $dom );
    $group = $xpath->query( '//*[@data-id="3d04054" and @data-element_type="e-tabs"]' )->item( 0 );
    if ( ! $group ) { return $html; }
    $menu = $xpath->query( './*[@data-element_type="e-tabs-menu"]', $group )->item( 0 );
    $area = $xpath->query( './*[@data-element_type="e-tabs-content-area"]', $group )->item( 0 );
    if ( ! $menu || ! $area ) { return $html; }
    $tabs = $xpath->query( './*[@data-element_type="e-tab"]', $menu );
    $panels = $xpath->query( './*[@data-element_type="e-tab-content"]', $area );
    if ( ! $tabs->length || $tabs->length !== $panels->length ) { return $html; }
    $index = $tabs->length;
    // Inherit the existing template's generated CSS classes, but no original IDs or handlers.
    $button = $dom->createElement( 'button' );
    $button->setAttribute( 'class', trim( str_replace( 'e--selected', '', $tabs->item( 0 )->getAttribute( 'class' ) ) ) );
    foreach ( array( 'type' => 'button', 'role' => 'tab', 'data-element_type' => 'e-tab', 'data-e-type' => 'e-tab',
        'data-ssbg-tab' => 'true', 'x-bind' => 'tab', 'tabindex' => '-1', 'aria-selected' => 'false',
        'id' => '3d04054-tab-' . $index, 'aria-controls' => '3d04054-tab-content-' . $index ) as $name => $value ) {
        $button->setAttribute( $name, $value );
    }
    $label = $dom->createElement( 'span' );
    $original_label = $xpath->query( './*', $tabs->item( 0 ) )->item( 0 );
    if ( $original_label ) { $label->setAttribute( 'class', $original_label->getAttribute( 'class' ) ); }
    $label->appendChild( $dom->createTextNode( 'راهنمای سایز و مراقبت' ) );
    $button->appendChild( $label );

    $panel = $dom->createElement( 'div' );
    $panel->setAttribute( 'class', trim( str_replace( 'e--selected', '', $panels->item( 0 )->getAttribute( 'class' ) ) ) . ' ssbg-tab-panel' );
    foreach ( array( 'role' => 'tabpanel', 'data-element_type' => 'e-tab-content', 'data-e-type' => 'e-tab-content',
        'x-bind' => 'tabContent', 'id' => '3d04054-tab-content-' . $index,
        'aria-labelledby' => '3d04054-tab-' . $index, 'style' => 'display:none' ) as $name => $value ) {
        $panel->setAttribute( $name, $value );
    }
    ob_start();
    require __DIR__ . '/guide-content.php';
    $guide = ob_get_clean();
    $guide_dom = new \DOMDocument( '1.0', 'UTF-8' );
    $previous = libxml_use_internal_errors( true );
    $guide_dom->loadHTML( '<?xml encoding="UTF-8">' . $guide, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NONET );
    libxml_clear_errors();
    libxml_use_internal_errors( $previous );
    $section = $guide_dom->getElementsByTagName( 'section' )->item( 0 );
    if ( ! $section ) { return $html; }
    $panel->appendChild( $dom->importNode( $section, true ) );
    $menu->appendChild( $button );
    $area->appendChild( $panel );
    $root = $dom->getElementById( 'ssbg-fragment' );
    if ( ! $root ) { return $html; }
    $output = '';
    foreach ( $root->childNodes as $child ) { $output .= $dom->saveHTML( $child ); }
    return $output;
}
add_filter( 'elementor/frontend/the_content', __NAMESPACE__ . '\elementor_tabs', 20 );

