<?php
/**
 * PerPage_Handler
 *
 * @category    WordPress_Plugin
 * @package     Using-Non-Defaults-URIs / Examples / Custom Per Page Number
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        https://github.com/butuzov/talks/tree/master/2018-wp-meetup-kyiv
 * @copyright   2018 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * PerPageSetting responsibilities:
 *
 * - Provide UI to change perpage settings.
 * - Handle saving.
 * - Change rewrite rules for particular post types
 */
class PerPage_Handler {


	/**
	 * Post Type we are workign with.
	 *
	 * @var string
	 */
	private $type;


	/**
	 * Post Type title.
	 *
	 * @var string
	 */
	private $title;


	/**
	 * Default Perpage setting.
	 *
	 * @var string
	 */
	private $perpage = 'default';


	/**
	 * Posts Per Page for Custom paging.
	 *
	 * @var int
	 */
	private $posts_per_page;


	/**
	 * Create Instance for PerPage Manager.
	 *
	 * @param string   $post_type  Post Type alias id.
	 * @param stdClass $settings   Object of settings passed to handler class.
	 */
	public function __construct( string $post_type, stdClass $settings ) {

		// Settings...

		$post_type_response = get_post_type_object( $post_type );

		if ( is_wp_error( $post_type_response ) ) {
			return;
		}

		if ( ! empty( $settings->type ) ) {
			$this->set_type_per_page( $settings->type );
		}

		if ( ! empty( $settings->number ) ) {
			$this->set_posts_per_page( $settings->number );
		}

		$this->type  = $post_type;
		$this->title = $post_type_response->label;

		// Hooks...
		add_action( 'init', [ $this, 'init' ], 1001 );
	}


	/**
	 * Perpage type getter.
	 *
	 * @return string
	 */
	public function get_type_per_page() : string {
		return $this->perpage;
	}

	/**
	 * Perpage type setter.
	 *
	 * @param  string $method  Perpage [method all, custom or default].
	 * @return boolean
	 */
	private function set_type_per_page( string $method ) : bool {
		if ( ! in_array( $method, [ 'default', 'all', 'custom' ], true ) ) {
			return false;
		}

		$this->perpage = $method;
		return true;
	}

	/**
	 * Perpage type getter.
	 *
	 * @return int
	 */
	public function get_posts_per_page() : int {
		if ( (int) $this->posts_per_page > 0 ) {
			return (int) $this->posts_per_page;
		}
		return (int) get_option( 'posts_per_page' );
	}

	/**
	 * Posts Per Page number setter.
	 *
	 * @param  int $number  Number of posts per page.
	 * @return boolean
	 */
	private function set_posts_per_page( int $number ) : bool {
		if ( 'custom' !== $this->get_type_per_page() ) {
			return false;
		}

		// 999 - maximum allowed posts per page setting in admin
		// but even 200 per page, not suggested to use without cache.
		if ( $number <= 0 || $number >= 999 ) {
			return false;
		}

		$this->posts_per_page = $number;
		return true;
	}

	/**
	 * WordPress default `init` action hook
	 *
	 * Adds admin menu hook and rewrite rules for this post type.
	 *
	 * @return void
	 */
	public function init() {

		// Admin menu handler.
		add_action( 'admin_menu', [ $this, 'menu' ] );

		// Handles special Rewrite rules for post type.
		add_filter( 'rewrite_rules_array', [ $this, 'rewrite_rules' ] );
	}
	/**
	 * WordPress handler for `admin_menu` action hook.
	 *
	 * @return void
	 */
	public function menu() {

		// Initiate View.
		$hook = add_submenu_page(
					sprintf( 'edit.php?post_type=%s', $this->type ),
					'Per Page Settings',
					'Per Page Settings',
					'manage_options',
					sprintf( '%s_perpage_settings', $this->type ),
					[ $this, 'view' ]
		);

		// Initiate Controller response / Load Callback.
		add_action( sprintf( 'load-%s', $hook ), [ $this, 'controller' ] );
	}


	/**
	 * Page View Handler.
	 *
	 * @return void
	 */
	public function view() {
		?>
		<div class="wrap">
			<h2><?php echo $this->title; // WP_CS: xss ok. ?> (Posts Per Page) Setting</h2>
			<p>This setting allows you to choose some custom perpage settings for post type</p>
			<form method="post">
			<?php wp_nonce_field( 'perpager', '_wpnonce', false ); ?>
			<ul>
				<li>
					<label>
						<input type="radio" name="type_per_page" value="default" <?php checked( $this->get_type_per_page(), 'default' ); ?> /> Default
					</label>
				</li>
				<li>
					<label>
						<input type="radio" name="type_per_page" value="all" <?php checked( $this->get_type_per_page(), 'all' ); ?> /> All Posts On Same Page
					</label>
				</li>
				<li>
					<label>
						<input type="radio" name="type_per_page" value="custom" <?php checked( $this->get_type_per_page(), 'custom' ); ?> /> Custom Posts Per Page Number <br />
						<input name="posts_per_page" type="number" min="1" max="999" value="<?php echo $this->get_posts_per_page(); // WP_CS: xss ok. ?>" class="regular-text">
					</label>
				</li>
			</ul>
			<?php submit_button( 'Submit' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handles 'load-$hook' action on settings page.
	 *
	 * @return void
	 */
	public function controller() {

		// I am not using filter var here due a reason i am using dockerized nginx + php-fpm
		// and can't access any of SERVER vars via filter_input function.
		$method = 'NONE';
		if ( isset( $_SERVER['REQUEST_METHOD'] ) ) {            // WP_CS : Input var okay.
			$method = wp_unslash( $_SERVER['REQUEST_METHOD'] ); // WP_CS : Input var okay; sanitization okay.
		}

		if ( 'POST' === $method && check_admin_referer( 'perpager', '_wpnonce' ) ) {

			$this->set_type_per_page( filter_input( INPUT_POST, 'type_per_page' ) );
			$this->set_posts_per_page( (int) filter_input( INPUT_POST, 'posts_per_page' ) );

			flush_rewrite_rules();

			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>Settings Updated!</p></div>';
			});
		}
	}

	/**
	 * Rewrite Rules Filter based on Settings.
	 *
	 * @param  array $rewrite_rules Incoming Rewrite Rules for a post type.
	 * @return array
	 */
	public function rewrite_rules( array $rewrite_rules ) : array {

		if ( 'all' !== $this->get_type_per_page() ) {
			return $rewrite_rules;
		}

		$new_rewrite_rules = [];

		// Serching for this pattern.
		$sp = sprintf( 'post_type=%s', $this->type );

		foreach ( $rewrite_rules as $rule => $query ) {

			if ( strpos( $query, $sp ) === false ) {
				$new_rewrite_rules[ $rule ] = $query;
				continue;
			}

			// This commented code will make paged links impossible to work.
			// So you would have only landing page for posts and not much more.

			// $rule = preg_replace( '/\/page\/.*?\)/si', '', $rule );

			// This is active code but, if google will visit any of paged urls
			// all of them will generate full list of posts. Don't do that.
			// Do redirection.
			if ( false === strpos( $query, 'paged=' ) ) {
				$query .= '&sp=1';
			} else {
				$query = preg_replace( '/\&paged=\\$matches\[\d{1,}\]/si', '&sp=1', $query );
			}

			$new_rewrite_rules[ $rule ] = $query;
		}

		return $new_rewrite_rules;
	}
}
