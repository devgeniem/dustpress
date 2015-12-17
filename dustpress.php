<?php
/*
Plugin Name: DustPress
Plugin URI: http://www.geniem.com
Description: Dust templating system for WordPress
Author: Miika Arponen & Ville Siltala / Geniem Oy
Author URI: http://www.geniem.com
Version: 0.2.0
*/

class DustPress {

	// Instance of DustPHP
	private $dust;

	// Main model
	private $model;

	// This is where the data will be stored
	private $data;

	// Possible post body
	private $postbody;

	/*
	*  __construct
	*
	*  Constructor for DustPress core class.
	*
	*  @type	function
	*  @date	10/8/2015
	*  @since	0.2.0
	*
	*  @param	N/A
	*  @return	N/A
	*/

	public function __construct() {

		// start session for data storing
		if ( session_status() == PHP_SESSION_NONE ) {
    		session_start();
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
			elseif ( $class == "DustPressModel" ) {
				$class = "dustpress-model";	
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

		// Create Dust instance
		$this->dust = new Dust\Dust();

		// Set initial parameters
		$this->dust->includedDirectories[] = get_template_directory() . '/partials/';
		$this->dust->includedDirectories[] = get_template_directory() . '/dustpress/partials/';

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

		// Add create_instance to right action hook if we are not on the admin side
		if ( $this->want_autoload() ) {		
			$this->enqueue_scripts();		
			add_action( 'shutdown', [ $this, 'create_instance' ] );
		}
		else if ( $this->is_dustpress_ajax() ) {
			add_action( 'shutdown', [ $this, 'create_ajax_instance' ] );	
		}

		// Add admin menu
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );

		// Add admin stuff
		add_action( 'after_setup_theme', [ $this, 'admin_stuff' ] );

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

		// Filter for wanted post ID
		$new_post = apply_filters( "dustpress/router", $post->ID );

		// If developer wanted a post ID, make it happen
		if ( ! is_null( $new_post ) ) {
			$post = get_post( $new_post );

			setup_postdata( $post );
		}

		// Initialize an array for debugging.
		$debugs = [];

		// Get current template name tidied up a bit.
		$template = $this->get_template_filename( $debugs );

		$template = apply_filters( "dustpress/template", $template );

		// If class exists with the template's name, create new instance with it.
		// We do not throw error if the class does not exist, to ensure that you can still create
		// templates in traditional style if needed.
		if ( class_exists ( $template ) ) {
			$this->model = new $template();

			$this->model->fetch_data();

			$template_override = $this->model->get_template();

			$partial = $template_override ? $template_override : strtolower( $this->camelcase_to_dashed( $template ) );

			$this->render( [ "partial" => $partial ] );
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

		$page_template = get_post_meta( $post->ID, '_wp_page_template', true );

		if ( $page_template ) {
			$array = explode( "/", $page_template );

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

		// I don't think you really want to do this.
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
	*  populate_data_collection
	*
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
		global $dustpress;

		$wp_data = array();

		// Insert Wordpress blog info data to collection
		$infos = array( "name","description","wpurl","url","admin_email","charset","version","html_type","text_direction","language","stylesheet_url","stylesheet_directory","template_url","template_directory","pingback_url","atom_url","rdf_url","rss_url","rss2_url","comments_atom_url","comments_rss2_url","siteurl","home" );

		foreach ( $infos as $info ) {
			$wp_data[ $info ] = get_bloginfo( $info );
		}

		// Insert user info to collection

		$currentuser = wp_get_current_user();		
		
		if ( 0 === $currentuser->ID ) {
			$wp_data["loggedin"] = false;
		}
		else {
			$wp_data["loggedin"] = true;
			$wp_data["user"] = $currentuser->data;
			$wp_data["user"]->roles = $currentuser->roles;
			unset( $wp_data["user"]->user_pass );
		}

		// Insert WP title to collection
		ob_start();
		wp_title();
		$wp_data["title"] = ob_get_clean();

		// Insert admin ajax url
		$wp_data["admin_ajax_url"] = admin_url( 'admin-ajax.php' );

		$wp_data["permalink"] = get_permalink();

		// Return collection after filters
		return apply_filters( "dustpress/data/wp", $wp_data );
	}

	/*
	*  render
	*
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
			"data" => false,
			"type" => "default",
			"echo" => true
		];

		if ( is_array( $args ) ) {
			if ( ! isset( $args["partial"] ) ) {
				die("<p><b>DustPress error:</b> No partial is given to the render function.</p>");
			}
		}
		
		$options = array_merge( $defaults, (array)$args );

		extract( $options );

		if ( "default" == $type && ! get_option('dustpress_default_format' ) ) {
			$type = "html";
		}
		else if ( "default" == $type && get_option('dustpress_default_format' ) ) {
			$type = get_option('dustpress_default_format');
		}

		$types = array(
			"html" => function( $data, $partial, $dust ) {

				try {					
					$compiled = $dust->compileFile( $partial );
				}
				catch ( Exception $e ) {
					die( "DustPress error: ". $e->getMessage() );
				}

				return $dust->renderTemplate( $compiled, $data );		
			},
			"json" => function( $data, $partial, $dust ) {
				try {
					$output = json_encode( $data );
				}
				catch ( Exception $e ) {
					die( "JSON encode error: ". $e->getMessage() );
				}

				return $output;
			}
		);

		$types = apply_filters( 'dustpress/formats', $types );

		if ( ! $data ) {
			$this->model->data = (array)$this->model->data;

			$this->model->data['WP'] = $this->populate_data_collection();
		}

		// Fetch Dust partial by given name. Throw error if there is something wrong.
		try {
			$template = $this->get_template( $partial );

			$helpers = $this->prerender( $partial );

			$this->enqueue_helpers( $helpers );
		}
		catch ( Exception $e ) {
			die( "DustPress error: ". $e->getMessage() );
		}

		// Ensure we have a DustPHP instance.
		if ( isset( $this->dust ) ) {
			$dust = $this->dust;
		}
		else {
			die("DustPress error: Something very unexpected happened: there is no DustPHP.");
		}

		$dust->helpers = apply_filters( 'dustpress/helpers', $dust->helpers );

		// Create debug data if wanted.

		if ( ! $data && current_user_can( 'manage_options') && true == get_option('dustpress_debug') ) {
			$jsondata = json_encode( $this->model->data );
		
			$hash = md5( $_SERVER[ REQUEST_URI ] . microtime() );
			$data_array = array(
				'ajaxurl' 	=> admin_url( 'admin-ajax.php' ),
				'hash' 		=> $hash
			);
			wp_localize_script( 'dustpress_debugger', 'dustpress_debugger', $data_array );
			
			// jsonView jQuery plugin
			wp_enqueue_style( "jquery.jsonview", get_template_directory_uri() .'/dustpress/css/jquery.jsonview.css', null, null, null );
			wp_enqueue_script( "jquery.jsonview",  get_template_directory_uri() .'/dustpress/js/jquery.jsonview.js', array( 'jquery' ), null, true );

			// Enqueued script with localized data.
			wp_enqueue_script( 'dustpress_debugger' );
		}

		if ( $data ) {
			$render_data = apply_filters( 'dustpress/data', $data );;
		}
		else {
			$render_data = apply_filters( 'dustpress/data', $this->model->data );
		}

		// Create output with wanted format.
		$output = call_user_func_array( $types[$type], array( $render_data, $template, $dust ) );

		$output = apply_filters( 'dustpress/output', $output );

		// Store data into session for debugger to fetch
		if ( ! $data && current_user_can( 'manage_options') && true == get_option('dustpress_debug') ) {									
			$this->model->data 	= apply_filters( 'dustpress/data/after_render', $this->model->data );

			$_SESSION[ $hash ] = $this->model->data;			
		}

		if ( $echo ) {
			if ( empty ( strlen( $output ) ) ) {
				$error = true;
				echo "DustPress warning: empty output.";
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

	/*
	*  get_template
	*
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

			$templatepaths = [				
				get_template_directory() . '/dustpress/partials/',
				get_template_directory() . '/partials/'
			];

			$templatepaths = array_reverse( apply_filters( 'dustpress/partials', $templatepaths ) );

			foreach ( $templatepaths as $templatepath ) {
				if ( is_readable( $templatepath ) ) {
					foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $templatepath ) ) as $file ) {
						if ( strpos( $file, $templatefile ) !== false ) {
							if ( is_readable( $file ) ) {
								return $templatepath . $templatefile;
							}
						}
					}
				}
			}
			
			// If we could not find such template.
			throw new Exception( "Error loading template file: " . $partial, 1 );

			return false;
		}
	}

	/*
	*  admin_stuff
	*
	*  This function sets JavaScripts and styles for admin debug feature.
	*
	*  @type	function
	*  @date	23/3/2015
	*  @since	0.0.2
	*
	*  @param	N/A
	*  @return	N/A
	*/

	public function admin_stuff() {
		global $current_user;

		get_currentuserinfo();

		// If admin and debug is set to true, enqueue JSON printing
		if ( current_user_can( 'manage_options') && true == get_option('dustpress_debug') ) {
			wp_enqueue_script( 'jquery' );			
			
			// Just register the dustpress and enqueue later, if needed
			wp_register_script( "dustpress",  get_template_directory_uri() .'/dustpress/js/dustpress.js', null, null, true );

			// Register the debugger script
			wp_register_script( "dustpress_debugger",  get_template_directory_uri() .'/dustpress/js/dustpress-debugger.js', null, '0.0.2', true );						

			// Register debugger ajax hook
			add_action( 'wp_ajax_dustpress_debugger', array( $this, 'get_debugger_data' ) );
			add_action( 'wp_ajax_nopriv_dustpress_debugger', array( $this, 'get_debugger_data' ) );
		}
	}

	/*
	*  plugin_menu
	*
	*  This function creates the menu item for DustPress options in admin side.
	*
	*  @type	function
	*  @date	23/3/2015
	*  @since	0.0.2
	*
	*  @param	N/A
	*  @return	N/A
	*/

	public function plugin_menu() {
		add_options_page( 'DustPress Options', 'DustPress', 'manage_options', 'dustPress_options', array( $this, 'dustPress_options') );
	}

	/*
	*  dustPress_options
	*
	*  This function creates the options page functionality in admin side.
	*
	*  @type	function
	*  @date	23/3/2015
	*  @since	0.0.2
	*
	*  @param	N/A
	*  @return	N/A
	*/

	public function dustPress_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		if ( isset( $_POST['dustpress_hidden_send'] ) && $_POST['dustpress_hidden_send'] == 1 ) {
			$debug = $_POST['debug'];

			update_option( 'dustpress_debug', $debug );

			echo '<div class="updated"><p>Settings saved.</p></div>';
		}

		$debug_val = get_option('dustpress_debug');

		if ( $debug_val )
			$string = " checked=\"checked\"";
		else
			$string = "";
		
		echo '<div class="wrap">';
		echo '<h2>DustPress Options</h2>';
?>
		<form name="form1" method="post" action="">
			<input type="hidden" name="dustpress_hidden_send" value="1"/>

			<p><label for="debug">Show debug information</label> <input type="checkbox" value="1" name="debug"<?php echo $string; ?>/></p>

			<p class="submit">
				<input type="submit" name="Submit" class="button-primary" value="Save changes"/>
			</p>
		</form>
<?php

		echo '</div>';
	}

	/*
	*  get_debugger_data
	*
	*  This function returns dustpress data from the session.
	*
	*  @type	function
	*  @date	13/8/2015
	*  @since	0.1.1
	*
	*  @return	$data (json)
	*/
	public function get_debugger_data() {
		if ( defined("DOING_AJAX") ) {
			session_start();
		
			$hash = $_POST['hash'];
			$data = $_SESSION[$hash];

			if ( isset( $data ) && is_array( $data ) ) {
                unset( $_SESSION[$hash] );
                $status = 'success';
            } else {
				$status = 'error';
			}

			$response = [
				'status' 	=> $status, // 'success' || 'error'			
				'data' 		=> $data // data for js
            ];
			
			$output = json_encode($response);

			die( $output );
		}
	}

	/*
	*  set_debugger_data
	*
	*  This function sets data into global data collection.
	*  To be used for debugging purposes.
	*
	*  @type	function
	*  @date	13/8/2015
	*  @since	0.1.1
	*
	*  @param	$key (string)
	*  @param 	$data (N/A)
	*  @return	$data (json)
	*/
	public function set_debugger_data( $key, $data ) {
		if ( empty( $key ) ) {
			die( 'You did not set a key for your debugging data collection.' );
		} else {			
			if ( isset( $this->model ) ) {
				if ( ! isset( $this->model->data['Debugger'] ) ) {
					$this->model->data['Debugger'] = [];
				}

				if ( ! isset( $this->model->data['Debugger'][ $key ] ) ) {
					$this->model->data['Debugger'][ $key ] = [];	
				}

				$this->model->data['Debugger'][ $key ][] = $data;
			}
		}
	}

	/*
	*  is_login_page
	*
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

	/*
	*  is_installation_compatible
	*
	*  This function returns true if the WordPress configuration is suitable for DustPress.
	*
	*  @type	function
	*  @date	9/4/2015
	*  @since	0.0.7
	*
	*  @param	N/A
	*  @return	true/false (boolean)
	*/

	private function is_installation_compatible() {
		$ret = [];

		if ( ! is_readable( get_template_directory() .'/models' ) ) {
			$ret[] = "models";
		}
		if ( ! is_readable(get_template_directory() .'/partials' ) ) {
			$ret[] = "partials";
		}
		if ( ! defined("PHP_VERSION_ID") or PHP_VERSION_ID < 50300 ) {
			$ret[] = "phpversion";
		}

		return $ret;
	}

	/*
	*  required
	*
	*  This function prints out admin notice for missing folders on the theme file.
	*
	*  @type	function
	*  @date	15/6/2015
	*  @since	0.1.0
	*
	*  @param	N/A
	*  @return	N/A
	*/

	public function required() {
		$errors = [
			"models" => "Directory named \"models\" is required under the activated theme's directory.",
			"partials" => "Directory named \"partials\" is required under the activated theme's directory.",
			"phpversion" => "Your version of PHP is too old. DustPress requires at least version 5.3 to function properly."
		];

		echo "<div class=\"update-nag\"><p><b>DustPress errors:</b></p>";
		foreach( $this->errors as $error ) {
			echo "<p>". $errors[$error] ."</p>";
		}
		echo "</div>";
	}

	/*
	*  camelcase_to_dashed
	*
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
	private function camelcase_to_dashed( $string, $char = "-" ) {
		preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);
		$results = $matches[0];
		foreach ( $results as &$match ) {
	    	$match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
		}

	 	return implode($char, $results);
	}

	/*
	*  want_autoload
	*
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
				return ! defined( "WP_CLI" );
			},
			function() {
				return ( php_sapi_name() !== "cli" );
			},
			function() {
				return ! defined( "DOING_AJAX" );
			},
			function() {
				return WP_USE_THEMES !== false;
			},
			$_GET['_wpcf7_is_ajax_call'],
			$_POST['_wpcf7_is_ajax_call'],
			$_POST['gform_ajax'],
		];

		$conditions = apply_filters( "dustpress/want_autoload", $conditions );

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

	/*
	*  is_dustpress_ajax
	*
	*  This function determines if we are on a DustPress AJAX call or not.
	*
	*  @type	function
	*  @date	17/12/2015
	*  @since	0.3.0
	*
	*  @return	(boolean)
	*/
	private function is_dustpress_ajax() {
		if ( isset( $_REQUEST["dustpress_data"] ) ) {
			return true;
		}
		else {
			return false;
		}
	}

	/*
	*  create_ajax_instance
	*
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

		if ( isset( $_REQUEST["dustpress_data"] ) ) {
			$data = $_REQUEST["dustpress_data"];
		}
		die( json_encode( [ "error" => "Something went wrong. There was no dustpress_data present at the request." ] ) );	

		$runs = [];

		// Get the args
		if ( ! empty( $data["args"] ) ) {
			$args = $data["args"];
		}

		// Check if the data we got from the JS side has a function path
		if ( isset( $data["path"] ) ) {
			$path = explode( "/", $data["path"] );

			if ( count( $path ) > 2 ) {
				die( json_encode( [ "error" => "AJAX call did not have a proper function path defined (syntax: model/function)." ] ) );	
			}
			else if ( count( $path ) == 2 ) {
				if ( strlen( $path[0] ) == 0 || strlen( $path[1] ) == 0 ) {
					die( json_encode( [ "error" => "AJAX call did not have a proper function path defined (syntax: model/function)." ] ) );			
				}

				$model = $path[0];

				$functions = explode( ",", $path[1] );

				foreach( $functions as $function ) {
					$runs[] = $function;				
				}
			}
		}

		// If there was not model defined in JS call, use the one we already are in.
		if ( ! $model ) {
			// Get current template name
			$model = $this->get_template_filename();

			$model = apply_filters( "dustpress/template", $model );
		}

		// If render is set true, set the model's default template to be used.
		if ( isset( $args["render"] ) && $args["render"] == true ) {
			$partial = strtolower( $this->camelcase_to_dashed( $model ) );
		}

		// Get the possible defined partial and possible override the default template.
		if ( isset( $args["partial"] ) ) {
			$partial = $args["partial"];
		}

		// Do we want tidy output or not?
		if ( isset( $args["tidy"] ) ) {
			$tidy = $args["tidy"];
		}
		else {
			$tidy = false;
		}

		if ( class_exists( $model ) ) {
			$instance = new $model( $args );

			// Get the data
			$instance->fetch_data( $functions, $tidy );

			// If we don't want to render, json-encode and return just the data
			if ( ! isset( $partial ) ) {
				die( json_encode( [ "success" => $instance->data ] ) );
			}
			else {
				$template_override = $instance->get_template();

				$partial = $template_override ? $template_override : strtolower( $this->camelcase_to_dashed( $partial ) );

				die( $this->render( [ "partial" => $partial, "data" => $instance->data ] ) );
			}
		}
		else {
			die( json_encode( [ "error" => "Model '". $model ."' does not exist." ] ) );			
		}
	}

	// Prerender
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
		preg_match_all("/\{@(\w+)/", $file, $findings);

		$helpers = array_merge( $helpers, array_unique( $findings[1] ) );

		// Get includes
		preg_match_all("/\{>[\"']?([a-zA-z0-9\/]+)?/", $file, $includes);

		foreach( $includes[1] as $include ) {
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

	public function get_prerender_file( $partial ) {
		$templatefile =  $partial . '.dust';

		$templatepaths = array( get_template_directory() . '/partials/' );

		$templatepaths = array_reverse( apply_filters( 'dustpress/partials', $templatepaths ) );

		foreach ( $templatepaths as $templatepath ) {
			if ( is_readable( $templatepath ) ) {
				foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $templatepath ) ) as $file ) {
					if ( strpos( $file, $templatefile ) !== false ) {
						if ( is_readable( $file ) ) {
							return $file;
						}
					}
				}
			}
		}

		return false;
	}

	public function enqueue_helpers( $helpers ) {
		if ( is_array( $helpers ) ) {
			$dummyEvaluator = new Dust\Evaluate\Evaluator( $this->dust );
			$dummyChunk = new Dust\Evaluate\Chunk( $dummyEvaluator );
			$dummyContext = new Dust\Evaluate\Context( $dummyEvaluator );
			$dummySection = new Dust\Ast\Section( null );
			$dummyBodies = new Dust\Evaluate\Bodies( $dummySection );
			$dummyParameters = new Dust\Evaluate\Parameters( $dummyEvaluator, $dummyContext );

			foreach( $this->dust->helpers as $name => $helper ) {
				if ( in_array( $name, $helpers) ) {
					if ( $helper instanceof \Closure ) {
						$dummyBodies->dummy = true;
						call_user_func( $helper, $dummyChunk, $dummyContext, $dummyBodies, $dummyParameters );
					}
				}
			}
		}
	}

	/**
	*  enqueue_scripts
	*
	*  This function enqueues front end scripts.
	*
	*  @type	function
	*  @date	17/12/2015
	*  @since	0.3.0
	*
	*  @return	(boolean)
	*/
	private function enqueue_scripts() {
		wp_enqueue_script( 'dustpress', get_template_directory_uri() .'/dustpress/js/dustpress.js', null, '0.0.1', false );
	}
}

$dustpress = new DustPress();