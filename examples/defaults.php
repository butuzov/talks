<?php
/**
 * Rewrite Rules Reseter
 *
 * @category    WordPress_Plugin
 * @package     Using-Non-Defaults-URIs / Examples / Rewrite Rules Reseter
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        Reset URIs array to working minimum.
 * @copyright   2014-2018 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @wordpress-plugin
 *
 * Plugin Name: Rewrite Rules Reseter.
 * Plugin URI:  https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs/
 *
 * Description: Resets Generated URI to some working minimum.
 * Version:     0.2
 *
 * Author:      Oleg Butuzov
 * Author URI:  http://made.ua/
 *
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

register_activation_hook( __FILE__, function() {
	add_filter( 'rewrite_rules_array', 'rewrite_rules_array_defaults_reset' );
	flush_rewrite_rules();
});

register_deactivation_hook( __FILE__, function() {

	// Resets for Some general unused by me rules.
	remove_filter( 'category_rewrite_rules', '__return_empty_array' );
	remove_filter( 'tag_rewrite_rules', '__return_empty_array' );
	remove_filter( 'post_format_rewrite_rules', '__return_empty_array' );
	remove_filter( 'date_rewrite_rules',  '__return_empty_array' );

	remove_filter( 'rewrite_rules_array', 'rewrite_rules_array_defaults_reset', 12 );
	remove_filter( 'rewrite_rules_array', 'rewrite_rules_array_defaults_reset', 13 );
	flush_rewrite_rules();
});

// Shortening way to rewrite rules..
add_action( 'init', function() {

	// 1: General Example of rules reset using `__return_empty_array`.
	add_filter( 'category_rewrite_rules', '__return_empty_array' );
	add_filter( 'tag_rewrite_rules', '__return_empty_array' );
	add_filter( 'post_format_rewrite_rules', '__return_empty_array' );
	add_filter( 'date_rewrite_rules',  '__return_empty_array' );

	// 2: Example of rules filter/modification
	// Converts 2 landing page and paging rule.
	add_filter( 'rewrite_rules_array', 'rewrite_rules_array_pages_compact', 12 );

	// 3: Example fo filtering rules
	// Delete some legacy and unused (by me) rules.
	add_filter( 'rewrite_rules_array', 'rewrite_rules_array_defaults_reset', 13 );
});

/**
 * One rule for paging "unrequired" rules.
 *
 * ... so we can see and test a smaller amount of information.
 *
 * @param  array $rules    WP_Rewrite Rules.
 * @return array
 */
function rewrite_rules_array_pages_compact( array $rewrite_rules ) : array {
	global $wp_rewrite;

	$rules = [];

	$paging = '/page/?([0-9]{1,})';

	foreach( array_reverse($rewrite_rules) as $rule => $query ) {
		if ( strpos( $rule, $paging ) === false ) {
			$rules[ $rule ] = $query;
			continue;
		}

		// Deleting corresponding rule
		$delete_rule_key = str_replace( $paging, '', $rule );
		unset( $rules[ $delete_rule_key ] );

		$new_rule = str_replace( $paging, '[page]', $rule );
		$new_rule = preg_replace( '#(\([^(]+\)\[page\])#', '($0)', $new_rule);
		$new_rule = str_replace( '[page]', "($paging)?", $new_rule);

		$re_matches = '#([\?|&])([a-z\_]{1,})=\$matches\[(\d{1,})\]&paged=\$matches\[(\d{1,})\]#';
		preg_match( $re_matches, $query, $matched_matches );

		// Updating counter for page rule
		$query = str_replace(
			'$matches[' . $matched_matches[4] .']',
			'$matches[' . ( intval( $matched_matches[4] ) + 2 ) . ']',
			$query
		);

		// Updating counter for actual rule
		$query = str_replace(
			'$matches[' . $matched_matches[3] .']',
			'$matches[' . ( intval( $matched_matches[3] ) + 1 ) . ']',
			$query
		);

		$rules[ $new_rule ] = $query;
	}

	return $rules;
}

/**
 * Removes some of the "unrequired" rules.
 *
 * ... so we can see and test a smaller amount of information.
 *
 * @param  array $rules    WP_Rewrite Rules.
 * @return array
 */
function rewrite_rules_array_defaults_reset( array $rewrite_rules ) : array {

	// Removes "Embed" URLs.
	$rewrite_rules = array_filter( $rewrite_rules, function( $match_query, $rewrite_rule ) {
		return strpos( $match_query, 'embed=true' ) === false;
	}, ARRAY_FILTER_USE_BOTH);

	// Removes "Attachment" URLs.
	$rewrite_rules = array_filter( $rewrite_rules, function( $match_query ) {
		return strpos( $match_query, 'attachment=' ) === false;
	});

	// Removes "Trackback" URLs.
	$rewrite_rules = array_filter( $rewrite_rules, function( $match_query ) {
		return strpos( $match_query, 'tb=1' ) === false;
	});

	// Removes "Comments" URLs.
	$rewrite_rules = array_filter( $rewrite_rules, function( $match_query ) {
		return strpos( $match_query, 'cpage=' ) === false;
	});

	// Removes "author" URLs.
	$rewrite_rules = array_filter( $rewrite_rules, function( $match_query ) {
		return strpos( $match_query, 'author_name' ) === false;
	});

	// Feeds removal.
	global $wp_rewrite;

	$feeds = implode( '|', $wp_rewrite->feeds );
	$rewrite_rules = array_filter( $rewrite_rules, function( $rewrite_rule ) use ( $feeds ) {
		return strpos( $rewrite_rule, $feeds ) === false;
	}, ARRAY_FILTER_USE_KEY);

	// Removes Legacy URLS
	$rewrite_rules = array_filter( $rewrite_rules, function( $rewrite_rule ) {
		return strpos( $rewrite_rule, '.*wp-' ) === false;
	}, ARRAY_FILTER_USE_KEY);

	// wp-json - unused now.
	$rewrite_rules = array_filter( $rewrite_rules, function( $match_query ) {
		return strpos( $match_query, 'rest_route' ) === false;
	});

	// Removed 'page' (when you split content in post by page splitter)
	$rewrite_rules = array_filter( $rewrite_rules, function( $match_query ) {
		return strpos( $match_query, 'page=' ) === false;
	});

	return $rewrite_rules;
}
