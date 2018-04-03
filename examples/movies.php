<?php
/**
 * "Movies" Post Type!
 *
 * @category    WordPress_Plugin
 * @package     Using-Non-Defaults-URIs / Examples / Movies!
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs
 * @copyright   2018 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @wordpress-plugin
 *
 * Plugin Name: "Movies" Post Type!
 * Plugin URI:  https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs
 *
 * Description: Plugin register "Movie" Post Type - so we can use it to test our examples.
 * Version:     0.1
 *
 * Author:      Oleg Butuzov
 * Author URI:  https://made.ua/
 *
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 */

/**************************************************************************************
 * Install and Uninstall procedures.
 *************************************************************************************/

add_action( 'reset_rewrite_rules', 'flush_rewrite_rules' );

register_activation_hook( __FILE__, function() {
	wp_schedule_single_event( time(), 'create_default_movies_posts' );
	wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'reset_rewrite_rules' );
});

register_deactivation_hook( __FILE__, function() {
	Movie::uninstall();
	flush_rewrite_rules();
});

/**
 * Movie Post Type handler.
 */
class Movie {

	/**
	 * Post Type name.
	 *
	 * @var string
	 */
	private $post_type = 'mpte'; // movies post type example.

	/**
	 * Class constructor.
	 */
	public function __construct() {

		// Order matter.
		add_action( 'init', [ $this, 'register_movie_post_types' ], 0 );
		add_action( 'init', [ $this, 'register_movie_taxonomies' ], 0 );

		// Install Action.
		add_action( 'create_default_movies_posts', [ $this, 'metacritic' ] );

	}

	/**
	 * Uninstall functionality - removes all sample data.
	 *
	 * @return void
	 */
	public static function uninstall() {

		foreach ( [ 'genres-mpte', 'actors-mpte' ] as $taxonomy ) {
			$terms = get_terms( $taxonomy, [
				'hide_empty' => false,
				'fields'     => 'ids',
			] );

			foreach ( $terms as $term_id ) {
				wp_delete_term( $term_id, $taxonomy );
			}
		}

		$posts = get_posts([
			'post_type'   => 'mpte',
			'post_status' => 'all',
			'showposts'   => -1,
			'fields'      => 'ids',
		]);

		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

	}

	/**
	 * Generate sample data for Testing.
	 *
	 * @return void
	 */
	public function metacritic() {
		$url = 'http://www.metacritic.com/browse/movies/score/metascore/year/filtered'
					. '?year_selected=2018&sort=desc';

		$response = wp_safe_remote_get( $url, [ 'timeout' => 60 ] );
		if ( is_wp_error( $response ) ) {
			return;
		}

		$data = wp_remote_retrieve_body( $response );

		$split_pattern = '/class="summary_row">(.*?)class="details_row">(.*?)<tr/si';
		preg_match_all( $split_pattern, $data, $movies );

		foreach ( $movies[0] as $movie ) {

			// Getting GEnres.
			preg_match_all( '/<span>([\w\s]+?)<\/span>/si',
				Movie::tag( $movie, 'div', 'genres', false ), $genres );

			// Getting Actors.
			preg_match_all( '/<a href="\/person\/(.*?)">(.*?)<\/a>/si', $movie, $actores );

			$runtime = explode( "\n", Movie::tag( $movie, 'div', 'runtime', true ) );
			$dates   = explode( "\n", Movie::tag( $movie, 'td', 'date_wrapper', true ) );
			$post    = array(
				'post_type'    => $this->post_type,
				'post_status'  => 'publish',
				'post_title'   => Movie::tag( $movie, 'div', 'title', true ),
				'post_content' => Movie::tag( $movie, 'div', 'summary', true ),
				'post_date'    => date( 'Y-m-d', strtotime( array_shift( $dates ) ) ),

				// Post Meta Fields.
				'meta_input'   => array(
					'runtime' => trim( array_pop( $runtime ) ),
				),
			);

			$post_ID = wp_insert_post( $post );

			// WordPress wouldn't allow us to create taxonomies in normal way
			// (by providing tax_input), so we need to create it manually.
			wp_set_post_terms( $post_ID, $genres[1], 'genres-mpte' );
			wp_set_post_terms( $post_ID, array_combine( $actores[1], $actores[2] ), 'actors-mpte' );
		}
	}


	/**
	 * Helper method to parse some html and get something back.
	 *
	 * @param string  $html         Heystack for searching.
	 * @param string  $tag          HTML Tag.
	 * @param string  $class        CSS inline class.
	 * @param boolean $strip_tags   Shoudl we strip it or not.
	 * @return string
	 */
	private static function tag( string $html, string $tag, string $class, bool $strip_tags ) : string {

		$pattern = sprintf( '/<%1$s class="%2$s">(.*?)<\/%1$s>/si', $tag, $class );

		if ( preg_match( $pattern, $html, $m ) ) {
			return $strip_tags ? trim( strip_tags( $m[1] ) ) : $m[1];
		}

		return ''; // Fail =(.
	}



	/*******************************************************************************************
	 * Post Type and supported Taxonomies Registration
	 ******************************************************************************************/

	/**
	 * Register Movie Post Type.
	 *
	 * @return void
	 */
	public function register_movie_post_types() {
		register_post_type( $this->post_type, $this->movie() );
	}


	/**
	 * Register Movie Taxonomies.
	 *
	 * @return void
	 */
	public function register_movie_taxonomies() {
		register_taxonomy( 'genres-mpte', $this->post_type, $this->taxonomy( 'genres' ) );
		register_taxonomy( 'actors-mpte', $this->post_type, $this->taxonomy( 'actors' ) );
	}

	/**
	 * Generate Taxonomy Template.
	 *
	 * @param string $key Taxonomy Key.
	 */
	private function taxonomy( string $key ) {
		global $wp_rewrite;

		return array(
			'hierarchical'          => false,
			'label'                 => ucfirst( $key ),
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => $key,
			'rewrite'               => array( 'slug' => $key ),
		);
	}

	/**
	 * Return Lables
	 *
	 * @return array
	 */
	private function moview_labels() : array {
		return array(
			'name'               => _x( 'Movies', 'post type general name', 'textdomain' ),
			'singular_name'      => _x( 'Movie', 'post type singular name', 'textdomain' ),
			'menu_name'          => _x( 'Movies', 'admin menu', 'textdomain' ),
			'name_admin_bar'     => _x( 'Movie', 'add new on admin bar', 'textdomain' ),
			'add_new'            => _x( 'Add New', 'Movie', 'textdomain' ),
			'add_new_item'       => __( 'Add New Movie', 'textdomain' ),
			'new_item'           => __( 'New Movie', 'textdomain' ),
			'edit_item'          => __( 'Edit Movie', 'textdomain' ),
			'view_item'          => __( 'View Movie', 'textdomain' ),
			'all_items'          => __( 'All Movies', 'textdomain' ),
			'search_items'       => __( 'Search Movies', 'textdomain' ),
			'not_found'          => __( 'No Movies found.', 'textdomain' ),
			'not_found_in_trash' => __( 'No Movies found in Trash.', 'textdomain' ),
		);
	}

	/**
	 * Return array of the Post Type attributes
	 *
	 * @return array
	 */
	private function movie() {
		return array(
			'labels'             => $this->moview_labels(),
			'description'        => __( 'Description.', 'textdomain' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,

			// If true, post type alias will be used.
			// If string, will be used as query_var.
			'query_var'          => $this->post_type,

			// https://codex.wordpress.org/Function_Reference/register_post_type#rewrite
			// read url above carefully.
			'rewrite'            => array(
				'slug'    => 'movies',
				'pages'   => true,
				'feeds'   => false,
				'ep_mask' => EP_NONE,
			),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'thumbnail' ),
		);
	}
}

new Movie();
