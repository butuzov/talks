<?php
/**
 * Plugin Name: Rewrite Rules Reset Example
 * Plugin URI:
 * Description: Provides UI to Abandone some of the rewrite rules.
 * Author: Oleg Butuzov
 * Author URI: Oleg Butuzov
 * Version: 0.1
 *
 * @package : WP_Rewrite_Urls_Examples / Rewrite Rules Reset Example
 **/

register_activation_hook( __FILE__, function() {

	// Immitating init callback thathasn't happend.
	$rrre = new ResetRewriteRulesExample();
	$rrre->init();

	flush_rewrite_rules();
});

// Deactivate hook isn't stable. Its intented to work 100%
// all of the time, and it only cover cases of link activate,
// but not any other deactivate method.
register_deactivation_hook( __FILE__, function() {
	flush_rewrite_rules();
});

/**
 * This is class for simple management of Rewrite Rules and simple/easy
 * removing of redundand WP rewrite rules from it.
 *
 * Use this tool just as example of how its can be done.
 */
class ResetRewriteRulesExample {

	/**
	 * We going to store some filters we can use here.
	 *
	 * @var array
	 */
	public $filters = array();

	/**
	 * Restrictions are selected filters element we
	 * using to delete/reset rewrites rules for.
	 *
	 * @var array
	 */
	public $restrictions = array();

	/**
	 *  ResetRewriteRulesExample class constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'admin_init', [ $this, 'init_admin' ] );
	}

	/**
	 * WordPress `init` Action Hook callback.
	 *
	 * @return void
	 */
	public function init() {
		global $pagenow;

		$this->filters      = $this->get_filters();
		$this->restrictions = get_option( 'rewrite_rules_resets', array() );

		// We not going to setup rewrite rule filters in case if
		// we edactivating plugin or we are at Pemalink options page.

		$not_options_page = ! isset( $pagenow ) || 'options-permalink.php' !== $pagenow;

		// see activate/deactivate notes.
		$not_deactivation = ! ( filter_input( INPUT_GET, 'action' ) === 'deactivate' &&
							plugin_basename( __FILE__ ) === filter_input( INPUT_GET, 'plugin' ) );

		if ( $not_options_page && $not_deactivation ) {
			$this->setup_rewrite_rules_restrcitions();
		}

	}

	/**
	 * Main Handler that apply selected rules reset.
	 */
	public function setup_rewrite_rules_restrcitions() {

		if ( empty( $this->restrictions ) ) {
			return;
		}

		$callback = function( $key ) {
			// Callback will return all items except ones it thinks
			// that match to requested pattern.
			return function( $rules ) use ( $key ) {
				$new_rules = array();
				foreach ( $rules as $rule => $match ) {
					if ( strpos( $rule, $key ) !== false ||
						strpos( $match, $key ) !== false ) {
						continue;
					}
					$new_rules[ $rule ] = $match;
				}
				return $new_rules;
			};
		};

		// Special cases will get a custom simple trackback,
		// but native filters are got to get a empty array on
		// filter.
		$special = array_slice( $this->filters, count( $this->filters ) - 6 );

		foreach ( $this->restrictions as $restricted => $__ ) {
			if ( in_array( $restricted, $special, true ) ) {
				add_filter( 'rewrite_rules_array', $callback( $restricted ) );
				continue;
			}
			add_filter( "{$restricted}_rewrite_rules", '__return_empty_array' );
		}

	}

	/**
	 * WordPress `admin_init` Action Hook callback.
	 *
	 * It's creating a UI in Permalink section, handles settings save etc.
	 *
	 * @return void
	 */
	public function init_admin() {
		add_action( 'load-options-permalink.php', [ $this, 'settings_create' ] );
		add_action( 'load-options-permalink.php', [ $this, 'settings_handle' ] );
	}

	/**
	 * Creates UI section in Permalinks Settings.
	 *
	 * @return void
	 */
	public function settings_create() {
		// Creating Own Section at Permalinks.
		add_settings_section(
			'reset_rewrite_rules_ui',
			'Remove next sections from rewrite rules generation',
			function() {
				echo '<p>', 'Here you can disable some of the default rewrite rules. ',
					'It is suggested to use <a href="https://wordpress.org/plugins/tags/rewrite-rules/">some plugins</a> to see rewrite rules, in order to see change.',
					'</p>';
			},
			'permalink'
		);

		// One callback to be used to display each field
		// input checlbox.
		$callback = function( $id ) {

			return function() use ( $id ) {
				$input = sprintf(
					'<input name="reset_rr[%1$s]" type="checkbox" id="reset_rr_%1$s" value="1" %2$s  />', $id, checked( true, isset( $this->restrictions[ $id ] ), false )
				);
				printf( "<label for='reset_rr_%s'>%s</label>", $id, $input ); // WP_CS: xss ok.
			};
		};

		// Adding settings field.
		foreach ( $this->filters as $item ) {
			add_settings_field(
				sprintf( 'reset_rewrite_rules[%s]', $item ),
				ucfirst( str_replace( '_', ' ', $item ) ),
				$callback( $item ),
				'permalink',
				'reset_rewrite_rules_ui'
			);
		}
	}

	/**
	 * Saving and updating out restrictions, array of keys we use to determine
	 * what rewrite rules we suppose to abandone.
	 *
	 * @return void
	 */
	public function settings_handle() {
		$method = 'NONE';
		if ( isset( $_SERVER['REQUEST_METHOD'] ) ) {            // WP_CS : Input var okay.
			$method = wp_unslash( $_SERVER['REQUEST_METHOD'] ); // WP_CS : Input var okay; sanitization okay.
		}

		if ( 'POST' === $method && check_admin_referer( 'update-permalink' ) ) {
			$this->restrictions = filter_input( INPUT_POST, 'reset_rr', FILTER_VALIDATE_INT, array(
				'flags'   => FILTER_REQUIRE_ARRAY,
				'options' => array(
					'min_range' => 1,
					'max_range' => 1,
				),
			) );

			$this->restrictions = array_filter( $this->restrictions, function( $item ) {
				return in_array( $item, $this->filters, true );
			}, ARRAY_FILTER_USE_KEY );

			update_option( 'rewrite_rules_resets', $this->restrictions );
		}

		$this->setup_rewrite_rules_restrcitions();
	}

	/**
	 * Return filters that can be used to reset rewrite rules functionality.
	 *
	 * @return array
	 */
	private function get_filters() {
		global $wp_rewrite;

		$rules = [];

		// List of native filters.
		$native = array(
			'post'     => 'post',
			'date'     => 'date',
			'root'     => 'root',
			'comments' => 'comments',
			'search'   => 'search',
			'author'   => 'author',
			'page'     => 'page',
			'tag'      => 'post_tag', // This structure looks like this just becouse this element.
		);

		// Populating additional permastructes (additionaly registread post type etc).
		foreach ( array_keys( $wp_rewrite->extra_permastructs ) as $name ) {
			if ( ! in_array( $name, $native, true ) ) {
				$rules[] = $name;
			}
		}

		// Additional loop for native permanent structure.
		foreach ( array_keys( $native )  as $name ) {
			$rules[] = $name;
		}

		// This is special rules to remove, and we use a simple filter for them.
		$rules[] = 'feed';
		$rules[] = 'attachment';
		$rules[] = 'trackback';
		$rules[] = 'comment-page';
		$rules[] = 'embed';
		$rules[] = 'category_name';

		return $rules;
	}
}

// Initiating Sample Plugin Object.
new ResetRewriteRulesExample();
