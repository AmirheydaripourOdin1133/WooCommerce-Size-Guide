<?php
/**
 * Elementor Atomic Tabs integration.
 *
 * @package SimorghSaya\BeltGuide
 */

namespace SimorghSaya\BeltGuide;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the actual single product, even when a related-products loop changed the global.
 *
 * @return \WC_Product|null
 */
function current_product() {
	return function_exists( 'is_product' ) && is_product() ? wc_get_product( get_queried_object_id() ) : null;
}

/**
 * Append a guide tab to the site's Elementor "product information" tab group.
 *
 * The single-product template uses Elementor Atomic Tabs instead of the classic
 * WooCommerce product data tabs. Elementor's Alpine handler derives tab ids from
 * DOM order at runtime, so the pair can be added to the rendered markup before
 * the frontend script initialises.
 *
 * Only the known product-information group is extended; every other tab group on
 * the page is left untouched. The group id is filterable
 * (`ssbg_elementor_group_id`) for when the template is rebuilt.
 *
 * @param string $html Rendered Elementor content.
 * @return string
 */
function elementor_tabs( $html ) {
	if ( Guide_State::$rendered || ! is_string( $html ) || '' === $html ) {
		return $html;
	}

	// Never inject into the editor preview iframe: the tab would not be editable.
	if ( is_elementor_editor() ) {
		return $html;
	}

	if ( ! enabled( current_product() ) ) {
		return $html;
	}

	$group = elementor_group_id();

	if ( false === strpos( $html, 'data-id="' . $group . '"' ) ) {
		return $html;
	}

	if ( false !== strpos( $html, 'data-ssbg-tab' ) ) {
		return $html;
	}

	// The whole approach needs PHP's DOM extension; degrade instead of fataling.
	if ( ! class_exists( '\\DOMDocument' ) ) {
		return $html;
	}

	$previous = libxml_use_internal_errors( true );
	$dom      = new \DOMDocument( '1.0', 'UTF-8' );
	$loaded   = $dom->loadHTML(
		'<?xml encoding="UTF-8"?><div id="ssbg-fragment">' . $html . '</div>',
		LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NONET
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded ) {
		return $html;
	}

	$xpath      = new \DOMXPath( $dom );
	$group_node = $xpath->query( '//*[@data-id="' . $group . '" and @data-element_type="e-tabs"]' )->item( 0 );

	if ( ! $group_node ) {
		return $html;
	}

	$menu = $xpath->query( './*[@data-element_type="e-tabs-menu"]', $group_node )->item( 0 );
	$area = $xpath->query( './*[@data-element_type="e-tabs-content-area"]', $group_node )->item( 0 );

	if ( ! $menu || ! $area ) {
		return $html;
	}

	$tabs   = $xpath->query( './*[@data-element_type="e-tab"]', $menu );
	$panels = $xpath->query( './*[@data-element_type="e-tab-content"]', $area );

	if ( ! $tabs->length || $tabs->length !== $panels->length ) {
		return $html;
	}

	// Elementor numbers tabs from zero, so the next free index equals the current count.
	$index = $tabs->length;

	// Inherit the template's generated classes, but none of its ids or handlers.
	$button = $dom->createElement( 'button' );
	$button->setAttribute( 'class', trim( str_replace( 'e--selected', '', $tabs->item( 0 )->getAttribute( 'class' ) ) ) );

	foreach ( array(
		'type'             => 'button',
		'role'             => 'tab',
		'data-element_type' => 'e-tab',
		'data-e-type'      => 'e-tab',
		'data-ssbg-tab'    => 'true',
		'x-bind'           => 'tab',
		'tabindex'         => '-1',
		'aria-selected'    => 'false',
		'id'               => $group . '-tab-' . $index,
		'aria-controls'    => $group . '-tab-content-' . $index,
	) as $name => $value ) {
		$button->setAttribute( $name, $value );
	}

	$label          = $dom->createElement( 'span' );
	$original_label = $xpath->query( './*', $tabs->item( 0 ) )->item( 0 );
	if ( $original_label ) {
		$label->setAttribute( 'class', $original_label->getAttribute( 'class' ) );
	}
	$label->appendChild( $dom->createTextNode( tab_title() ) );
	$button->appendChild( $label );

	$panel = $dom->createElement( 'div' );
	$panel->setAttribute( 'class', trim( str_replace( 'e--selected', '', $panels->item( 0 )->getAttribute( 'class' ) ) ) . ' ssbg-tab-panel' );

	foreach ( array(
		'role'             => 'tabpanel',
		'data-element_type' => 'e-tab-content',
		'data-e-type'      => 'e-tab-content',
		'x-bind'           => 'tabContent',
		'id'               => $group . '-tab-content-' . $index,
		'aria-labelledby'  => $group . '-tab-' . $index,
		// No `hidden` attribute: it would fight Elementor's x-show toggle.
		'style'            => 'display:none',
	) as $name => $value ) {
		$panel->setAttribute( $name, $value );
	}

	ob_start();
	require __DIR__ . '/guide-content.php';
	$guide = ob_get_clean();

	$guide_dom = new \DOMDocument( '1.0', 'UTF-8' );
	$previous  = libxml_use_internal_errors( true );
	$guide_dom->loadHTML( '<?xml encoding="UTF-8">' . $guide, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	$section = $guide_dom->getElementsByTagName( 'section' )->item( 0 );
	if ( ! $section ) {
		return $html;
	}

	$panel->appendChild( $dom->importNode( $section, true ) );
	$menu->appendChild( $button );
	$area->appendChild( $panel );

	$root = $dom->getElementById( 'ssbg-fragment' );
	if ( ! $root ) {
		return $html;
	}

	$output = '';
	foreach ( $root->childNodes as $child ) {
		$output .= $dom->saveHTML( $child );
	}

	Guide_State::$rendered = true;
	Guide_State::$source   = 'elementor';

	return $output;
}

add_filter( 'elementor/frontend/the_content', __NAMESPACE__ . '\elementor_tabs', 20 );