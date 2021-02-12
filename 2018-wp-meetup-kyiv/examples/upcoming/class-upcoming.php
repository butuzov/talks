<?php
/**
 * Class that encapsulate functionality.
 *
 * @category    WordPress_Plugin
 * @package     Using-Non-Defaults-URIs / Examples / Upcoming Example
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        https://github.com/butuzov/talks/tree/master/2018-wp-meetup-kyiv
 * @copyright   2018 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Upcoming quey var usage
 */
class Upcoming {

	/**
	 * Singleton.
	 *
	 * @var Upcoming
	 */
	private static $_instance = null;

	/**
	 * Supported Post Types.
	 *
	 * @var array
	 */
	private $post_types = [];

	/**
	 * Function Construct.
	 */
	private function __construct() {

		// Adding Support For upcoming.
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_filter( 'parse_query', [ $this, 'parse_query_post_types' ] );
		add_filter( 'parse_query', [ $this, 'parse_query_taxonomies' ] );

		// Changing links.
		add_filter( 'post_type_link', [ $this, 'link' ], 10, 4 );
		add_filter( 'rewrite_rules_array', [ $this, 'rewrite_rules_array' ] );
	}

	/**
	 * Method getInstance of Singleton.
	 *
	 * @return Upcoming
	 */
	public static function getInstance() : Upcoming {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Adding Upcoming Query Vars
	 *
	 * @param array $query_vars Query Vars Incoming.
	 * @return array
	 */
	public function query_vars( array $query_vars ) : array {
		$query_vars[] = 'upcoming';
		return $query_vars;
	}

	/**
	 * Simple way to change taxonomy display for upcoming events.
	 *
	 * After this action on taxonome term page future posts will apear,
	 * no order altered.
	 *
	 * @param WP_Query $wp_query Incoming WP_Query object.
	 * @return WP_Query
	 */
	public function parse_query_taxonomies( WP_Query $wp_query ) : WP_Query {
		global $wp_taxonomies;
		foreach ( get_taxonomies() as $taxonomy_name ) {
			$taxonomy     = get_taxonomy( $taxonomy_name );
			$incoming_var = $wp_query->get( $taxonomy->query_var );

			if ( empty( $incoming_var ) ) {
				continue;
			}

			if ( isset( $taxonomy->object_type )
				&& count( array_intersect( $taxonomy->object_type, $this->post_types() ) ) > 0 ) {

				$post_status = $wp_query->get( 'post_status' );

				// Post Statuses.
				$statuses    = get_post_stati( [ 'public' => true ] );
				$statuses['future'] = 'future';

				// It's rather complicated logic so its up to you to include private and protected
				// psot statues based on user who read public posts.

				$wp_query->set( 'post_status', $statuses );

				return $wp_query;
			}
		}
		return $wp_query;
	}


	/**
	 * Parse Query for `upcoming`
	 *
	 * 1) We check exitance of 'upcoming' query var
	 * 2) We check if post type supported
	 * 3) We set 'future' post status to be public
	 * 4) We add filter for post_where_part of wp_query
	 *
	 * @param  WP_Query $wp_query WP_Query object.
	 * @return WP_Query
	 */
	public function parse_query_post_types( WP_Query $wp_query ) : WP_Query {

		if ( false === $wp_query->get( 'upcoming' ) ) {
			return $wp_query;
		}

		foreach ( $this->post_types() as $type ) {
			$vars = array_unique( [ $type, get_post_type_object( $type )->query_var ] );

			foreach ( $vars as $var ) {
				$incoming_var = $wp_query->get( $var );
				if ( ! empty( $incoming_var ) ) {

					$wp_query->set( 'order', 'ASC' );
					$wp_query->set( 'post_status', 'future' );
					add_filter( 'posts_where_request', [ $this, 'posts_where_request' ] );

					break 2;
				}
			}
		}
		return $wp_query;
	}

	/**
	 * Filter for where cases to remove publish post_status.
	 *
	 * @param string $where Where cases of Query Selection SQL Statment.
	 * @return string
	 */
	public function posts_where_request( string $where ) : string {
		global $wpdb;

		// Action for running only once.
		remove_filter( 'posts_where_request', [ $this, 'posts_where_request' ] );

		// And all its gonna do - remove publish from post post_statuses selection.
		return str_replace( "{$wpdb->posts}.post_status = 'publish' OR ", '', $where );
	}

	/**
	 * Return Supported post type that found in query match.
	 *
	 * @param  string $query  Match Query to analyse it.
	 * @return string Matched $post_type or Empty String.
	 */
	private function matches( $query ) : string {

		$tmp_query_vars    = [];
		list( $_, $query ) = explode( '?', $query );
		parse_str( $query, $tmp_query_vars );

		foreach ( $this->post_types() as $post_type ) {

			$matches = [
				'post_type' => $post_type,
				$post_type  => '*',
				get_post_type_object( $post_type )->query_var => '*',
			];

			foreach ( $matches as $key => $item ) {
				if ( ! isset( $tmp_query_vars[ $key ] ) ) {
					continue;
				}

				if ( '*' === $item || $item === $tmp_query_vars[ $key ] ) {
					return $post_type;
				}
			}
		}

		return '';

	}

	/**
	 * Filters Rewrite Rules
	 *
	 * @param array $rewrite_rules Rewrite Rules array.
	 * @return array
	 */
	public function rewrite_rules_array( array $rewrite_rules ) : array {
		global $wp_rewrite;

		// Nothing to change.
		if ( 0 === count( $this->post_types() ) ) {
			return $rewrite_rules;
		}

		$new_rules = array();

		foreach ( $rewrite_rules as $rule => $query ) {
			$post_type = $this->matches( $query );
			if ( empty( $post_type ) ) {
				$new_rules[ $rule ] = $query;
				continue;
			}

			// So this is query that matches to one of our supported post types.

			$pto = get_post_type_object( $post_type );

			$archive_slug = substr( $wp_rewrite->front, (int) $pto->rewrite['with_front'] )
								. $pto->rewrite['slug'];

			$replaced_rule  = str_replace( $archive_slug, $archive_slug . '/upcoming', $rule );
			$replaced_query = str_replace( 'index.php?', 'index.php?upcoming=true&', $query );

			$new_rules[ $replaced_rule ] = $replaced_query;
			$new_rules[ $rule ]          = $query;
		}

		return $new_rules;
	}

	/**
	 * A filter for `post_type_link`.
	 *
	 * Allows developers to change links to post types.
	 *
	 * @param string  $link       Originally generated link.
	 * @param WP_Post $post      WP_POSt object for $post.
	 * @param boolean $leavename Whether to keep the post name.
	 * @param boolean $sample    Is it a sample permalink.
	 * @return string
	 */
	public function link( string $link, WP_Post $post, bool $leavename, bool $sample ) : string {
		global $wp_rewrite;

		if ( 'future' !== $post->post_status || ! $this->post_type_supported( $post->post_type ) ) {
			return $link;
		}

		$post_type_object = get_post_type_object( $post->post_type );

		$url_base        = rtrim( $this->get_rewrite_base( $post->post_type ), '/' );
		$url_query_var   = sprintf( '%%%s%%', $post_type_object->query_var );
		$url_trail_slash = $wp_rewrite->use_trailing_slashes ? '/' : '';

		// Forming upcoming link.
		$upcoming_link = home_url( $url_base . '/upcoming/' . $url_query_var . $url_trail_slash );

		if ( true === $leavename ) {
			return $upcoming_link;
		}

		return str_replace( $url_query_var, $post->post_name, $upcoming_link );
	}

	/**
	 * Return the post type struct link without query var .
	 *
	 * @param string $post_type Post Type name.
	 * @return string
	 */
	public function get_rewrite_base( string $post_type ) : string {
		global $wp_rewrite;

		$post_type_object = get_post_type_object( $post_type );
		$replace          = sprintf( '%%%s%%', $post_type_object->query_var );

		return str_replace( $replace, '', $wp_rewrite->get_extra_permastruct( $post_type ) );
	}



	/**
	 * Adds post type to list of post types that going to get upcoming support.
	 *
	 * @param string $post_type Post Type name.
	 * @return void
	 */
	public function post_type_add( string $post_type ) {

		$type = get_post_type_object( $post_type );

		if ( false === $type || empty( $type->has_archive ) ) {
			return;
		}

		$this->post_types[] = $post_type;
	}

	/**
	 * Check whenever psot type can support "upcoming" functionality.
	 *
	 * @param string $post_type Post Type name.
	 * @return boolean
	 */
	public function post_type_supported( string $post_type ) : bool {
		return in_array( $post_type, $this->post_types, true );
	}

	/**
	 * Return supported post types.
	 *
	 * @return array
	 */
	private function post_types() : array {
		return $this->post_types;
	}
}
