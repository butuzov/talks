<?php
/**
 * Rules Reset - Rules and Matches filtering
 *
 * @category    WordPress_Plugin
 * @package     Using-Non-Defaults-URIs / Examples / Rewrite Rules and Matches Filtering
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        Reset URIs array to working minimum.
 * @copyright   2014-2018 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @wordpress-plugin
 *
 * Plugin Name: Ukrainian Monthes Names
 * Plugin URI:  https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs/
 *
 * Description: This plugin allows to use Ukrainian names for monthes instead of <code>%monthnum%</code> in your urls.
 * Version:     0.1
 *
 * Author:      Oleg Butuzov
 * Author URI:  http://made.ua/
 *
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Immitating init callback thathasn't happend yet.
register_activation_hook( __FILE__, function() {
	add_filter( 'rewrite_rules_array', 'rewrite_rules_array_defaults_reset' );
	flush_rewrite_rules();
});

// Rules Normalization.
register_deactivation_hook( __FILE__, function() {
	remove_filter( 'rewrite_rules_array', 'rewrite_rules_array_defaults_reset' );
	flush_rewrite_rules();
});

// Shortening way to rewrite rules..
add_action( 'init', function() {
	add_filter( 'rewrite_rules_array', 'rewrite_rules_array_defaults_reset' );
});

/**
 * Removes some of the "unrequired" rules.
 *
 * ... so we can see and test a smaller amount of information.
 *
 * @param  array $rules    WP_Rewrite Rules.
 * @return array
 */
function rewrite_rules_array_defaults_reset( array $rules ) : array {
	global $wp_rewrite;

	// Removes "Embed" URLs.
	$rules = array_filter( $rules, function( $match_query, $rewrite_rule ) {
		return strpos( $match_query, 'embed=true' ) === false;
	}, ARRAY_FILTER_USE_BOTH);

	// Removes "Attachment" URLs.
	$rules = array_filter( $rules, function( $match_query ) {
		return strpos( $match_query, 'attachment=' ) === false;
	});

	// Removes "Trackback" URLs.
	$rules = array_filter( $rules, function( $match_query ) {
		return strpos( $match_query, 'tb=1' ) === false;
	});

	// Removes "Comments" URLs.
	$rules = array_filter( $rules, function( $match_query ) {
		return strpos( $match_query, 'cpage=' ) === false;
	});

	// Removes "author" URLs.
	$rules = array_filter( $rules, function( $rewrite_rule ) {
		return strpos( $rewrite_rule, 'author/([^/]+)' ) === false;
	}, ARRAY_FILTER_USE_KEY);

	// Feeds removal.
	$feeds = implode( '|', $wp_rewrite->feeds );
	$rules = array_filter( $rules, function( $rewrite_rule ) use ( $feeds ) {
		return strpos( $rewrite_rule, $feeds ) === false;
	}, ARRAY_FILTER_USE_KEY);

	return $rules;
}

