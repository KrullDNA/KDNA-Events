<?php
/**
 * Menu smoke test.
 *
 * Loads the settings + CPT classes against a stubbed WordPress menu
 * API and verifies register_menu produces the expected sub-items in
 * the expected order, with show_in_menu=false on every CPT so WP's
 * _add_post_type_submenus would never fire. Also exercises the
 * parent_file / submenu_file filters that keep the Events sidebar
 * highlighted when editing one of our CPTs.
 *
 * Run: php tests/smoke-menu.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'KDNA_EVENTS_VERSION', '1.0.0' );
define( 'KDNA_EVENTS_FILE', dirname( __DIR__ ) . '/kdna-events.php' );
define( 'KDNA_EVENTS_PATH', dirname( __DIR__ ) . '/' );
define( 'KDNA_EVENTS_URL', 'https://example.test/wp-content/plugins/kdna-events/' );
define( 'KDNA_EVENTS_BASENAME', 'kdna-events/kdna-events.php' );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['_registered_post_types'] = array();
$GLOBALS['_recorded_menu_pages']   = array();
$GLOBALS['_recorded_submenus']     = array();
$GLOBALS['_hooks']                 = array();
$GLOBALS['_filters']               = array();
$GLOBALS['_options']               = array();
$GLOBALS['__stub_screen']          = null;

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['_hooks'][ $tag ][ $priority ][] = array( $callback, $accepted_args );
}
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['_filters'][ $tag ][ $priority ][] = array( $callback, $accepted_args );
}
function do_action( $tag, ...$args ) {
	if ( empty( $GLOBALS['_hooks'][ $tag ] ) ) { return; }
	$priorities = $GLOBALS['_hooks'][ $tag ];
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as $row ) {
			call_user_func_array( $row[0], array_slice( $args, 0, $row[1] ) );
		}
	}
}
function register_post_type( $slug, $args ) { $GLOBALS['_registered_post_types'][ $slug ] = $args; }
function register_taxonomy() {}
function register_post_meta() {}
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null ) {
	$GLOBALS['_recorded_menu_pages'][] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug' );
	return $menu_slug;
}
function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '', $position = null ) {
	$GLOBALS['_recorded_submenus'][] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug' );
	return $menu_slug;
}
function remove_submenu_page( $parent, $slug ) {
	foreach ( $GLOBALS['_recorded_submenus'] as $i => $row ) {
		if ( $row['parent_slug'] === $parent && $row['menu_slug'] === $slug ) {
			unset( $GLOBALS['_recorded_submenus'][ $i ] );
		}
	}
	$GLOBALS['_recorded_submenus'] = array_values( $GLOBALS['_recorded_submenus'] );
}
function register_setting() {}
function get_option( $key, $default = false ) { return $GLOBALS['_options'][ $key ] ?? $default; }
function _x( $text, $context = '', $domain = '' ) { return $text; }
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function get_current_screen() { return $GLOBALS['__stub_screen']; }

require_once dirname( __DIR__ ) . '/includes/class-kdna-events-cpt.php';
require_once dirname( __DIR__ ) . '/includes/class-kdna-events-settings.php';

$failures = 0;
$assert = function ( $cond, $msg ) use ( &$failures ) {
	echo ( $cond ? '[pass] ' : '[FAIL] ' ) . $msg . "\n";
	if ( ! $cond ) { $failures++; }
};

// --- Menu registration ---
KDNA_Events_CPT::init();
do_action( 'init' );
KDNA_Events_Settings::init();
do_action( 'admin_menu' );

$top   = $GLOBALS['_recorded_menu_pages'];
$sub   = $GLOBALS['_recorded_submenus'];
$slugs = array_column( $sub, 'menu_slug' );

$assert( 1 === count( $top ) && 'kdna-events' === $top[0]['menu_slug'], 'top-level Events menu registered once' );

$expected_subs = array(
	'edit.php?post_type=kdna_event',
	'post-new.php?post_type=kdna_event',
	'edit-tags.php?taxonomy=kdna_event_category&post_type=kdna_event',
	'edit.php?post_type=kdna_event_location',
	'edit.php?post_type=kdna_event_organiser',
	'kdna-events-settings',
);
foreach ( $expected_subs as $want ) {
	$assert( in_array( $want, $slugs, true ), "submenu registered: $want" );
}

foreach ( array_count_values( $slugs ) as $slug => $n ) {
	$assert( 1 === $n, "submenu appears exactly once: $slug (saw $n)" );
}

$assert( end( $slugs ) === 'kdna-events-settings', 'Settings is the last submenu entry' );

foreach ( array( 'kdna_event', 'kdna_event_location', 'kdna_event_organiser' ) as $cpt ) {
	$args = $GLOBALS['_registered_post_types'][ $cpt ] ?? array();
	$assert( array_key_exists( 'show_in_menu', $args ) && false === $args['show_in_menu'], "$cpt has show_in_menu=false" );
	$assert( ! empty( $args['show_ui'] ), "$cpt has show_ui=true" );
}

// --- parent_file / submenu_file filters ---
$cases = array(
	array( 'kdna_event',           'edit.php', 'edit.php', 'kdna-events', 'edit.php?post_type=kdna_event' ),
	array( 'kdna_event_location',  'edit.php', 'edit.php', 'kdna-events', 'edit.php?post_type=kdna_event_location' ),
	array( 'kdna_event_organiser', 'edit.php', 'edit.php', 'kdna-events', 'edit.php?post_type=kdna_event_organiser' ),
	array( 'page',                 'edit.php?post_type=page', 'page-new.php', 'edit.php?post_type=page', 'page-new.php' ),
);
foreach ( $cases as $c ) {
	$GLOBALS['__stub_screen'] = (object) array( 'post_type' => $c[0] );
	$parent  = KDNA_Events_Settings::filter_parent_file( $c[1] );
	$submenu = KDNA_Events_Settings::filter_submenu_file( $c[2], $parent );
	$assert( $parent === $c[3],  "parent_file for {$c[0]} = {$c[3]} (got $parent)" );
	$assert( $submenu === $c[4], "submenu_file for {$c[0]} = {$c[4]} (got $submenu)" );
}

// With no screen at all, filters must pass the input through untouched.
$GLOBALS['__stub_screen'] = null;
$assert( KDNA_Events_Settings::filter_parent_file( 'foo' ) === 'foo', 'parent_file is a no-op without a screen' );
$assert( KDNA_Events_Settings::filter_submenu_file( 'bar', 'foo' ) === 'bar', 'submenu_file is a no-op without a screen' );

echo "\n" . ( $failures ? "FAILED: $failures assertion(s)" : 'All menu assertions passed.' ) . "\n";
exit( $failures ? 1 : 0 );
