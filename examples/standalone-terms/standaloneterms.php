<?php
/**
 * Standalone URL's for Taxonomy Terms
 *
 * @category    WordPress_Plugin
 * @package     Using-Non-Defaults-URIs / Examples / Standalone Terms URLs
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs
 * @copyright   2018 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @wordpress-plugin
 *
 * Plugin Name: Standalone URL's for Taxonomy Terms
 * Plugin URI:  https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs
 *
 * Description: Allows taxonomy terms to have a (page based) standalone URLs.
 * Version:     0.1
 *
 * Author:      Oleg Butuzov
 * Author URI:  https://made.ua/
 *
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 */


include_once __DIR__ . '/class-standaloneterms.php';



// This plugin demonstrate how to enable standalone urls for your taxonomy terms.
// It uses 'genres-mpte' from 'movies.php' as example taxonomy for our needs.

add_action( 'init', function() {
	StandaloneTerms::getInstance()->register_taxonomy( 'genres-mpte' );
});
