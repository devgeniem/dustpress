<?php
/*
Plugin Name: DustPress
Plugin URI: http://www.geniem.com
Description: Dust.js templating system for WordPress
Author: Miika Arponen & Ville Siltala / Geniem Oy
Author URI: http://www.geniem.com
License: GPLv3
Version: 1.22.1
*/

final class DustPress {

	// Singleton DustPress instance
	private static $instance;

	// Instance of DustPHP
	public $dust;

	// Main model
	private $model;

	// This is where the data will be stored
	private $data;

	// DustPress settings
	private $settings;

	// Is DustPress disabled?
	public $disabled;

	// Paths for locating files
	private $paths;

	// Paths for template files
	private $templates;

    // Are we on an activation page
    private $activate;

	// Registered custom ajax functions
	private $ajax_functions;

	// Custom routes
	private $custom_routes = [];

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
            self::$instance = new DustPress();
        }
        return self::$instance;
	}

	/**
	*  Constructor for DustPress core class.
	*
	*  @type	function
	*  @date	10/8/2015
	*  @since	0.2.0
	*
	*  @param	N/A
	*  @return	N/A
	*/

	protected function __construct() {
		// Autoload paths will be stored here so the filesystem has to be scanned only once.
		$this->autoload_paths = [];

		$this->register_autoloaders();

		// Create a DustPHP instance
		$this->dust = new Dust\Dust();

		// Dust template paths will be stored here so the filesystem has to be scanned only once.
		$this->templates = [];

		$this->add_theme_paths();
		$this->add_core_paths();

        // Find and include Dust helpers from DustPress plugin
        $paths = [
            __DIR__ . '/helpers',
        ];

        foreach( $paths as $path ) {
            if ( is_readable( $path ) ) {
                foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ) ) as $file ) {
                    if ( is_readable( $file ) && '.php' === substr( $file, -4, 4 ) ) {
                        require_once( $file );
                    }
                }
            }
        }

		// Add create_instance to right action hook if we are not on the admin side
		if ( $this->want_autoload() ) {
			add_filter( 'template_include', [ $this, 'create_instance' ] );

			// A fix to prevent the activation feature from causing bugs with newer WordPress versions.
			if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
				// If we are on wp-activate.php hook into activate_header
				if ( strpos( $_SERVER['REQUEST_URI'], 'wp-activate.php' ) !== false ) {
					$this->activate = true;
					// Run create_instance for use partial and model
					add_action( 'activate_header', [ $this, 'create_instance' ] );
					// Kill original wp-activate.php execution
					add_action( 'activate_header', function() {
						die();
					});
				}
			}
		}
		else if ( $this->is_dustpress_ajax() ) {
			$this->parse_request_data();

			// Optimize the run if we don't want to run the main WP_Query at all.
			if ( ( is_object( $this->request_data ) && ! empty( $this->request_data->bypassMainQuery ) ) ||
			     ( is_array( $this->request_data ) && ! empty( $this->request_data['bypassMainQuery'] ) ) ) {
				
				// DustPress.js request is never 404.
				add_filter( 'status_header', function( $status, $header ) {
					return 'status: 200';
				}, 10, 2 );

				// Make the main query to be as fast as possible within DustPress.js requests.
				add_filter( 'posts_request', function( $request, \WP_Query $query ) {
					// Target main home query
					if ( $query->is_main_query() ) {
						$request = str_replace( '1=1', '0=1', $request );
					}

					return $request;    

				}, PHP_INT_MAX, 2 );

				// Populate the main query posts variable with a dummy post to prevent errors and notices.
				add_filter( 'posts_pre_query', function( $posts, \WP_Query $query ) {
					if( $query->is_main_query() ){
						$posts = [ new WP_Post( (object) [] ) ];
						$query->found_posts = 1;
					}

					return $posts;
				}, 10, 2 );
			}

			add_filter( 'template_include', [ $this, 'create_ajax_instance' ] );
		}

		// Initialize settings
		add_action( 'init', [ $this, 'init_settings' ] );

		// Register custom route rewrite tag
		add_action( 'after_setup_theme', [ $this, 'rewrite_tags' ], 20 );

		return;
	}

	/**
	*  Register custom route rewrite tags
	*
	*  @type	function
	*  @date	19/3/2019
	*  @since	1.13.1
	*
	*  @param   N/A
	*  @return	N/A
	*/
	public function rewrite_tags() {
		// Register custom route rewrite tag
		add_rewrite_tag( '%dustpress_custom_route%', '([^\/]+)' );
		add_rewrite_tag( '%dustpress_custom_route_route%', '(.+)' );
		add_rewrite_tag( '%dustpress_custom_route_parameters%', '(.+)' );
		add_rewrite_tag( '%dustpress_custom_route_render%', '(.+)' );
	}

	/**
	*  This function creates the instance of the main model that is defined by the WordPress template
	*
	*  @type	function
	*  @date	19/3/2015
	*  @since	0.0.1
	*
	*  @param   N/A
	*  @return	N/A
	*/
	public function create_instance() {
		global $post, $wp_query;

        // Initialize an array for debugging.
        $debugs = [];

        if ( ! $this->activate ) {
            if ( is_object( $post ) && isset( $post->ID ) ) {
                $post_id = $post->ID;
            }
            else {
                $post_id = null;
            }

            // Filter for wanted post ID
            $new_post = apply_filters( 'dustpress/router', $post_id );

            // If developer wanted a post by post ID.
            if ( $new_post !== $post_id ) {
                $post = get_post( $new_post );

                setup_postdata( $post );
            }


            // Get current template name tidied up a bit.
            $template = $this->get_template_filename( $debugs );
        }
        else {
            // Use user-activate.php and user-activate.dust to replace wp-activate.php
            $template = apply_filters( 'dustpress/template/useractivate', 'UserActivate' );
            $debugs[] = $template;
            // Prevent 404 on multisite sub pages.
            $wp_query->is_404 = false;
		}

		$custom_route_args       = [];
		$custom_route            = $wp_query->get( 'dustpress_custom_route' );
		$custom_route_parameters = $wp_query->get( 'dustpress_custom_route_parameters' );

		// Handle registered DustPress custom routes
		if ( ! empty( $custom_route ) ) {
			$template = $custom_route;

			$custom_route_args[ 'route' ]  = $wp_query->get( 'dustpress_custom_route_route' );
			$custom_route_args[ 'render' ] = $wp_query->get( 'dustpress_custom_route_render' );

			if ( ! empty( $custom_route_parameters ) ) {
				$custom_route_args['params'] = explode( DIRECTORY_SEPARATOR, $custom_route_parameters );
			}
		}

		$template = apply_filters( 'dustpress/template', $template, $custom_route_args );

		if ( ! defined( 'DOING_AJAX' ) && ! $this->disabled ) {
			// If class exists with the template's name, create new instance with it.
			// We do not throw error if the class does not exist, to ensure that you can still create
			// templates in traditional style if needed.
			if ( class_exists ( $template ) ) {
				$this->model = new $template( $custom_route_args );

				$this->model->fetch_data();

				if ( $this->model->get_terminated() ) {
					return;
				}

				do_action( 'dustpress/model_list', array_keys( (array) $this->model->get_submodels() ) );

				$template_override = $this->model->get_template();

				$partial = $template_override ? $template_override : strtolower( $this->camelcase_to_dashed( $template ) );

				$type = $custom_route_args['render'] ?: 'default';

				$this->render([
					'partial' => $partial,
					'main' => true,
					'type' => $type
				]);
			}
			else {
				die( 'DustPress error: No suitable model found. One of these is required: '. implode( ', ', $debugs ) );
			}
		}
	}

	/**
	 * This function returns the model name of current custom route, or false if we are not on a custom route.
	 *
	 *  @type	function
	 *  @date	8/1/2019
	 *  @since	1.20.0
	 *
	 *  @param	N/A
	 *  @return	string|boolean
	 */
	public function get_custom_route() {
		global $wp_query;

		$custom_route = $wp_query->get( 'dustpress_custom_route' );

		if ( ! empty( $custom_route ) ) {
			if ( isset( $this->custom_routes[ $custom_route ] ) ) {
				return [
					'template' => $custom_route,
					'route'    => $this->custom_routes[ $custom_route ],
				];
			}
		}

		return false;
	}

	/**
	*  This function gets current template's filename and returns without extension or WP-template prefixes such as page- or single-.
	*
	*  @type	function
	*  @date	19/3/2015
	*  @since	0.0.1
	*
	*  @param	N/A
	*  @return	$filename (string)
	*/

	private function get_template_filename( &$debugs = array() ) {
		global $post;

		if ( is_object( $post ) && isset( $post->ID ) ) {
			$page_template = get_post_meta( $post->ID, '_wp_page_template', true );

			if ( $page_template ) {
				$array = explode( DIRECTORY_SEPARATOR, $page_template );

				$template = array_pop( $array );

				// strip out .php
				$template = str_replace( '.php', '', $template );

				// strip out page-, single-
				$template = str_replace( 'page-', '', $template );
				$template = str_replace( 'single-', '', $template );

				if ( $template == 'default' ) $template = 'page';
			}
			else {
				$template = 'default';
			}
		}
		else {
			$template = 'default';
		}

		if ( is_front_page() ) {
			$hierarchy = [
				'is_front_page' => [
				    'FrontPage'
				]
			];
		}

		if ( is_home() ) {
			$hierarchy['is_home'] = [
			    'Home'
			];
		}

		if ( is_page() ) {
			$hierarchy[ 'is_page' ] = [
				'Page' . $this->dashed_to_camelcase( $template ),
				'Page' . $this->dashed_to_camelcase( $post->post_name ),
				'Page' . $post->ID,
				'Page'
			];
		}

		if ( is_category() ) {
			$cat = get_category( get_query_var( 'cat' ) );

			$hierarchy[ 'is_category' ] = [
				'Category' . $this->dashed_to_camelcase( $cat->slug ),
				'Category' . $cat->term_id,
				'Category',
				'Archive'
			];
		}

		if ( is_tag() ) {
			$term_id = get_queried_object()->term_id;
			$term = get_term_by( 'id', $term_id, 'post_tag' );

			$hierarchy[ 'is_tag' ] = [
				'Tag' . $this->dashed_to_camelcase( $term->slug ),
				'Tag',
				'Archive'
			];
		}

		if ( is_tax() ) {
			$term_id = get_queried_object()->term_id;
			$term = get_term_by( 'id', $term_id, get_query_var( 'taxonomy' ) );

			$hierarchy[ 'is_tax' ] = [
				'Taxonomy' . $this->dashed_to_camelcase( get_query_var( 'taxonomy' ) ) . $this->dashed_to_camelcase( $term->slug ),
				'Taxonomy' . $this->dashed_to_camelcase( get_query_var( 'taxonomy' ) ),
				'Taxonomy',
				'Archive'
			];
		}

		if ( is_author() ) {
			$author = get_user_by( 'slug', get_query_var( 'author_name' ) );

			$hierarchy[ 'is_author' ] = [
				'Author' . $this->dashed_to_camelcase( $author->user_nicename ),
				'Author' . $author->ID,
				'Author',
				'Archive'
			];
		}

		$hierarchy[ 'is_search' ] = [
			'Search'
		];


		$hierarchy[ 'is_404' ] = [
			'Error404'
		];

		if ( is_attachment() ) {
			$mime_type = get_post_mime_type( get_the_ID() );

			$hiearchy[ 'is_attachment' ] = [
				function() use ( $mime_type ) {
					if ( preg_match( '/^image/', $mime_type ) && class_exists( 'Image' ) ) {
						return 'Image';
					}
					else {
						return false;
					}
				},
				function() use ( $mime_type ) {
					if ( preg_match( '/^video/', $mime_type ) && class_exists( 'Video' ) ) {
						return 'Video';
					}
					else {
						return false;
					}
				},
				function() use ( $mime_type ) {
					if ( preg_match( '/^application/', $mime_type ) && class_exists( 'Application' ) ) {
						return 'Application';
					}
					else {
						return false;
					}
				},
				function() use ( $mime_type ) {
					if ( $mime_type == 'text/plain' ) {
						if ( class_exists( 'Text' ) ) {
							return 'Text';
						}
						else if ( class_exists( 'Plain' ) ) {
							return 'Plain';
						}
						else if ( class_exists( 'TextPlain' ) ) {
							return 'TextPlain';
						}
						else {
							return false;
						}
					}
					else {
						return false;
					}
				},
				'Attachment',
				'SingleAttachment',
				'Single'
			];
		}

		if ( is_single() ) {
			$type = get_post_type();

			$hierarchy[ 'is_single' ] = [
				'Single' . $this->dashed_to_camelcase( $template ),
				'Single' . $this->dashed_to_camelcase( $type ),
				'Single'
			];
		}

		if ( is_archive() ) {
			// Double check just to keep the function structure.
			$hierarchy[ 'is_archive' ] = [
				function() {
					$post_types = get_post_types();

					foreach ( $post_types as $type ) {
						if ( is_post_type_archive( $type ) ) {
							if ( class_exists( 'Archive' . $this->dashed_to_camelcase( $type ) ) ) {
								return 'Archive' . $this->dashed_to_camelcase( $type );
							}
							else if ( class_exists( 'Archive' ) ) {
								return 'Archive';
							}
							else {
								return false;
							}
						}
					}

					return false;
				}
			];
		}

		if ( is_date() ) {

			// Double check just to keep the function structure.
			$hierarchy[ 'is_date' ] = [
				function() {
					if ( class_exists( 'Date' ) ) {
						return 'Date';
					}
					else if ( class_exists( 'Archive' ) ) {
						return 'Archive';
					}
					else {
						return false;
					}
				}
			];
		}

		// I don't think you really want to do this.
		$hierarchy = apply_filters( 'dustpress/template_hierarchy', $hierarchy );

		foreach( $hierarchy as $level => $keys ) {
			if ( true === call_user_func ( $level ) ) {
				foreach( $keys as $key => $value ) {
					if ( is_integer( $key ) ) {
						if ( is_string( $value ) ) {
							$debugs[] = $value;
							if ( class_exists( $value ) ) {
								return $value;
							}
						}
						else if ( is_callable( $value ) ) {
							$value = call_user_func( $value );
							$debugs[] = $value;
							if( is_string( $value ) && class_exists( $value ) ) {
								return $value;
							}
						}
					}
					else if ( is_string( $key ) ) {
						if ( class_exists( $key ) ) {
							if( is_string( $value ) ) {
								$debugs[] = $value;
								if ( class_exists( $value ) ) {
									return $value;
								}
							}
							else if ( is_callable( $value ) ) {
								$debugs[] = $value;
								return call_user_func( $value );
							}
						}
					}
					else if ( true === $key or is_callable( $key ) ) {
						if ( true === call_user_func( $key ) ) {
							if( is_string( $value ) ) {
								$debugs[] = $value;
								if ( class_exists( $value ) ) {
									return $value;
								}
							}
							else if ( is_callable( $value ) ) {
								$debugs[] = $value;
								return call_user_func( $value );
							}
						}
					}
				}
			}
		}

		$debugs[] = 'Index';
		return 'Index';
	}

	/**
	*  This function populates the data collection with essential data
	*
	*  @type	function
	*  @date	17/3/2015
	*  @since	0.0.1
	*
	*  @param	N/A
	*  @return	N/A
	*/
	private function populate_data_collection() {
		$wp_data = array();

		// Insert Wordpress blog info data to collection
		$infos = [
			'name',
			'description',
			'wpurl',
			'url',
			'admin_email',
			'charset',
			'version',
			'html_type',
			'is_rtl',
			'language',
			'stylesheet_url',
			'stylesheet_directory',
			'template_url',
			'template_directory',
			'pingback_url',
			'atom_url',
			'rdf_url',
			'rss_url',
			'rss2_url',
			'comments_atom_url',
			'comments_rss2_url',
			'url'
		];

		if ( $this->is_dustpress_ajax() ) {
			$remove_infos = array( 'wpurl', 'admin_email', 'version', 'user' );

			$remove_infos = apply_filters( 'dustpress/ajax/remove_wp', $remove_infos );

			$infos = array_diff( $infos, $remove_infos );
		}

		foreach ( $infos as $info ) {
			$wp_data[ $info ] = get_bloginfo( $info );
		}

		// Insert user info to collection

		$currentuser = wp_get_current_user();

		if ( 0 === $currentuser->ID ) {
			$wp_data['loggedin'] = false;
		}
		else {
			$wp_data['loggedin'] = true;
			$wp_data['user'] = $currentuser->data;
			$wp_data['user']->roles = $currentuser->roles;
			unset( $wp_data['user']->user_pass );
		}

		// Insert WP title to collection
		ob_start();
		wp_title();
		$wp_data['title'] = ob_get_clean();

		// Insert admin ajax url
		$wp_data['admin_ajax_url'] = admin_url( 'admin-ajax.php' );

		// Insert current page permalink
		$wp_data['permalink'] = get_permalink();

		// Insert body classes
		$wp_data['body_class'] = get_body_class();

		// Return collection after filters
		return apply_filters( 'dustpress/data/wp', $wp_data );
	}

	/**
	*  This function will render the given data in selected format
	*
	*  @type	function
	*  @date	17/3/2015
	*  @since	0.0.1
	*
	*  @param	$partial (string)
	*  @param	$data (N/A)
	*  @param	$type (string)
	*  @return	true/false (boolean)
	*/
	public function render( $args = array() ) {
		global 	$dustpress;
				$hash;

		$defaults = [
			'data' => false,
			'type' => 'default',
			'echo' => true,
			'main' => false,
		];

		if ( is_array( $args ) ) {
			if ( ! isset( $args['partial'] ) ) {
				die( '<p><b>DustPress error:</b> No partial is given to the render function.</p>' );
			}
		}

		$options = array_merge( $defaults, (array) $args );

		extract( $options );

		if ( 'default' == $type && ! get_option( 'dustpress_default_format' ) ) {
			$type = 'html';
		}
		else if ( 'default' == $type && get_option( 'dustpress_default_format' ) ) {
			$type = get_option( 'dustpress_default_format' );
		}

		if ( $this->get_setting( 'json_url' ) && isset( $_GET['json'] ) ) {
			$type = 'json';
		}

		if ( $this->get_setting( 'json_headers' ) && isset( $_SERVER['HTTP_ACCEPT'] ) && $_SERVER['HTTP_ACCEPT'] == 'application/json' ) {
			$type = 'json';
		}

		$types = array(
			'html' => function( $data, $partial, $dust ) {

				try {
					if ( apply_filters( 'dustpress/cache/partials', false ) && apply_filters( 'dustpress/cache/partials/' . $partial, true ) ) {
						if ( ! ( $compiled = wp_cache_get( $partial, 'dustpress/partials' ) ) ) {
							$compiled = $dust->compileFile( $partial );

							wp_cache_set( $partial, $compiled, 'dustpress/partials' );
						}
					}
					else {
						$compiled = $dust->compileFile( $partial );
					}
				}
				catch ( Exception $e ) {
					die( 'DustPress error: '. $e->getMessage() );
				}

				if ( apply_filters( 'dustpress/cache/rendered', false ) && apply_filters( 'dustpress/cache/rendered/' . $partial, true ) ) {
					$data_hash = sha1( serialize( $compiled ) . serialize( $data ) );

					$cache_time = apply_filters( 'dustpress/settings/partial/' . $partial, $this->get_setting( 'rendered_expire_time' ) );

					if ( ! ( $rendered = wp_cache_get( $data_hash, 'dustpress/rendered' ) ) ) {
						$rendered = $dust->renderTemplate( $compiled, $data );

						wp_cache_set( $data_hash, $rendered, 'dustpress/rendered', $cache_time );
					}
				}
				else {
					$rendered = $dust->renderTemplate( $compiled, $data );
				}

				return $rendered;
			},
			'json' => function( $data, $partial, $dust ) {
				try {
					$output = json_encode( $data );
					header( 'Content-Type: application/json' );
				}
				catch ( Exception $e ) {
					die( 'JSON encode error: ' . $e->getMessage() );
				}

				return $output;
			}
		);

		$types = apply_filters( 'dustpress/formats', $types );

		if ( ! $data && ! empty( $this->model ) ) {
			$this->model->data = (array) $this->model->data;

			$this->model->data['WP'] = $this->populate_data_collection();
		}

		// Ensure we have a DustPHP instance.
		if ( isset( $this->dust ) ) {
			$dust = $this->dust;
		}
		else {
			die( 'DustPress error: Something very unexpected happened: there is no DustPHP.' );
		}

		$dust->helpers = apply_filters( 'dustpress/helpers', $dust->helpers );

		// Fetch Dust partial by given name. Throw error if there is something wrong.
		try {
			$template = $this->get_template( $partial );

			$helpers = $this->prerender( $partial );

			$this->prerun_helpers( $helpers );
		}
		catch ( Exception $e ) {
			die( 'DustPress error: '. $e->getMessage() );
		}

		if ( ! empty( $data ) ) {
			$render_data = apply_filters( 'dustpress/data', $data );
		}
		elseif ( ! empty( $this->model->data ) ) {
			$render_data = apply_filters( 'dustpress/data', $this->model->data );
			$render_data = apply_filters( 'dustpress/data/main', $render_data );
		}
		else {
			$render_data = null;
		}

		$this->dust->includedDirectories = $this->get_template_paths( 'partials' );

		// Create output with wanted format.
		$output = call_user_func_array( $types[$type], array( $render_data, $template, $dust ) );

		// Filter output
		$output = apply_filters( 'dustpress/output', $output, $options );

		// Do something with the data after rendering
		apply_filters( 'dustpress/data/after_render', $render_data );

		if ( $echo ) {
			if ( empty ( strlen( $output ) ) ) {
				$error = true;
				echo 'DustPress warning: empty output.';
			}
			else {
				echo $output;
			}
		}
		else {
			return $output;
		}

		if ( isset( $error ) ) {
			return false;
		}
		else {
			return true;
		}
	}

	/**
	*  This function checks whether the given partial exists and returns the contents of the file as a string
	*
	*  @type	function
	*  @date	17/3/2015
	*  @since	0.0.1
	*
	*  @param	$partial (string)
	*  @return	$template (string)
	*/
	private function get_template( $partial ) {
		// Check if we have received an absolute path.
		if ( file_exists( $partial ) )
			return $partial;
		else {
			$templatefile =  $partial . '.dust';

			$templates = $this->get_templates();

			if ( isset( $templates[ $templatefile ] ) ) {
				return $templates[ $templatefile ];
			}

			// Force searching of templates bypassing the cache.
			$templates = $this->get_templates( true );

			if ( isset( $templates[ $templatefile ] ) ) {
				return $templates[ $templatefile ];
			}

			// If we could not find such template.
			throw new Exception( 'Error loading template file: ' . $partial, 1 );
		}
	}

	/**
	*  This function initializes DustPress settings with default values
	*
	*  @type    function
	*  @date    01/04/2016
	*  @since   0.4.0
	*
	*  @return  N/A
	*/

	public function init_settings() {
		$this->settings = [
			'cache' => false,
			'debug_data_block_name' => 'Helper data',
			'rendered_expire_time' => 7*60*60*24,
			'json_url' => false,
			'json_headers' => false,
		];

		// loop through the settings and execute possible filters from functions
		foreach ( $this->settings as $key => $value ) {
			$this->settings[ $key ] = apply_filters( 'dustpress/settings/'. $key, $value );
		}

		// A hook to prevent DustPress error to appear in Yoast's sitemap
		add_filter( 'wpseo_build_sitemap_post_type', array( $this, 'disable' ), 1, 1 );

		// A hook to prevent DustPress error to appear when using WP Rest API
		add_action( 'rest_api_init', array( $this, 'disable' ), 1, 1 );

		// A hook to prevent DustPress error to appear when generating robots.txt
		add_action( 'do_robotstxt', array( $this, 'disable' ), 1, 1 );

		return null;
	}

	/**
	*  This function returns DustPress setting for specific key.
	*
	*  @type	function
	*  @date	29/01/2016
	*  @since	0.3.1
	*
	*  @return	$setting (any)
	*/

	public function get_setting( $key ) {
		return apply_filters( 'dustpress/settings/' . $key, isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : null );
	}

	/**
	*  Returns true if we are on login or register page.
	*
	*  @type	function
	*  @date	9/4/2015
	*  @since	0.0.7
	*
	*  @param	N/A
	*  @return	true/false (boolean)
	*/

	public function is_login_page() {
	    return in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) );
	}

	/**
	*  This function returns given string converted from CamelCase to camel-case
	*  (or probably camel_case or somethinge else, if wanted).
	*
	*  @type	function
	*  @date	15/6/2015
	*  @since	0.1.0
	*
	*  @param	$string (string)
	*  @param   $char (string)
	*  @return	(string)
	*/
	public function camelcase_to_dashed( $string, $char = '-' ) {
		preg_match_all( '!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches );
		$results = $matches[0];
		foreach ( $results as &$match ) {
	    	$match = $match == strtoupper( $match ) ? strtolower( $match ) : lcfirst( $match );
		}

	 	return implode( $char, $results );
	}

	/**
	*  This function returns given string converted from camel-case to CamelCase
	*  (or probably camel_case or somethinge else, if wanted).
	*
	*  @type	function
	*  @date	1/10/2016
	*  @since	1.2.9
	*
	*  @param	$string (string)
	*  @param   $char (string)
	*  @return	(string)
	*/
	public function dashed_to_camelcase( $string, $char = '-' ) {
		$string = str_replace( $char, ' ', $string );
		$string = str_replace( ' ', '', ucwords( $string ) );

		return $string;
	}

	/**
	*  This function determines if we want to autoload and render the model or not.
	*
	*  @type	function
	*  @date	10/8/2015
	*  @since	0.2.0
	*
	*  @return	(boolean)
	*/
	private function want_autoload() {
		$conditions = [
			function() {
				return ! $this->is_dustpress_ajax();
			},
			function() {
				return ! is_admin();
			},
			function() {
				return ! $this->is_login_page();
			},
			function() {
				return ! defined( 'WP_CLI' );
			},
			function() {
				return ( php_sapi_name() !== 'cli' );
			},
			function() {
				return ! defined( 'DOING_AJAX' );
			},
            function() {
                if ( strpos( $_SERVER['REQUEST_URI'], 'wp-activate') > 0 ) {
                    return true;
                }
                elseif ( defined( 'WP_USE_THEMES' ) ) {
                    return WP_USE_THEMES !== false;
                }
                else {
                    return false;
                }
            },
			function() {
				return ! ( strpos( $_SERVER['REQUEST_URI'], '/feed' ) !== false );
			},
			function() {
				return ! isset( $_GET['_wpcf7_is_ajax_call'] );
			},
			function() {
				return ! isset( $_POST['_wpcf7_is_ajax_call'] );
			},
			function() {
				return ! isset( $_POST['gform_ajax'] );
			},
			function() {
				return ! isset( $_POST['dustpress_comments_ajax'] );
			}
		];

		$conditions = apply_filters( 'dustpress/want_autoload', $conditions );

		foreach( $conditions as $condition ) {
			if ( is_callable( $condition ) ) {
				if ( false === $condition() ) {
					return false;
				}
			}
			else {
				if ( ! is_null( $condition ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	*  This function determines if we are on a DustPress AJAX call or not.
	*
	*  @type	function
	*  @date	17/12/2015
	*  @since	0.3.0
	*
	*  @return	(boolean)
	*/
	private function is_dustpress_ajax() {
		$request_body = file_get_contents( 'php://input' );
		$json = json_decode( $request_body );

		if ( isset( $_POST['dustpress_data'] ) || ( is_object( $json ) && property_exists( $json, 'dustpress_data' ) ) ) {
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * Register a function to be run with a keyword from DustPress.js.
	 *
	 * @type    function
	 * @date    25/11/2016
	 * @since   1.3.2
	 *
	 * @param   $key (string)
	 * @param   $callable (mixed)
	 *
	 * @return  void
	 */
	public function register_ajax_function( $key, $callable ) {
		$this->ajax_functions[ $key ] = $callable;
	}

	/**
	 * A function to determine if a keyword has already been registered for an ajax function.
	 *
	 * @type   function
	 * @date   10/10/2017
	 * @since  1.6.9
	 *
	 * @param  string $key
	 * @return (boolean)
	 */
	public function ajax_function_exists( $key ) {
		return isset( $this->ajax_functions[ $key ] );
	}

	/**
	*  This function does lots of AJAX stuff with the parameters from the JS side.
	*
	*  @type	function
	*  @date	17/12/2015
	*  @since	0.3.0
	*
	*  @param   N/A
	*  @return	N/A
	*/
	public function create_ajax_instance() {
		global $post;

		$model = false;

		$request_data = $this->request_data;

		if ( ! empty( $request_data->token ) && ! empty( $_COOKIE[ 'dpjs_token' ] ) ) {
			$token = ( $request_data->token === $_COOKIE[ 'dpjs_token' ] );
		}
		else {
			$token = false;
		}

		if ( ! $token ) {
			die( json_encode( [ 'error' => 'CSRF token mismatch.' ] ) );
		}

		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		$runs = [];

		// Get the args
		if ( ! empty( $request_data->args ) ) {
			$args = $request_data->args;
		}
		else {
			$args = [];
		}

		if ( ! preg_match( '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\/]*$/', $request_data->path ) ) {
			die( json_encode( [ 'error' => 'AJAX call path contains illegal characters.' ] ) );
		}

		// Check if the data we got from the JS side has a function path
		if ( isset( $request_data->path ) ) {

			// If the path is set as a custom ajax function key, run the custom function
			if ( isset( $this->ajax_functions[ $request_data->path ] ) ) {
				try {
					$data = call_user_func_array( $this->ajax_functions[ $request_data->path ], [ $args ] );
				}
				catch( Exception $e ) {
					die( json_encode( [ 'error' => $e->getMessage() ] ) );
				}

				if ( isset( $request_data->partial ) ) {
					$partial = $request_data->partial;
				}

				if ( empty( $partial ) ) {
					$output = [ 'success' => $data ];

					if ( isset( $request_data->data ) && $request_data->data === true ) {
						$output[ 'data' ] = $data;
					}

					die( wp_json_encode( $output ) );
				}
				else {
					$html = $this->render( [ 'partial' => $partial, 'data' => $data, 'echo' => false ] );

					if ( isset( $request_data->data ) && $request_data->data === true ) {
						$response = [ 'success' => $html, 'data' => $data ];
					}
					else {
						$response = [ 'success' => $html ];
					}

					if ( method_exists( '\DustPress\Debugger', 'use_debugger' ) && \DustPress\Debugger::use_debugger() ) {
						$response[ 'debug' ] = $data;
					}

					die( wp_json_encode( $response ) );
				}
			}
			else {
				$path = explode( DIRECTORY_SEPARATOR, $request_data->path );

				if ( count( $path ) > 2 ) {
					die( json_encode( [ 'error' => 'AJAX call did not have a proper function path defined (syntax: model/function).' ] ) );
				}
				else if ( count( $path ) == 2 ) {
					if ( strlen( $path[0] ) == 0 || strlen( $path[1] ) == 0 ) {
						die( json_encode( [ 'error' => 'AJAX call did not have a proper function path defined (syntax: model/function).' ] ) );
					}

					$model = $path[0];

					$functions = explode( ',', $path[1] );

					foreach( $functions as $function ) {
						$runs[] = $function;
					}
				}
				else {
					die( json_encode( [ 'error' => 'Custom AJAX function key \'' . $request_data->path . '\' was not found.' ] ) );
				}
			}
		}

		// If there was no model defined in the JS call, use the one we already are in.
		if ( ! $model ) {
			// Get current template name
			$model = $this->get_template_filename();

			$model = apply_filters( 'dustpress/template', $model );
		}

		// If render is set true, set the model's default template to be used.
		if ( isset( $request_data->render ) && $request_data->render === true ) {
			$partial = strtolower( $this->camelcase_to_dashed( $model ) );
		}

		// Do we want tidy output or not?
		if ( isset( $request_data->tidy ) ) {
			if ( $request_data->tidy === false ) {
				$tidy = false;
			}
			else {
				$tidy = true;
			}
		}
		else {
			$tidy = false;
		}

		// Get the possible defined partial and possible override the default template.
		if ( isset( $request_data->partial ) && strlen( $request_data->partial ) > 0 ) {
			$partial = $request_data->partial;
		}

		if ( class_exists( $model ) ) {
			$instance = new $model( $args );

			// Get the data
			$instance->fetch_data( $functions, $tidy );

			// If we don't want to render, json-encode and return just the data
			if ( empty( $partial ) ) {
				$output = [ 'success' => $instance->data ];

				if ( isset( $request_data->data ) && $request_data->data === true ) {
					$output[ 'data' ] = $instance->data;
				}

				die( wp_json_encode( $output ) );
			}
			else {
				$template_override = $instance->get_template();

				$partial = $template_override ? $template_override : strtolower( $this->camelcase_to_dashed( $partial ) );

				if ( $tidy && is_array( $functions ) && count( $functions ) === 1 ) {

					$data = $instance->data->{$functions[0]};

					$html = $this->render( [ 'partial' => $partial, 'data' => $data, 'echo' => false ] );
				}
				else {
					$data = $instance->data;
					$html = $this->render( [ 'partial' => $partial, 'data' => $data, 'echo' => false ] );
				}

				if ( isset( $request_data->data ) && $request_data->data === true ) {
					$response = [ 'success' => $html, 'data' => $data ];
				}
				else {
					$response = [ 'success' => $html ];
				}

				if ( method_exists( '\DustPress\Debugger', 'use_debugger' ) && \DustPress\Debugger::use_debugger() ) {
					$response[ 'debug' ] = $data;
				}

				die( wp_json_encode( $response ) );
			}
		}
		elseif ( empty( $model ) ) {
			die( wp_json_encode( [ 'error' => 'No model defined.' ] ) );
		}
		else {
			die( wp_json_encode( [ 'error' => 'Model \'' . $model . '\' does not exist.' ] ) );
		}
	}

	/**
	*  This function loops through the wanted partial and finds all helpers that are used.
	*  It is used recursively.
	*
	*  @type	function
	*  @date	17/12/2015
	*  @since	0.3.0
	*
	*  @param   $partial (string)
	*  @param   $already (array|string) (optional)
	*  @return	$helpers (array|string)
	*/
	public function prerender( $partial, $already = [] ) {
		$filename = $this->get_prerender_file( $partial );

		if ( $filename == false ) return;

		$file = file_get_contents( $filename );

		if ( in_array( $file, $already) ) {
			return;
		}

		$already[] = $file;

		$helpers = [];

		// Get helpers
		preg_match_all( '/\{@(\w+)/', $file, $findings );

		$helpers = array_merge( $helpers, array_unique( $findings[1] ) );

		// Get includes
		preg_match_all( '/\{>["\']?([-a-zA-z0-9\/]+)?/', $file, $includes);

		foreach( $includes[1] as $include ) {
			$incl_explode = explode( DIRECTORY_SEPARATOR, $include );

			$include = array_pop( $incl_explode );

			$include_helpers = $this->prerender( $include, $already );

			if ( is_array( $include_helpers ) ) {
				$helpers = array_merge( $helpers, array_unique( $include_helpers ) );
			}
		}

		if ( is_array( $helpers ) ) {
			return array_unique( $helpers );
		}
		else {
			return [];
		}
	}

	/**
	*  This function is used to get a template file to prerender.
	*
	*  @type	function
	*  @date	17/12/2015
	*  @since	0.3.0
	*
	*  @param   $partial (string)
	*  @return	$file (string)
	*/
	public function get_prerender_file( $partial ) {
		$templatefile =  $partial . '.dust';

		$templates = $this->get_templates();

		if ( isset( $templates[ $templatefile ] ) ) {
			return $templates[ $templatefile ];
		}
		return false;
	}

	/**
	*  This function executes dummy runs through all wanted helpers to enqueue scripts they need.
	*
	*  @type	function
	*  @date	17/12/2015
	*  @since	0.3.0
	*
	*  @param   $helpers (array|string)
	*  @return	N/A
	*/
	public function prerun_helpers( $helpers ) {
		if ( is_array( $helpers ) ) {
			$dummyEvaluator = new Dust\Evaluate\Evaluator( $this->dust );
			$dummyChunk = new Dust\Evaluate\Chunk( $dummyEvaluator );
			$dummyContext = new Dust\Evaluate\Context( $dummyEvaluator );
			$dummySection = new Dust\Ast\Section( null );
			$dummyBodies = new Dust\Evaluate\Bodies( $dummySection );
			$dummyParameters = new Dust\Evaluate\Parameters( $dummyEvaluator, $dummyContext );

			foreach( $this->dust->helpers as $name => $helper ) {
				if ( in_array( $name, $helpers) ) {
					if ( ( $helper instanceof \Closure ) || ( $helper instanceof \DustPress\Helper ) ) {
						$dummyBodies->dummy = true;
						call_user_func( $helper, $dummyChunk, $dummyContext, $dummyBodies, $dummyParameters );
					}
				}
			}
		}
	}

	/**
	*  Returns an array of Dust files present in the project. If the variable is empty, populates it recursively.
	*
	*  @type	function
	*  @date	21/03/2018
	*  @since	1.14.0
	*
	*  @param   N/A
	*  @return	array
	*/
	public function get_templates( $force = false ) {
		if ( empty( $this->templates ) || $force ) {
			if ( ! defined( 'DUSTPRESS_DISABLE_TEMPLATE_CACHE' ) || ( ! method_exists( '\DustPress\Debugger', 'use_debugger' ) && ! \DustPress\Debugger::use_debugger() ) ) {
				$this->templates = wp_cache_get( 'dustpress/templates' );
			}

			if ( empty( $this->templates ) || $force ) {
				$templatepaths = $this->get_template_paths( 'partials' );

				foreach ( $templatepaths as $templatepath ) {
					if ( is_readable( $templatepath ) ) {
						foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $templatepath, RecursiveDirectoryIterator::SKIP_DOTS ) ) as $file ) {
							if ( is_readable( $file->getPathname() ) && substr( $file->getFilename(), -5 ) === '.dust' ) {
								// Use only the first found template, do not override.
								if ( empty( $this->templates[ $file->getFilename() ] ) ) {
									$this->templates[ $file->getFilename() ] = $file->getPathname();
								}
							}
						}
					}
				}

				wp_cache_set( 'dustpress/templates', $this->templates );
			}
		}

		return $this->templates;
	}

	/**
	*  This function disables DustPress from doing pretty much anything.
	*
	*  @type	function
	*  @date	02/06/2016
	*  @since	0.3.3
	*
	*  @param   $param (mixed)
	*  @return	$param
	*/
	public function disable( $param = null ) {
		$this->disabled = true;

		return $param;
	}

	/**
	*  This function adds a helper.
	*
	*  @type	function
	*  @date	08/06/2016
	*  @since	0.4.0
	*
	*  @param   $param (mixed)
	*  @return	$param
	*/
	public function add_helper( $name, $instance ) {
		$this->dust->helpers[ $name ] = $instance;
	}

	/**
	 *  This function adds autoloaders for the classes
	 *
	 *  @type 	function
	 *  @date 	08/06/2016
	 *  @since  0.04.0
	 *
	 *  @param  N/A
	 *  @return N/A
	 */
	private function register_autoloaders() {
		// Autoload DustPHP classes
		spl_autoload_register( function ( $class ) {

		    // project-specific namespace prefix
		    $prefix = 'Dust\\';

		    // base directory for the namespace prefix
		    $base_dir = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'dust' . DIRECTORY_SEPARATOR;

		    // does the class use the namespace prefix?
		    $len = strlen( $prefix );
		    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		        // no, move to the next registered autoloader
		        return;
		    }

		    // get the relative class name
		    $relative_class = substr( $class, $len );

		    // replace the namespace prefix with the base directory, replace namespace
		    // separators with directory separators in the relative class name, append
		    // with .php
		    $file = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		    // if the file exists, require it
		    if ( file_exists( $file ) ) {
		        require $file;
		    }
		});

		// Autoload DustPress classes
		spl_autoload_register( function( $class ) {
			$paths = $this->get_template_paths( 'models' );

			$paths[] = dirname( __FILE__ );

			if ( $class == 'DustPress\Query' ) {
				$class = 'classes/query';
			}
			elseif ( $class == 'DustPress\Model' ) {
				$class = 'classes/model';
			}
			elseif ( $class == 'DustPress\Helper' ) {
				$class = 'classes/helper';
			}
			elseif ( $class == 'DustPress\Data' ) {
				$class = 'classes/data';
			}
			else {
				$class = $this->camelcase_to_dashed( $class, '-' );
			}

			$filename = strtolower( $class ) .'.php';

			// Store all paths when autoloading for the first time.
            if ( empty( $this->autoload_paths ) ) {
                foreach ( $paths as $path ) {
                    if ( is_readable( $path ) ) {
                        foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ) ) as $file ) {
                            $file_basename = $file->getBaseName();
                            $file_fullpath = $file->getPath() . DIRECTORY_SEPARATOR . $file->getFileName();

                            // Ignore certain files as they do not contain any model files.
                            if ( ! strstr( $file_basename, '.' ) || strstr( $file_fullpath, '/.git/' ) ) {
								continue;
							}

                            // Only store filepath and filename from the SplFileObject.
                            $this->autoload_paths[] = $file->getPath() . DIRECTORY_SEPARATOR . $file->getFileName();
                        }
                    }
                    else {
                        if ( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'models' !== $path ) {
                            die( 'DustPress error: Your theme does not have required directory ' . $path);
                        }
                    }
                }
            }

            // Find the file in the stored paths.
            foreach ( $this->autoload_paths as $file ) {
                if ( strpos( $file, DIRECTORY_SEPARATOR . $filename ) ) {
                    if ( is_readable( $file ) ) {
                        require_once( $file );

                        return;
                    }
                }
            }
		});
	}

	/**
	 *  This function returns the paths from which to look for models or partials
	 *
	 *  @type	function
	 *  @date	17/06/2016
	 *  @since	0.4.0.4
	 *
	 *  @param  string $append [models|partials]
	 *  @return array  list of paths to look in
	 */
	private function get_template_paths( $append ) {
		$tag = $append ? 'dustpress/' . $append : '';

		$return = apply_filters( $tag, [] );

		return $return;
	}

	/**
	 * Add themes to template paths array
	 * 
	 *  @type	function
	 *  @date	25/05/2018
	 *  @since	1.15.1
	 *
	 * @return void
	 */
	private function add_theme_paths() {
		foreach ( [ 'models', 'partials' ] as $target ) {
			add_filter( 'dustpress/' . $target, function( $paths ) use ( $target ) {
				// Set paths for where to look for partials and models
				$theme_paths = [
					get_stylesheet_directory(),
					get_template_directory()
				];

				$theme_paths = array_values( array_unique( $theme_paths ) );

				array_walk( $theme_paths, function( &$path ) use ( $target ) {
					$path .= DIRECTORY_SEPARATOR . $target;
				});

				$paths = array_merge( $theme_paths, $paths );

				return $paths;
			}, 1000, 1 );
		}
	}

	/**
	 * Add core path to template paths array
	 * 
	 *  @type	function
	 *  @date	25/05/2018
	 *  @since	1.15.1
	 *
	 * @return void
	 */
	private function add_core_paths() {
		foreach ( [ 'models', 'partials' ] as $target ) {
			add_filter( 'dustpress/' . $target, function( $paths ) use ( $target ) {
				$paths[] = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $target;

				return $paths;
			}, 1, 1 );
		}
	}

	/**
	 * Register a custom route for a model to be used outside the posts context.
	 *
	 * @type   function
	 * @date   15/03/2018
	 * @since  1.13.0
	 *
	 * @param string $route    A regular expression to be used as the route.
	 * @param string $template The model name to be used with the matching route.
	 * @param string $render   How to render the output. Defaults to HTML which means the Dust template system.
	 * @return void
	 */
	public function register_custom_route( $route, $template, $render = 'default' ) {
		$this->custom_routes[ $template ] = $route;

		add_action( 'init', function() use ( $route, $template, $render ) {
			add_rewrite_rule(
				'(' . $route . ')(\/(.+))?\/?$',
				'index.php?dustpress_custom_route=' . $template . '&dustpress_custom_route_route=$matches[1]&dustpress_custom_route_parameters=$matches[3]&dustpress_custom_route_render='. $render,
				'top'
			);
		}, 30);
	}

	/**
	 * Parse DustPress.js request data
	 * 
	 * @type  function
	 * @date  20/06/2018
	 * @since 1.16.1
	 *
	 * @return void
	 */
	private function parse_request_data() {
		$request_body = file_get_contents( 'php://input' );
		$json = json_decode( $request_body );

		if ( isset( $json->dustpress_data ) ) {
			$this->request_data = $json->dustpress_data;
		}
		elseif ( isset( $_POST['dustpress_data'] ) ) {
			$this->request_data = (object) $_POST['dustpress_data'];

			// Parse old data to correct format and assume it to be false if it isn't defined
			$this->request_data->tidy = ( isset( $this->request_data->tidy ) && $this->request_data->tidy === 'true' ) ? true : false;
			$this->request_data->render = ( isset( $this->request_data->render ) && $this->request_data->render === 'true' ) ? true : false;
		}
		else {
			die( json_encode( [ 'error' => 'Something went wrong. There was no dustpress_data present at the request.' ] ) );
		}
	}

	/**
	 * Force 404 page and status from anywhere
	 *
	 * @type  function
	 * @date  03/04/2019
	 * @since 1.23.0
	 *
	 * @return void
	 */
	public function error404( \DustPress\Model $model ) {
		global $wp_query;

		$model->terminate();

		\status_header( 404 );

		$template = 'Error404';

		if ( class_exists ( $template ) ) {
			$this->model = new $template();

			$this->model->fetch_data();

			$this->model->terminate();

			do_action( 'dustpress/model_list', array_keys( (array) $this->model->get_submodels() ) );

			$template_override = $this->model->get_template();

			$partial = $template_override ? $template_override : strtolower( $this->camelcase_to_dashed( $template ) );

			// Set page title for custom route.
			add_filter( 'wp_title', function( $title, $sep ) {
				$sep   = ' | ';
				$title = '404' . $sep . get_bloginfo( 'name' );
				return $title;
			}, 10, 2 );

			$this->render([
				'partial' => $partial,
				'main'    => true,
				'type'    => 'default'
			]);

			$this->disable();
		}
		else {
			die( 'DustPress error: No suitable model found. One of these is required: '. implode( ', ', $debugs ) );
		}
	}
}

// Global function that returns the DustPress singleton
function dustpress() {
	return DustPress::instance();
}
