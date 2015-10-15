<?php
/*
Plugin Name: DustPress
Plugin URI: http://www.geniem.com
Description: Dust templating system for WordPress
Author: Miika Arponen & Ville Siltala / Geniem Oy
Author URI: http://www.geniem.com
Version: 0.2
*/

class DustPress_Core {

	// Instance of DustPHP
	private $dust;

	// Instances of other classes
	public $classes = [];

	/*
	*  __construct
	*
	*  Constructor for DustPress core class.
	*
	*  @type	function
	*  @date	10/8/2015
	*  @since	0.2
	*
	*  @param	N/A
	*  @return	N/A
	*/

	public function __construct() {

		// start sessio for data storing
		if ( session_status() == PHP_SESSION_NONE ) {
    		session_start();
		}

		// Set constant, when doing ajax with plugins
		if (
			// Contact Form 7
			isset( $_POST['_wpcf7_is_ajax_call'] ) || 
			isset( $_GET['_wpcf7_is_ajax_call'] ) ||
			// Gravity Forms
			isset( $_POST['gform_ajax'] )
		) {
			define('DOING_AJAX', true);
		}

		// Autoload DustPHP classes
		spl_autoload_register( function ( $class ) {

		    // project-specific namespace prefix
		    $prefix = 'Dust\\';

		    // base directory for the namespace prefix
		    $base_dir = get_template_directory() . '/dustpress/dust/';

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
		    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		    // if the file exists, require it
		    if ( file_exists( $file ) ) {			    	
		        require $file;
		    }
		});

		// Autoload DustPress classes
		spl_autoload_register( function( $class ) {
			$paths = [
				get_template_directory() . '/dustpress/classes/',
				get_template_directory() . '/models',
			];

			if ( $class == "DustPressHelper" ) {
				$class = "dustpress-helper";
			}
			else {
				$class = $this->camelcase_to_dashed( $class, "-" );
			}
			
			$filename = strtolower( $class ) .".php";

			foreach ( $paths as $path ) {
				if ( is_readable( $path ) ) {
					foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) ) as $file ) {
						if ( strpos( $file, $filename ) ) {
							if ( is_readable( $file ) ) {
								require_once( $file );
							}
						}
					}
				}
				else {
					die("DustPress error: Your theme does not have required directory ". $path);
				}
			}
		});

		// Find and include Dust helpers from DustPress plugin
		$paths = [
			get_template_directory() . '/dustpress/helpers',
		];

		foreach( $paths as $path ) {
			if ( is_readable( $path ) ) {
				foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ) ) as $file ) {
					if ( is_readable( $file ) ) {
						require_once( $file );
					}
				}
			}
		}

		// Add hooks for helpers' ajax functions
		$this->set_helper_hooks();

		// Add create_instance to right action hook if we are not on the admin side
		if ( ! is_admin() && ! $this->is_login_page() && ! defined("DOING_AJAX") ) {				
			add_action( 'shutdown', [ $this, 'create_instance' ] );
		}

		// Add admin menu
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );

		// Add admin stuff
		add_action( 'after_setup_theme', [ $this, 'admin_stuff' ] );

		// Add create_instance to right action hook if we are not on the admin side
		if ( ! is_admin() && ! $this->is_login_page() && ! defined("DOING_AJAX") ) {				
			add_action( 'shutdown', [ $this, 'create_instance' ] );
		}

		return;

	}

	/*
	*  create_instance
	*
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
		global $post;
		global $dustpress;

		// Initialize an array for debugging.
		$debugs = [];

		// Get current template name tidied up a bit.
		$template = $this->get_template_filename( $debugs );

		// If class exists with the template's name, create new instance with it.
		// We do not throw error if the class does not exist, to ensure that you can still create
		// templates in traditional style if needed.
		if ( class_exists ( $template ) ) {
			new $template( $dustpress, null, true );
		}
		else {
			die("DustPress error: No suitable model found. One of these is required: ". implode(", ", $debugs));
		}
	}

	/*
	*  get_template_filename
	*
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

		$pageTemplate = get_post_meta( $post->ID, '_wp_page_template', true );

		if ( $pageTemplate ) {
			$array = explode( "/", $pageTemplate );

			$template = array_pop( $array );

			// strip out .php
			$template = str_replace( ".php", "", $template );

			// strip out page-, single-
			$template = str_replace( "page-", "", $template );
			$template = str_replace( "single-", "", $template );

			if ( $template == "default" ) $template = "page";
		}
		else {
			$template = "default";
		}

		$hierarchy = [
			"is_home" => [
			    "FrontPage",
				"Home"
			]
		];

		if ( is_page() ) {
			$hierarchy["is_page"] = [
				"Page" . ucfirst( $template ),
				"Page" . ucfirst( $post->post_name ),
				"Page" . $post->ID,
				"Page"
			];
		}

		if ( is_category() ) {
			$cat = get_category( get_query_var('cat') );

			$hierarchy["is_category"] = [
				"Category" . ucfirst( $cat->slug ),
				"Category" . $cat->term_id,
				"Category",
				"Archive"
			];
		}

		if ( is_tag() ) {
			$term_id = get_queried_object()->term_id;
			$term = get_term_by( "id", $term_id, "post_tag" );

			$hierarchy["is_tag"] = [
				"Tag" . ucfirst( $term->slug ),
				"Tag",
				"Archive"
			];
		}

		if ( is_tax() ) {
			$term_id = get_queried_object()->term_id;
			$term = get_term_by( "id", $term_id, get_query_var('taxonomy') );

			$hierarchy["is_tax"] = [
				"Taxonomy" . ucfirst( get_query_var('taxonomy') ) . ucfirst($term->slug),
				"Taxonomy" . ucfirst( get_query_var('taxonomy') ),
				"Taxonomy",
				"Archive"
			];
		}

		if ( is_author() ) {
			$author = get_user_by( 'slug', get_query_var( 'author_name' ) );

			$hierarchy["is_author"] = [
				"Author" . ucfirst( $author->user_nicename ),
				"Author" . $author->ID,
				"Author",
				"Archive"
			];
		}

		$hierarchy["is_search"] = [
			"Search"
		];


		$hierarchy["is_404"] = [
			"Error404"
		];

		if ( is_attachment() ) {
			$mime_type = get_post_mime_type( get_the_ID() );

			$hiearchy["is_attachment"] = [
				function() use ( $mime_type ) {
					if ( preg_match( "/^image/", $mime_type ) && class_exists("Image") ) {
						return "Image";
					}
					else {
						return false;
					}
				},
				function() use ( $mime_type ) {
					if ( preg_match( "/^video/", $mime_type ) && class_exists("Video") ) {
						return "Video";
					}
					else {
						return false;
					}
				},
				function() use ( $mime_type ) {
					if ( preg_match( "/^application/", $mime_type ) && class_exists("Application") ) {
						return "Application";
					}
					else {
						return false;
					}
				},
				function() use ( $mime_type ) {
					if ( $mime_type == "text/plain" ) {
						if ( class_exists( "Text" ) ) {
							return "Text";
						}
						else if ( class_exists( "Plain" ) ) {
							return "Plain";
						}
						else if ( class_exists( "TextPlain" ) ) {
							return "TextPlain";
						}
						else {
							return false;
						}
					}
					else {
						return false;
					}
				},
				"Attachment",
				"SingleAttachment",
				"Single"
			];
		}

		if ( is_single() ) {
			$type = get_post_type();

			$hierarchy["is_single"] = [
				"Single" . ucfirst( $type ),
				"Single"
			];
		}

		if ( is_archive() ) {
			$hierarchy["is_hierarchy"] = [
				function() {
					$post_types = get_post_types();

					foreach ( $post_types as $type ) {
						if ( is_post_type_archive( $type ) ) {
							if( class_exists( "Archive" . ucfirst( $type ) ) ) {
								return "Archive" . ucfirst( $type );
							}
							else if ( class_exists("Archive") ) {
								return "Archive";
							}
							else {
								return false;
							}
						}
						else {
							return false;
						}
					}
				}
			];
		}

		$hierarchy = apply_filters( "dustpress/template_hierarchy", $hierarchy );

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

		$debugs[] = "Index";
		return "Index";
	}

	/*
	*  set_helper_hooks
	*
	*  This function sets JavaScripts and styles for admin debug feature.
	*
	*  @type	function
	*  @date	02/09/2015
	*  @since	0.1.2
	*
	*  @param	N/A
	*  @return	N/A
	*/

	public function set_helper_hooks() {

		// set hooks for comments helper
		if ( isset( $_POST['dustpress_comments_ajax'] ) ) {		
			
			if ( ! defined('DOING_AJAX') ) {
				define('DOING_AJAX', true);
			}

			// initialize helper
			$comments_helper = new Comments_Helper( 'handle_ajax', $this );

			// fires after comment is saved
			add_action( 'comment_post', array( $comments_helper, 'handle_ajax' ), 2 );

			// wp error handling
			add_filter('wp_die_ajax_handler', array( $comments_helper, 'get_error_handler' ) );			
	
		}

	}

}

global $dustpress_core;
$dustpress_core = new DustPress_Core();