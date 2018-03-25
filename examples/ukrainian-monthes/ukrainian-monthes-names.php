<?php
/**
 * This plugin allows to use Ukrainian names for monthes instead of
 * <code>%monthnum%</code> in your urls.
 *
 * @category    WordPress_Plugin
 * @package     Using-Non-Defaults-URIs / Examples / Ukrainian Monthes Names
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs
 * @copyright   2008-2018 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @wordpress-plugin
 *
 * Plugin Name: Ukrainian Monthes Names
 * Plugin URI:  https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs
 *
 * Description: This plugin allows to use Ukrainian names for monthes instead of <code>%monthnum%</code> in your urls.
 * Version:     0.2
 *
 * Author:      Oleg Butuzov
 * Author URI:  https://made.ua/
 *
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 */



// *****************************************************************************
// Main action function setting all trigger actions for our plugin.
// *****************************************************************************

register_activation_hook( __FILE__, function() {
	add_filter( 'date_rewrite_rules', 'replace_monthnum_rules_in_rewrite_map' );
	add_filter( 'post_rewrite_rules', 'replace_monthnum_rules_in_rewrite_map' );
	flush_rewrite_rules();
});

register_deactivation_hook( __FILE__, function() {
	remove_filter( 'date_rewrite_rules', 'replace_monthnum_rules_in_rewrite_map' );
	remove_filter( 'post_rewrite_rules', 'replace_monthnum_rules_in_rewrite_map' );
	flush_rewrite_rules();
});

add_action( 'init', function() {
	add_filter( 'date_rewrite_rules', 'replace_monthnum_rules_in_rewrite_map' );
	add_filter( 'post_rewrite_rules', 'replace_monthnum_rules_in_rewrite_map' );

	// Changes links for monthes (used everywhere).
	add_filter( 'month_link', 'link_for_ukrainian__widget_archive', 10, 3 );
	add_filter( 'day_link', 'link_for_ukrainian__widget_calendar', 10, 4 );

	// Both this filters accept 3 parameters but we will use 1 and 2 becouse it fits to us.
	add_filter( 'pre_post_link', 'link_for_ukrainian__post_link_prepare' );
	add_filter( 'post_link', 'link_for_ukrainian__post_link_monthnum_transform', 10, 2 );

	add_filter( 'query_string', 'query_string_monthnum_parser' );

	if ( ! is_admin() ) {
		return;
	}

	if ( ! is_monthnum_found() ) {
		/**
		 * Adds Admin Notice for %monthnum% not available condition.
		 */
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>',
				sprintf( 'Please: Add <code>%s</code> to your Permalink Settings or Turn-off <code>%s</code> plugin.', '%monthnum%', plugin_basename( __FILE__ ) ),
				'</p></div>'; // wpcs: xss ok.
		});
	}

});

/**
 * Restore Query String to WP native state (in regards of %monthnum%)
 *
 * @param string $query_string  Query String we changing...
 * @return string               ....and returning.
 */
function query_string_monthnum_parser( string $query_string ) : string {

	if ( false === strpos( $query_string, 'monthnum' ) ) {
		return $query_string;
	}

	// It's cheaper to use string functions then regularexpressions.
	list( $pre_month , $post_month ) = explode( 'monthnum=', $query_string );
	list( $month, $post_month )      = explode( '&', $post_month, 2 );

	$month_key = array_search( $month, get_ukrainian_monthes(), true );

	if ( false !== $key_of_month ) {
		// Putting zero leading month back to query.
		$query_string = str_replace( $month, sprintf( "%'02s", 1 + $month_key ), $query_string );
	}

	return $query_string;
}

/**
 * Month Link for archive widget.
 *
 * @param string $link  Link to month.
 * @param string $year  Actual year (as string).
 * @param string $month Actual month (as string).
 * @return string       Altered Link.
 */
function link_for_ukrainian__widget_archive( string $link, string $year, string $month ) : string {
	global $wp_rewrite;

	$monthlink = $wp_rewrite->get_month_permastruct();
	if ( empty( $monthlink ) ) {
		return $link;
	}

	$month_index = ( (int) $month ) - 1;

	$monthlink = str_replace( '%year%', $year, $monthlink );
	$monthlink = str_replace( '%monthnum%', get_ukrainian_monthes()[ $month_index ], $monthlink );

	return home_url( user_trailingslashit( $monthlink ) );
}

/**
 * Day link for calendar widget.
 *
 * @param string $link  Link to month.
 * @param string $year  Actual year (as string).
 * @param string $month Actual month (as string).
 * @param string $day   Actual day (as string).
 * @return string
 */
function link_for_ukrainian__widget_calendar( string $link, string $year, string $month, string $day ) : string {
	global $wp_rewrite;

	$daylink = $wp_rewrite->get_day_permastruct();

	if ( empty( $daylink ) ) {
		return $link;
	}

	$month_index = ( (int) $month ) - 1;

	$daylink = str_replace( '%year%', $year, $daylink );
	$daylink = str_replace( '%day%', $day, $daylink );
	$daylink = str_replace( '%monthnum%', get_ukrainian_monthes()[ $month_index ], $daylink );

	return home_url( user_trailingslashit( $daylink ) );
}


/**
 * Prepare a link for transformation
 *
 * @param string $link         Link pattern as you can see in permlink_structure option field.
 * @return string
 */
function link_for_ukrainian__post_link_prepare( string $link ) : string {
	return str_replace( '%monthnum%', '%ukrmonthnum%', $link );
}

/**
 * Undocumented function
 *
 * @param string  $permalink     Link pattern.
 * @param WP_Post $post          WP_Post Object.
 * @return string
 */
function link_for_ukrainian__post_link_monthnum_transform( string $permalink, WP_Post $post ) : string {
	$month_index = date( 'n', strtotime( $post->post_date ) ) - 1;
	return str_replace( '%ukrmonthnum%', get_ukrainian_monthes()[ $month_index ], $permalink );
}


/**
 * Undocumented function
 *
 * @param  array $rewrite_rules_array Array of WP's Rewrite Rules.
 * @return array
 */
function replace_monthnum_rules_in_rewrite_map( array $rewrite_rules_array ) : array {

	/**
	 * We need closure to count what month we replacing.
	 * %day% will have same regexp as %monthnum%
	 */
	$replace = function( $counter, $index ) {
		$i = $counter;
		return function( $n ) use ( &$i, $index ) : string {
			$match = $n[1];
			if ( ++$i === $index ) {
				$match = implode( '|', get_ukrainian_monthes() );
			}
			return sprintf( '(%s)', $match );
		};
	};

	$pattern = '&monthnum=$matches[';

	$array_of_rules_to_be_returned = array();

	foreach ( $rewrite_rules_array as $rule => $matched_query ) {

		$position = strpos( $matched_query, $pattern );
		if ( false !== $position ) {
			$index = (int) substr( $matched_query, $position + strlen( $pattern ) );
			$rule  = preg_replace_callback( '/\((.*?)\)/si', $replace( 0, $index ), $rule );
		}

		$array_of_rules_to_be_returned[ $rule ] = $matched_query;
	}

	return $array_of_rules_to_be_returned;
}


/**
 * Returns monthes aliases, keys are zero-based month name equivalent number.
 *
 * @return array
 */
function get_ukrainian_monthes() : array {
	return array( 'sichen', 'lutiy', 'berezen', 'kviten', 'traven', 'cherven', 'lipen', 'serpen', 'veresen', 'jovten', 'lystopad', 'gryden' );
}

/**
 * Return if `%monthnum%` found in `permalink_structure` option.
 *
 * @return bool
 */
function is_monthnum_found(): bool {
	return strpos( get_option( 'permalink_structure' ), '%monthnum%' ) !== false;
}


/**
 * Admin Notice for %monthnum% not available condition.
 *
 * @return void
 */
function admin_notice_monthnum_not_available() {
	$message = sprintf(
			'Please turn-off <code>%s<code> or add <code>%s</code> to your reqrite',
			plugin_basename( __FILE__ ),
			'%monthnum%'
	);
	echo '<div class="notice notice-error"><p>', $message, '</p></div>'; // wpcs: xss ok.
}