<?php
/**
 * PerPage_Manager
 *
 * @category    WordPress_Plugin
 * @package     Using-Non-Defaults-URIs / Examples / Custom Per Page Number
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs
 * @copyright   2018 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Class Responsibility
 *
 * - Spawn Custom Per Page Controllers for Custom Post Types
 * - Handles Query vars (creation, parsing)
 */
class PerPage_Manager {

	/**
	 * Handlers (per post type) collection.
	 *
	 * @var array
	 */
	public $handlers;

	/**
	 * $wpdb->options table field to store settings.
	 *
	 * @var string
	 */
	private $option = 'custom_per_page';

	/**
	 * Class Constructor
	 *
	 * @return void
	 */
	public function __construct() {

		$this->handlers = [];
		$this->settings = get_option( $this->option, [] );

		add_action( 'init', [ $this, 'init' ], 1000 );
	}


	/**
	 * WordPress default `init` action hook
	 *
	 * Spawning new PerPage_Instance for Custom Post Types.
	 *
	 * @return void
	 */
	public function init() {
		foreach ( $this->get_post_types() as $post_type ) {
			// Local setting.
			$settings = isset( $this->settings[ $post_type ] )
							? $this->settings[ $post_type ] : [];

			// Entry for handlers collection.
			$this->handlers[ $post_type ] = new PerPage_Handler( $post_type, $settings );
		}

		// adding `sp`.
		add_filter( 'query_vars', [ $this, 'query_vars' ] );

		// We interested only in front end.
		if ( is_admin() ) {
			return;
		}

		// custom logic for query parsing.
		add_filter( 'parse_query', [ $this, 'parse_query_all' ] );
		add_filter( 'parse_query', [ $this, 'parse_query_custom' ] );
	}

	/**
	 * Query_vars filter
	 *
	 * We adding a new query vars, that we will use later to swap with nopaging.
	 *
	 * @param array $query_vars WordPress query vars.
	 * @return array
	 */
	public function query_vars( array $query_vars ) : array {
		$query_vars[] = 'sp'; // Single Page (pagingn).
		return $query_vars;
	}

	/**
	 * Changing WP_Query using filters.
	 *
	 * What are we do here is substitute out incoming `sp` query var with `nopaging` - one
	 * that supported by WordPress defaults. We not able (and not adviced) to use `nopaging`
	 * directly, becouse exposing it to outside ( `nopaging` is private query var or 'notexposed' ).
	 * Also keep in mind that `sp` also should be `notexposed` in terms realization should be
	 * hidden or anyone can add `sp=1` to your pages and create Artificial load really easy.
	 *
	 * @param WP_Query $query Wp_Query object passed to be filtread (or changed).
	 * @return WP_Query
	 */
	public function parse_query_all( WP_Query $query ) : WP_Query {

		if ( 1 === (int) $query->get( 'sp' ) ) {
			$query->set( 'nopaging', true );
			$query->set( 'sp', 0 );
		}

		return $query;
	}


	/**
	 * Changing WP_Query using filters.
	 *
	 * Other example or changing query vars is custom posts_per_page number, you can go further
	 * and have different posts numper for featured categories and some other for default ones.
	 *
	 * @param WP_Query $query Wp_Query object passed to be filtread (or changed).
	 * @return WP_Query
	 */
	public function parse_query_custom( WP_Query $query ) : WP_Query {

		$post_type = $query->get( 'post_type' );

		if ( isset( $this->handlers[ $post_type ] )
			&& 'custom' === $this->handlers[ $post_type ]->get_type_per_page() ) {

			$query->set( 'posts_per_page', $this->handlers[ $post_type ]->get_posts_per_page() );
		}

		return $query;
	}


	/**
	 * Simplified get_post_types.
	 *
	 * Returns array of post type names we can use
	 * to make custom settings.
	 *
	 * @return array
	 */
	private function get_post_types() {
		return get_post_types( [
			'public'             => true,
			'has_archive'        => true,
			'publicly_queryable' => true,
			'_builtin'           => false,
		] );
	}

	/**
	 * Class Destructor.
	 *
	 * Handles final settingss save.
	 */
	public function __destruct() {
		if ( ! empty( $this->handlers ) ) {
			foreach ( $this->handlers as $post_type => $handler ) {

				$settings = new stdClass();

				switch ( $handler->get_type_per_page() ) {
					case 'all':
						$settings->type = 'all';
						break;
					case 'custom':
						$settings->type   = 'custom';
						$settings->number = $handler->get_posts_per_page();
						break;
				}

				$this->settings[ $post_type ] = $settings;
			}
		}

		update_option( $this->option, $this->settings );
	}
}
