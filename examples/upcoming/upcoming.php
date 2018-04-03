<?php
/**
 * Upcoming !
 *
 * @category    WordPress_Plugin
 * @package     Using-Non-Defaults-URIs / Examples / Upcoming Example
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs
 * @copyright   2018 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @wordpress-plugin
 *
 * Plugin Name: Upcoming!
 * Plugin URI:  https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs
 *
 * Description: Add Way to show Upcoming Post Type Posts
 * Version:     0.1
 *
 * Author:      Oleg Butuzov
 * Author URI:  https://made.ua/
 *
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 */


include_once __DIR__ . '/class-upcoming.php';

$upcoming = Upcoming::getInstance();

// Adding out sample post type plugin.
add_action( 'init', function() use ( $upcoming ) {
	$upcoming->post_type_add( 'mpte' );
});
