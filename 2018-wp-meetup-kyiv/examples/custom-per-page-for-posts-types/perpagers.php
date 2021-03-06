<?php
/**
 * Custom Per Page For Post Types - example of custom query var passed to match query.
 *
 * @category    WordPress_Plugin
 * @package     Using-Non-Defaults-URIs / Examples / Custom Per Page Number
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        https://github.com/butuzov/talks/tree/master/2018-wp-meetup-kyiv
 * @copyright   2018 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @wordpress-plugin
 *
 * Plugin Name: Custom Per Page For Post Types
 * Plugin URI:  https://github.com/butuzov/talks/tree/master/2018-wp-meetup-kyiv
 *
 * Description: Example of custom query var passed to match query.
 * Version:     0.1
 *
 * Author:      Oleg Butuzov
 * Author URI:  http://made.ua/
 *
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Example does not provide any additional logic for activate/deactivate
// rewrite_rules cleanup procedures.

include_once __DIR__ . '/perpage-manager.php';
include_once __DIR__ . '/perpage-handler.php';

new PerPage_Manager();


