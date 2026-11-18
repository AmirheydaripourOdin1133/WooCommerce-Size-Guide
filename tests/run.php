<?php
/**
 * Standalone smoke tests. No PHPUnit, no WordPress, no database.
 *
 *   php tests/run.php
 *
 * @package SimorghSaya\BeltGuide
 */

require_once __DIR__ . '/bootstrap.php';

use SimorghSaya\BeltGuide\Guide_State;

$GLOBALS['ssbg_test'] = array(
	'pass'   => 0,
	'fail'   => 0,
	'errors' => array(),
);

function check( $name, $condition, $detail = '' ) {
	if ( $condition ) {
		++$GLOBALS['ssbg_test']['pass'];
		echo "  PASS  {$name}\n";
		return true;
	}

	++$GLOBALS['ssbg_test']['fail'];
	$GLOBALS['ssbg_test']['errors'][] = $name . ( $detail ? " ({$detail})" : '' );
	echo "  FAIL  {$name}" . ( $detail ? " ({$detail})" : '' ) . "\n";

	return false;
}

function fixture() {
	return (string) file_get_contents( __DIR__ . '/fixtures/elementor-tabs-4.html' );
}

function run_elementor( $html ) {
	return \SimorghSaya\BeltGuide\elementor_tabs( $html );
}

function run_wc_tabs() {
	return apply_filters( 'woocommerce_product_tabs', array() );
}

echo "== Simorgh Belt Guide – smoke tests ==\n";

/* ------------------------------------------------------------- metadata */

echo "\n[meta]\n";
check( 'version() reads the header', '1.0.3' === \SimorghSaya\BeltGuide\version(), \SimorghSaya\BeltGuide\version() );
check( 'META_KEY is private', '_ssbg_enabled' === \SimorghSaya\BeltGuide\META_KEY );
check( 'elementor group id default', '3d04054' === \SimorghSaya\BeltGuide\elementor_group_id() );
check( 'tab title default', 'راهنمای سایز و مراقبت' === \SimorghSaya\BeltGuide\tab_title() );
check( 'content filter is registered', ! empty( $GLOBALS['ssbg_filters']['elementor/frontend/the_content'] ) );

/* ------------------------------------------------------- group id filter */

echo "\n[elementor_group_id filter]\n";
$cb = static function () { return 'abc_123'; };
add_filter( 'ssbg_elementor_group_id', $cb );
check( 'filter overrides the id', 'abc_123' === \SimorghSaya\BeltGuide\elementor_group_id() );

$cb_bad = static function () { return 'bad"id'; };
add_filter( 'ssbg_elementor_group_id', $cb_bad );
check( 'invalid id falls back to default', '3d04054' === \SimorghSaya\BeltGuide\elementor_group_id() );
ssbg_remove_filter( 'ssbg_elementor_group_id', $cb );
ssbg_remove_filter( 'ssbg_elementor_group_id', $cb_bad );
ssbg_reset();

/* --------------------------------------------------------- tab title filter */

echo "\n[tab_title filter]\n";
$cb_title = static function () { return 'راهنمای سایز'; };
add_filter( 'ssbg_tab_title', $cb_title );
check( 'filter overrides the title', 'راهنمای سایز' === \SimorghSaya\BeltGuide\tab_title() );

$cb_blank = static function () { return '   '; };
add_filter( 'ssbg_tab_title', $cb_blank );
check( 'blank title falls back', 'راهنمای سایز و مراقبت' === \SimorghSaya\BeltGuide\tab_title() );
ssbg_remove_filter( 'ssbg_tab_title', $cb_title );
ssbg_remove_filter( 'ssbg_tab_title', $cb_blank );
ssbg_reset();

/* ------------------------------------------------------------- injection */

echo "\n[elementor injection]\n";
ssbg_reset();
$html  = fixture();
$out   = run_elementor( $html );

check( 'html changed', $out !== $html );
check( 'tab marker present', false !== strpos( $out, 'data-ssbg-tab' ) );
check( '5 e-tab buttons', 5 === substr_count( $out, 'data-element_type="e-tab"' ), (string) substr_count( $out, 'data-element_type="e-tab"' ) );
check( '5 panels', 5 === substr_count( $out, 'data-element_type="e-tab-content"' ), (string) substr_count( $out, 'data-element_type="e-tab-content"' ) );
check( '3 details', 3 === substr_count( $out, '<details' ), (string) substr_count( $out, '<details' ) );
check( '9 size rows', 9 === substr_count( $out, 'ssbg-size' ), (string) substr_count( $out, 'ssbg-size' ) );
check( 'guide section present', false !== strpos( $out, 'class="ssbg"' ) );
check( 'panel is display:none', false !== strpos( $out, 'id="3d04054-tab-content-4"' ) && false !== strpos( $out, 'style="display:none"' ) );

preg_match_all( '/id="([^"]+)"/', $out, $matches );
$ids   = $matches[1];
$dupes = array_values( array_filter( array_count_values( $ids ), static function ( $count ) { return $count > 1; } ) );
check( 'no duplicate ids', empty( $dupes ), implode( ',', $dupes ) );
check( 'new ids are 0-based next free', in_array( '3d04054-tab-4', $ids, true ) && in_array( '3d04054-tab-content-4', $ids, true ) );

check( 'source marked elementor', 'elementor' === Guide_State::$source, Guide_State::$source );
check( 'second pass is a no-op', run_elementor( $out ) === $out );

/* --------------------------------------------------------- missing group */

echo "\n[guards]\n";
ssbg_reset();
$plain = '<div class="e-tabs" data-id="999" data-element_type="e-tabs"></div>';
check( 'no group -> unchanged', run_elementor( $plain ) === $plain );

ssbg_reset( false );
check( 'disabled product -> unchanged', run_elementor( fixture() ) === fixture() );
check( 'disabled product does not claim render', false === Guide_State::$rendered );

ssbg_reset();
$_GET['elementor-preview'] = '123';
check( 'editor preview -> unchanged', run_elementor( fixture() ) === fixture() );
unset( $_GET['elementor-preview'] );

ssbg_reset();
$blank = '';
check( 'empty html -> unchanged', run_elementor( $blank ) === $blank );

/* ------------------------------------------------------ anti-duplication */

echo "\n[anti-duplication]\n";
ssbg_reset();
run_elementor( fixture() );
$tabs = run_wc_tabs();
check( 'elementor first: WC tab not added', ! isset( $tabs['ssbg_guide'] ) );

ssbg_reset();
$tabs = run_wc_tabs();
check( 'wc alone: tab added', isset( $tabs['ssbg_guide'] ) );
check( 'wc tab title', 'راهنمای سایز و مراقبت' === $tabs['ssbg_guide']['title'] );
check( 'wc tab priority', 25 === $tabs['ssbg_guide']['priority'] );

$GLOBALS['product'] = $GLOBALS['ssbg_product'];
ob_start();
\SimorghSaya\BeltGuide\render( 'ssbg_guide', $tabs['ssbg_guide'] );
$rendered = ob_get_clean();
check( 'wc render output', false !== strpos( $rendered, 'class="ssbg"' ) && 9 === substr_count( $rendered, 'ssbg-size' ) );
check( 'wc marks render', true === Guide_State::$rendered && 'wc' === Guide_State::$source );

$out = run_elementor( fixture() );
check( 'wc first: elementor does not inject', false !== strpos( $out, 'data-id="3d04054"' ) && false === strpos( $out, 'data-ssbg-tab' ) );

/* ------------------------------------------------------------- render() */

echo "\n[render callback]\n";
ssbg_reset();
$GLOBALS['product'] = $GLOBALS['ssbg_product'];
ob_start();
\SimorghSaya\BeltGuide\render( 'ssbg_guide', array() );
$first = ob_get_clean();
ob_start();
\SimorghSaya\BeltGuide\render( 'ssbg_guide', array() );
$second = ob_get_clean();
check( 'first call renders', false !== strpos( $first, 'class="ssbg"' ) );
check( 'second call renders nothing', '' === trim( $second ) );

ssbg_reset( false );
$GLOBALS['product'] = $GLOBALS['ssbg_product'];
ob_start();
\SimorghSaya\BeltGuide\render( 'ssbg_guide', array() );
check( 'disabled product renders nothing', '' === trim( ob_get_clean() ) );

/* --------------------------------------------------------------- metabox */

echo "\n[metabox]\n";
ssbg_reset();
$post = (object) array( 'ID' => 42 );
ob_start();
\SimorghSaya\BeltGuide\settings( $post );
$box = ob_get_clean();
check( 'nonce field', false !== strpos( $box, 'ssbg_nonce' ) );
check( 'switch input', false !== strpos( $box, 'name="ssbg_enabled"' ) );
check( 'checked when enabled', false !== strpos( $box, "checked='checked'" ) );

ssbg_reset( false );
ob_start();
\SimorghSaya\BeltGuide\settings( $post );
$box = ob_get_clean();
check( 'unchecked when disabled', false === strpos( $box, "checked='checked'" ) );

/* ---------------------------------------------------------------- done */

$total = $GLOBALS['ssbg_test']['pass'] + $GLOBALS['ssbg_test']['fail'];
echo "\n----------------------------------------\n";
echo "{$GLOBALS['ssbg_test']['pass']} passed, {$GLOBALS['ssbg_test']['fail']} failed, {$total} total\n";

if ( $GLOBALS['ssbg_test']['fail'] ) {
	echo "\nFailures:\n";
	foreach ( $GLOBALS['ssbg_test']['errors'] as $error ) {
		echo " - {$error}\n";
	}
	exit( 1 );
}

exit( 0 );