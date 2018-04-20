<?php
/**
 * Standalone Taxonomy Terms functionality.
 *
 * @category    WordPress_Plugin
 * @package     Using-Non-Defaults-URIs / Examples / Standalone Terms URLs
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        https://github.com/butuzov/WordPress-Using-Non-Defaults-URIs
 * @copyright   2018 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Plugin main class.
 */
class StandaloneTerms {

	/**
	 *  List of supported Taxonomies.
	 *
	 * @var array
	 */
	private $taxonomies = [];

	/**
	 * Singleton.
	 *
	 * @var StandaloneTerms
	 */
	private static $_instance = null;

	/**
	 * Class Private Constructor.
	 */
	private function __construct() {
		add_action( 'init', [ $this, 'init' ], 100 );
	}

	/**
	 * WordPress "init" action-hook.
	 *
	 * @return void
	 */
	public function init() {

		// Page dropdown for supported taxonomies.
		foreach ( $this->get_taxonomies() as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", [ $this, 'add_form_fields' ], 10, 2 );
			add_filter( "{$taxonomy}_rewrite_rules", function( $rewrites ) use ( $taxonomy ) {
				return $this->rewrite_rules( $taxonomy, $rewrites );
			});
		}

		// Link generation.
		add_filter( 'term_link', [ $this, 'term_link' ], 10, 3 );

		// Edit fields.
		add_action( 'edit_term', [ $this, 'edit_term' ], 10, 3 );
		add_action( 'delete_term', [ $this, 'delete_term' ], 10, 3 );
		add_action( 'save_post_page', [ $this, 'save_post_page' ], 10, 3 );
	}

	/**
	 * Generate Proper link term.
	 *
	 * @param string $termlink Term link URL.
	 * @param object $term     Term object.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string
	 */
	public function term_link( $termlink, $term, $taxonomy ) {
		if ( ! in_array( $taxonomy, $this->get_taxonomies(), true ) ) {
			return $termlink;
		}

		global $wp_rewrite;

		$parent = get_term_meta( $term->term_id, 'landing', true );
		if ( empty( $parent ) ) {
			return $termlink;
		}

		$base     = get_term_meta( $term->term_id, 'landing_url', true ) . '/';
		$slug     = $term->slug;
		$trailing = $wp_rewrite->use_trailing_slashes ? '/' : '';

		return home_url( $base . $term->slug . $trailing );
	}

	/**
	 * Forming additional rules for $taxonomy.
	 *
	 * @param string $taxonomy         Taxonomy Name.
	 * @param array  $rewrite_rules    Rewrite Rules Array.
	 * @return array
	 */
	public function rewrite_rules( $taxonomy, $rewrite_rules ) : array {
		global $wpdb;

		// Do we have this rules?
		$terms = ( new WP_Term_Query([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'meta_key'   => 'landing_url',
			'fields'     => 'id=>slug',
		] ) )->get_terms();

		if ( empty( $terms ) ) {
			return $rewrite_rules;
		}

		// SELECT all meta values for terms matched out previous request.
		$query = "SELECT meta_value, GROUP_CONCAT(term_id) as terms
					FROM {$wpdb->termmeta}
					WHERE term_id IN (%s) and meta_key = 'landing_url'
					GROUP BY `meta_value` ";

		$query = sprintf( $query, implode( ',', array_keys( $terms ) ) );

		$results = $wpdb->get_results( $query ); // WP-CS: db call ok, cache ok, unprepared SQL.

		if ( empty( $results ) ) {
			return $rewrite_rules;
		}

		// Generating Rules keys.
		$rules = array_map( function( $item ) use ( $terms ) {

			// Making Base.
			$base = empty( $item->meta_value ) ? '' : $item->meta_value . '/';

			// Working With terms.
			$items = explode( ',', $item->terms );
			foreach ( $items as $k => $term ) {
				$items[ $k ] = $terms[ $term ];
			}

			// You can additionaly sort terms in order to avoid
			// aa|aaa|aaaa gotches of regular expressions.
			return sprintf( '%s(%s)/?$', $base, implode( '|', $items ) );
		}, $results);

		// Only one match rule.
		$matched_rule = sprintf( 'index.php?%s=$matches[1]', get_taxonomy( $taxonomy )->query_var );

		// New Rules.
		$new_rules = array_combine( $rules, array_fill( 0, count( $rules ), $matched_rule ) );

		// Merging both arrays.
		return array_merge( $new_rules, $rewrite_rules );
	}

	/**
	 * Drops Root for term.
	 *
	 * @param int $term_id      Term ID.
	 * @return void
	 */
	private function delete_term_root( $term_id ) {
		delete_term_meta( $term_id, 'landing_url' );
		if ( true === delete_term_meta( $term_id, 'landing' ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Updates term root.
	 *
	 * @param int $term_id      Term ID.
	 * @param int $page_id      Page ID.
	 * @return void
	 */
	private function update_term_root( $term_id, $page_id ) {
		$link = str_replace( get_option( 'home' ), '', get_permalink( intval( $page_id ) ) );

		update_term_meta( $term_id, 'landing', intval( $page_id ) );
		$url_result = update_term_meta( $term_id, 'landing_url', trim( $link, '/' ) );

		if ( ! is_wp_error( $url_result ) && (int) $url_result > 0 ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Updates parent page URI in terms table.
	 *
	 * WARNING - Example dosn't provide solution if any of the parent pages was updated,
	 * it's rather complex issue that require a bit more functionality.
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	 */
	public function save_post_page( $post_ID, $post, $update ) {
		global $wpdb;

		$query = "SELECT term_id FROM {$wpdb->termmeta} WHERE CAST(meta_value AS UNSIGNED) = %d";
		$terms = $wpdb->get_col( $wpdb->prepare( $query, $post_ID ) ); // WP-CS: db call ok, cache ok, unprepared SQL.

		if ( empty( $terms ) ) {
			return;
		}

		// Casting data to ints.
		$link = trim( str_replace( get_option( 'home' ), '', get_permalink( $post_ID ) ), '/' );

		// SQL Query placeholder.
		$update_placeholder_query = "UPDATE {$wpdb->termmeta} SET `meta_value` = '%s' WHERE `term_id` IN ( %s ) AND  `meta_key` = 'landing_url' ";

		$wpdb->query( sprintf( $update_placeholder_query,
			esc_sql( $link ), implode( ',', array_map( 'intval', $terms ) ) ) ); // WP-CS: db call ok, cache ok, unprepared SQL.

		flush_rewrite_rules();
	}

	/**
	 * Clean up Functionality.
	 *
	 * @param int    $term_id      Term ID.
	 * @param int    $tt_id        Term taxonomy ID.
	 * @param string $taxonomy     Taxonomy name.
	 * @return void
	 */
	public function delete_term( $term_id, $tt_id, $taxonomy ) {

		if ( ! in_array( $taxonomy, $this->get_taxonomies(), true ) ) {
			return;
		}

		$this->delete_term_root( $term_id );
	}

	/**
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function edit_term( $term_id, $tt_id, $taxonomy ) {

		if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) ) {
			return;
		}

		if ( ! in_array( $taxonomy, $this->get_taxonomies(), true ) ) {
			return;
		}

		$page_prev = get_term_meta( $term_id, 'landing', true );
		$page_next = filter_input( INPUT_POST, 'landing', FILTER_SANITIZE_NUMBER_INT );
		if ( $page_prev === $page_next ) {
			return;
		}

		// Case 1 - None Selected.
		if ( -1 === (int) $page_next ) {
			$this->delete_term_root( $term_id );
			return;
		}

		// Case 2 & 3 - Website Root & Actual Page.
		$this->update_term_root( $term_id, (int) $page_next );
	}

	/**
	 * Add term fields for selected taxonomy.
	 *
	 * @param WP_Term $term     Term object.
	 * @param string  $taxonomy Taxonomy name.
	 * @return void
	 */
	public function add_form_fields( $term, $taxonomy ) {

		$dropdown = wp_dropdown_pages( [
			'id'                    => 'landing',
			'name'                  => 'landing',
			'echo'                  => false,
			'show_option_no_change' => 'None',
			'show_option_none'      => 'WordPress Root',
			'option_none_value'     => '0',
			'selected'              => get_term_meta( $term->term_id, 'landing', true ),
		] );

		// WordPress doesn't provide way to preselect None value option.
		if ( '0' === get_term_meta( $term->term_id, 'landing', true ) ) {
			$dropdown = str_replace( 'value="0"', 'value="0" selected', $dropdown );
		}

		echo // WP-CS XSS OK.
			'<tr>',
				'<th scope="row">',
					'<label for="landing">',
						__( 'Term Landing Page', 'domain' ),
					'</label>',
				'</th>',
				'<td>',
				$dropdown,
				'</td>',
			'</tr>';
	}

	/**
	 * GetInstance of "StandaloneTerms" Singleton
	 *
	 * @return void
	 */
	public static function getInstance() : StandaloneTerms {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Register Taxonomy Callback.
	 *
	 * @param string $taxonomy_name      Taxonomy name.
	 * @return void
	 */
	public function register_taxonomy( $taxonomy_name ) {

		if ( false === ( $taxonomy = get_taxonomy( $taxonomy_name ) ) ) {
			$message = sprintf( 'Taxonomy `%s` can\'t be registread because its not available.', $taxonomy_name );
			trigger_error( $message );
			return;
		}

		$taxonomy = get_taxonomy( $taxonomy_name );

		if ( ! $taxonomy->public || ! $taxonomy->publicly_queryable ) {
			$message = sprintf( 'Taxonomy `%s` can\'t be registread because its not publicly available.', $taxonomy_name );
			trigger_error( $message );
			return;
		}

		if ( in_array( $taxonomy_name, $this->get_taxonomies(), true ) ) {
			return;
		}

		$this->add_taxonomy( $taxonomy_name );
	}

	/**
	 * Deletes taxonomy from list fo supported taxonomies if found.
	 *
	 * @param string $taxonomy_name Taxonomy name.
	 * @return void
	 */
	public function unregister_taxonomy( $taxonomy_name ) {
		if ( ! in_array( $taxonomy_name, $this->taxonomies(), true ) ) {
			return;
		}

		$this->remove_taxonomy( $taxonomy_name );
	}

	/**
	 * Getter for supported taxonomies.
	 *
	 * @return array
	 */
	private function get_taxonomies() : array {
		return $this->taxonomies;
	}

	/**
	 * Adds taxonomy name to array of supported taxonomies.
	 *
	 * @param string $taxonomy_name      Taxonomy Name.
	 * @return void
	 */
	private function add_taxonomy( $taxonomy_name ) {
		$this->taxonomies[] = $taxonomy_name;
	}

	/**
	 * Removes taxonomy from list of supported taxonomies.
	 *
	 * @param string $taxonomy_name      Taxonomy name.
	 * @return void
	 */
	private function remove_taxonomy( $taxonomy_name ) {
		if ( ! in_array( $taxonomy_name, $this->get_taxonomies(), true ) ) {
			return;
		}
		unset( $this->taxonomies[ array_search( $taxonomy_name, $this->get_taxonomies(), true ) ] );
	}
}
