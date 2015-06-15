<?php
/*
Plugin Name: DustPress
Plugin URI: http://www.geniem.com
Description: Dust templating system for WordPress
Author: Miika Arponen & Ville Siltala / Geniem Oy
Author URI: http://www.geniem.com
Version: 0.1.0
*/

// Require WordPress plugin functions to have the ability to deactivate the plugin if needed.
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

class DustPress {

	// Instance of DustPHP
	private $dust;

	// Instances of other classesƒ
	public $classes;

	// Data collection
	public $data;

	// Possible parent
	public $parent;

	// Possible arguments from external caller
	public $args;

	// Possible partial name
	public $partial;

	// Are we on the main instance?
	public $main;

	// Do we want to render
	public $do_not_render;

	// Possible post body is stored hiere
	public $body;

	/*
	*  __construct
	*
	*  Constructor for DustPress. Takes possible parent instance as parameter and stores it, if needed. Can and should be
	*  extended by subclasses.
	*
	*  @type	function
	*  @date	19/3/2015
	*  @since	0.0.1
	*
	*  @param	parent (object)
	*  @return	N/A
	*/
	public function __construct( $parent = null, $args = null, $is_main = false ) {
		if ( ! $this->is_installation_compatible() ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
		
			wp_die( __('DustPress requires /models/ and /partials/ directories under the activated theme.') );
		}

		if ("DustPress" === get_class( $this ) ) {
			// Autoload DustPHP classes
			spl_autoload_register( function ( $class ) {

			    // project-specific namespace prefix
			    $prefix = 'Dust\\';

			    // base directory for the namespace prefix
			    $base_dir = __DIR__ . '/dust/';

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
				$paths = array(
					__DIR__ . '/classes/',
					get_template_directory() . '/models',
				);

				$class = $this->camelcase_to_dashed( $class, "-" );
				
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

			// Find and include Dust helpers from DustPress plugin
			$paths = array(
				__DIR__ . '/helpers',
			);

			foreach( $paths as $path ) {
				if ( is_readable( $path ) ) {
					foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ) ) as $file ) {
						if ( is_readable( $file ) ) {
							require_once( $file );
						}
					}
				}
			}

			// Create data collection
			$this->data = array();

			// Create classes array
			$this->classes = array();

			$this->args = new StdClass();

			// Add create_instance to right action hook if we are not on the admin side
			if ( ! is_admin() && ! $this->is_login_page() && ! defined("DOING_AJAX") ) {
				add_action( 'shutdown', array( $this, 'create_instance' ) );
			}

			// Add admin menu
			add_action( 'admin_menu', array($this, 'plugin_menu') );

			// Add admin stuff
			add_action( 'plugins_loaded', array($this, 'admin_stuff') );

			return;
		}
		else {
			global $dustpress;

			$template = $this->get_template_filename();

			if ( is_array( $args ) ) {
				$class = $this->get_class();
				$dustpress->args->{$class} = $args;
			}

			if ( $parent ) {
				$this->parent = $parent;
			}

			if ( $is_main ) {
				$this->main = $is_main;
			}

			if ( strtolower( $template ) == strtolower( get_class( $this ) ) ) {
				$this->populate_data_collection();

				$this->get_data();

				if ( $this->get_post_body() === true ) {

					if ( ! ( $partial = $this->get_partial() ) )
						$partial = strtolower( $this->camelcase_to_dashed( $template ) );

					if ( ! $this->get_render_status ) {
						$this->render( $partial );
					}
				}
				else {
					$accepts = $this->get_post_body();

					$response = array();

					foreach( $accepts as $accept ) {
						if ( isset( $dustpress->data[ $accept->function ] ) ) {
							if ( isset( $accept->dp_partial ) ) {
								$response[ $accept->function ] = $dustpress->render( $accept->dp_partial, $dustpress->data, "html", false );
							}
							else if ( ! isset( $accept->dp_type ) && ( $accept->dp_type == "json" ) ) {
								$response[ $accept->function ] = $dustpress->data[ $accept->function ];
							}
						}
					}

					echo json_encode( $response );
					return;
				}

			}
			else {
				$this->get_data();
			}
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
			wp_register_script( "dustpress",  plugin_dir_url( __FILE__ ) .'js/dustpress.js', null, null, true );

			// Register the debugger script
			wp_register_script( "dustpress_debugger",  plugin_dir_url( __FILE__ ) .'js/dustpress-debugger.js', null, null, true );						
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

		// Get current template name tidied up a bit.
		$template = $this->get_template_filename();

		// If class exists with the template's name, create new instance with it.
		// We do not throw error if the class does not exist, to ensure that you can still create
		// templates in traditional style if needed.
		if ( class_exists ( $template ) ) {
			new $template( $dustpress, null, true );
		}
		else {
			die("DustPress error: model \"" . $template . "\" is not found.");
		}
	}

	/*
	*  get_data
	*
	*  This function gets the data from models and binds it to the global data structure
	*
	*  @type	function
	*  @date	19/3/2015
	*  @since	0.0.1
	*
	*  @param	N/A
	*  @return	N/A
	*/
	public function get_data() {
		global $dustpress;

		$className = get_class( $this );

		// Create a place to store the wanted data in the global data structure.
		if ( ! isset( $dustpress->data[ $className ] ) ) $dustpress->data[ $className ] = new \StdClass();
		if ( ! isset( $dustpress->data[ $className ]->Content ) ) $dustpress->data[ $className ]->Content = new \StdClass();

		// Fetch all methods from given class.
		$methods = $this->get_class_methods( $className );

		// Loop through all methods and run the ones starting with "bind" that deliver data to the views.
		foreach( $methods as $method ) {
			if ( strpos( $method, "bind" ) !== false ) {
				call_user_func( array( $this, $method ) );
			}
		}
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
	public function render( $partial, $data = -1, $type = 'default', $echo = true ) {
		global $dustpress;

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

		// If no data attribute given, take contents from object data collection
		if ( $data == -1 ) $data = $dustpress->data;

		$data = apply_filters( 'dustpress/data', $data );

		// Fetch Dust partial by given name. Throw error if there is something wrong.
		try {
			$template = $this->get_template( $partial );
		}
		catch ( Exception $e ) {
			$data = array(
				'dustPressError' => "DustPress error: ". $e->getMessage()				
			);
			$template = $this->get_error_template();
			$error = true;
		}

		// Ensure we have a DustPHP instance.
		if ( isset( $this->dust ) ) {
			$dust = $this->dust;
		}
		else {
			$dust = $this->parent->dust;
		}

		$dust->helpers = apply_filters( 'dustpress/helpers', $dust->helpers );

		// Create debug data if wanted and only if we are on the main instance.
		if ( $this->main == true && current_user_can( 'manage_options') && true == get_option('dustpress_debug') ) {
			$jsondata = json_encode( $data );
			
			//wp_register_script( "dustpress",  plugin_dir_url( __FILE__ ) .'js/dustpress.js', null, null, true );

			// Localize the script with new data
			$data_array = array(
				'jsondata' => $jsondata,				
			);
			wp_localize_script( 'dustpress_debugger', 'dustpress_debugger', $data_array );
			
			// jsonView jQuery - plugin
			wp_enqueue_style( "jquery.jsonview", plugin_dir_url( __FILE__ ) .'css/jquery.jsonview.css', null, null, null );
			wp_enqueue_script( "jquery.jsonview",  plugin_dir_url( __FILE__ ) . 'js/jquery.jsonview.js', array( 'jquery' ), null, true );

			// Enqueued script with localized data.
			wp_enqueue_script( 'dustpress_debugger' );

		}

		// Create output with wanted format.
		$output = call_user_func_array( $types[$type], array( $data, $template, $dust ) );

		$output = apply_filters( 'dustpress/output', $output, $this->main );

		if ( $echo ) {
			echo $output;
		}
		else {
			return $output;
		}

		if ( $error ) {
			return false;
		}
		else {
			return true;
		}

	}

	/*
	*  is_wanted
	*
	*  This function checks if certain partial is wanted into output
	*
	*  @type	function
	*  @date	17/3/2015
	*  @since	0.0.1
	*
	*  @param	$partial (string)
	*  @return	true/false (boolean)
	*/
	public function is_wanted( $partial ) {
		global $dustpress;

		if ( ( $accepts = $this->get_post_body() ) === true ) {
			return true;
		}

		foreach( $accepts as $accept ) {
			if ( $partial == $accept->function ) {
				return true;
			}
		}

		return false;
	}

	/*
	*  get_post_body
	*
	*  This function gets the possible settings json from post body and assigns the data
	*  to appropriate places. It returns either an array containing data for which functions'
	*  data to include in the response or boolean "true" if we are not in an ajax request.
	*
	*  @type	function
	*  @date	02/04/2015
	*  @since	0.0.6
	*
	*  @param	N/A
	*  @return	mixed
	*/
	private function get_post_body() {
		global $dustpress;

		if ( isset( $dustpress->body ) ) {
			$body = $dustpress->body;
		}
		else {
			$dustpress->body = file_get_contents('php://input');
			$body = $dustpress->body;
		}

		$accepts = array();

		try {
			$json = json_decode( $body );

			if ( $json["ajax"] === true ) {
				$accepts[] = "Content";
			}

			if ( count( $json ) > 0 ) {
				foreach ( $json as $container ) {
					if ( isset( $container->function ) ) {
						$temp = new StdClass();
						$temp->function = $container->function;

						if ( isset( $container->args->dp_type ) ) {
							$temp->type = $container->args->dp_type;
						}

						if ( isset( $container->args->dp_partial ) ) {
							$temp->partial = $container->args->dp_partial;
						}

						$accepts[] = $temp;

						if ( isset( $container->args ) ) {
							$dustpress->args->{$container->function} = $container->args;
						}
					}
				}
			}
			else {
				return true;
			}
		}
		catch( Exception $e ) {
			return true;
		}

		return $accepts;
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

			$templatepaths = array( get_template_directory() . '/partials/' );

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
			throw new Exception( "Error loading template file: " . $template, 1 );
		}
	}

	/*
	*  get_error_template
	*
	*  This function returns simple error template
	*
	*  @type	function
	*  @date	17/3/2015
	*  @since	0.0.1
	*
	*  @param	N/A
	*  @return	$template (string)
	*/
	private function get_error_template() {
		return '<p class="dustpress-error">{dustPressError}</p>';
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

		$WP = array();

		// Insert Wordpress blog info data to collection
		$infos = array( "name","description","wpurl","url","admin_email","charset","version","html_type","text_direction","language","stylesheet_url","stylesheet_directory","template_url","template_directory","pingback_url","atom_url","rdf_url","rss_url","rss2_url","comments_atom_url","comments_rss2_url","siteurl","home" );

		foreach ( $infos as $info ) {
			$WP[ $info ] = get_bloginfo( $info );
		}

		// Insert user info to collection

		$currentuser = wp_get_current_user();		
		
		if ( 0 === $currentuser->ID ) {
			$WP["loggedin"] = false;
		}
		else {
			$WP["loggedin"] = true;
			$WP["user"] = $currentuser->data;
			unset( $WP["user"]->user_pass );
		}

		// Insert WP title to collection
		ob_start();
		wp_title();
		$WP["title"] = ob_get_clean();

		// Insert admin ajax url
		$WP["admin_ajax_url"] = admin_url( 'admin-ajax.php' );

		$WP["permalink"] = get_permalink();

		// Push array to collection
		$dustpress->data["WP"] = $WP;
	}

	/*
	*  get_class_methods
	*
	*  This function returns all public methods from given class. Only class' own methods, no inherited.
	*
	*  @type	function
	*  @date	19/3/2015
	*  @since	0.0.1
	*
	*  @param	$className (string)
	*  @return	$methods (array)
	*/
	private function get_class_methods($className) {
		$rc = new \ReflectionClass($className);
		$rmpu = $rc->getMethods(\ReflectionMethod::IS_PUBLIC);

		$methods = array();
		foreach ( $rmpu as $r ) {
			$r->class === $className && $methods[] = $r->name;
		}

		return $methods;
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
	private function get_template_filename() {
		global $post;

		$pageTemplate = get_post_meta( $post->ID, '_wp_page_template', true );

		$array = explode( "/", $pageTemplate );

		$template = array_pop( $array );

		// strip out .php
		$template = str_replace( ".php", "", $template );

		// strip out page-, single-
		$template = str_replace( "page-", "", $template );
		$template = str_replace( "single-", "", $template );

		if ( $template == "default" ) $template = "page";

		$type = get_post_type();
		$cat = get_category( get_query_var('cat') );

		if ( is_tag() ) {
			$term_id = get_queried_object()->term_id;
			$term = get_term_by( "id", $term_id, "post_tag" );
		}
		else if ( is_tax() ) {
			$term_id = get_queried_object()->term_id;
			$term = get_term_by( "id", $term_id, get_query_var('taxonomy') );
		}

		$author = get_user_by( 'slug', get_query_var( 'author_name' ) );

		$mime_type = get_post_mime_type( get_the_ID() );

		$post_types = get_post_types();

		$hierarchy = [
			"is_home" => [
				"HomePage"
			],
			"is_page" => [
				"Page" . ucfirst( $template ),
				"Page" . ucfirst( $post->post_name ),
				"Page" . $post->ID,
				"Page"
			],
			"is_category" => [
				"Category" . ucfirst( $cat->slug ),
				"Category" . $cat->term_id,
				"Category",
				"Archive"
			],
			"is_tag" => [
				"Tag" . ucfirst( $term->slug ),
				"Tag",
				"Archive"
			],
			"is_tax" => [
				"Taxonomy" . ucfirst( get_query_var('taxonomy') ) . ucfirst($term->slug),
				"Taxonomy" . ucfirst( get_query_var('taxonomy') ),
				"Taxonomy",
				"Archive"
			],
			"is_author" => [
				"Author" . ucfirst( $author->user_nicename ),
				"Author" . $author->ID,
				"Author",
				"Archive"
			],
			"is_search" => [
				"Search"
			],
			"is_404" => [
				"Error404"
			],
			"is_attachment" => [
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
			],
			"is_single" => [
				"Single" . ucfirst( $type ),
				"Single"
			],
			"is_archive" => [
				function() use ( $post_types ) {
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
			]
		];

		$hierarchy = apply_filters( "dustpress/template_hierarchy", $hierarchy );

		foreach( $hierarchy as $level => $keys ) {
			if ( true === call_user_func ( $level ) ) {
				foreach( $keys as $key => $value ) {
					if ( is_integer( $key ) ) {
						if( is_string( $value ) && class_exists( $value ) ) {
							return $value;
						}
						else if ( is_callable( $value ) ) {
							$value = call_user_func( $value );
							if( is_string( $value ) && class_exists( $value ) ) {
								return $value;
							}
						}
					}
					else if ( is_string( $key ) ) {
						if ( class_exists( $key ) ) {
							if( is_string( $value ) ) {
								return $value;
							}
							else if ( is_callable( $value ) ) {
								return call_user_func( $value );
							}
						}
					}
					else if ( true === $key or is_callable( $key ) ) {
						if ( true === call_user_func( $key ) ) {
							if( is_string( $value ) ) {
								return $value;
							}
							else if ( is_callable( $value ) ) {
								return call_user_func( $value );
							}
						}
					}
				}
			}
		}

		return "Index";
	}

	/*
	*  bind_sub
	*
	*  This function checks if a bound submodel is wanted to run and if it is, runs it.
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
	public function bind_sub( $name, $args = null ) {
		global $dustpress;

		if ( $this->is_wanted( $name ) ) {
			$dustpress->classes[$name] = new $name();

			if ( is_array($args) )
				$dustpress->args->{$name} = $args;

			if ( ! isset( $dustpress->data[$name] ) ) $dustpress->data[$name] = new \StdClass();
		}
	}

	/*
	*  get_args
	*
	*  This function gets the arguments for wanted name or, if we don't give a name, we get
	*  args for current module.
	*
	*  @type	function
	*  @date	26/3/2015
	*  @since	0.0.3
	*
	*  @param	$name (string)
	*  @return	args (array)
	*/
	public function get_args( $name = null ) {
		global $dustpress;

		if ( $name ) {
			if ( isset( $dustpress->args->{$name} ) ) {
				return $dustpress->args->{$name};
			}
			else {
				return null;
			}
		}
		else {
			$module = $this->get_class();

			if ( isset( $dustpress->args->{$module} ) ) {
				return $dustpress->args->{$module};
			}
			else {
				return null;
			}
		}
	}

	/*
	*  bind_data
	*
	*  This function binds the data from the models to the global data structure.
	*  It could take a key to bind the data in, but as default creates the key from
	*  the function name.
	*
	*  @type	function
	*  @date	17/3/2015
	*  @since	0.0.1
	*
	*  @param	$data (N/A)
	*  @param	$key (string)
	*  @return	true/false (boolean)
	*/
	public function bind_data( $data, $key = null, $module = null ) {
		global $dustpress;

		$temp = array();

		if ( ! $module ) {
			$module = $this->get_class();
		}

		if ( ! $key ) {
			$key = $this->get_previous_function();
		}

		if ( strtolower( $key ) == "content" ) {
			$dustpress->data[$module]->Content = (object) array_merge( (array) $dustpress->data[$module]->Content, (array) $data );
		}
		else if ( $this->is_sub_module() ) {
			if ( isset( $dustpress->data[$module] ) ) {
				$dustpress->data[$module]->{$key} = $data;
			}
		}
		else {
			if ( isset( $dustpress->data[$module] ) ) {
				$dustpress->data[$module]->Content->{$key} = $data;
			}	
		}
	}

	/*
	*  get_class
	*
	*  This function is a static proxy for PHP function get_called_class() to know from which
	*  class a certain possibly inherited function is run.
	*
	*  @type	function
	*  @date	18/3/2015
	*  @since	0.0.1
	*
	*  @param	$data (N/A)
	*  @return	$classname (string)
	*/
	public static function get_class() {
		return get_called_class();
	}

	/*
	*  get_previous_function
	*
	*  This function returns the function where current function was called.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	N/A
	*  @return	$function (string)
	*/
	public function get_previous_function() {
		$backtrace = debug_backtrace();

		if ( isset( $backtrace[2] ) ) {
			$function = $backtrace[2]["function"];

			// strip out extra or get to get the block
			$function = str_replace ( "bind_", "bind", $function );
			$function = str_replace ( "bind", "", $function );
			return $function;
		}
		else {
			return false;
		}
	}

	/*
	*  is_sub_module
	*
	*  This function returns true if current function is from a submodule.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	N/A
	*  @return	true/false (boolean)
	*/
	public function is_sub_module() {
		if ( $this->array_search_recursive( "bind_sub", debug_backtrace() ) ) {
			return true;
		}
		else {
			return false;
		}
	}

	/*
	*  get_partial
	*
	*  This function returns the desired partial, if the developer has wished to do so. Otherwise return false.
	*
	*  @type	function
	*  @date	1/4/2015
	*  @since	0.0.6
	*
	*  @param	N/A
	*  @return	mixed
	*/
	public function get_partial() {
		global $dustpress;

		if ( isset( $dustpress->partial ) ) {
			return $dustpress->partial;
		}
		else {
			return false;
		}
	}

	/*
	*  set_partial
	*
	*  This function lets the developer to set the partial to be used to render a page.
	*
	*  @type	function
	*  @date	1/4/2015
	*  @since	0.0.6
	*
	*  @param	$partial (string)
	*  @return	N/A
	*/
	public function set_partial( $partial ) {
		global $dustpress;

		if ( $partial ) {
			$dustpress->partial = $partial;
		}
	}

	/*
	*  get_render_status
	*
	*  This function returns true/false whether we want to render (by default) or not.
	*
	*  @type	function
	*  @date	1/4/2015
	*  @since	0.0.6
	*
	*  @param	N/A
	*  @return	true/false (boolean)
	*/
	public function get_render_status() {
		global $dustpress;

		return $dustpress->do_not_render;
	}

	/*
	*  do_not_render
	*
	*  The developer can call this function if he wishes to not render the view automatically.
	*
	*  @type	function
	*  @date	1/4/2015
	*  @since	0.0.6
	*
	*  @param	N/A
	*  @return	N/A
	*/
	public function do_not_render() {
		global $dustpress;

		$dustpress->do_not_render = true;
	}

	/*
	*  array_search_recursive
	*
	*  This function extends PHP's array_search function making it recursive. Updates $indedex also
	*  with the indexes where wanted value is located.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$needle (N/A)
	*  @param	$haystack (array)
	*  @param	&$indexes (array)
	*  @return	true/false (boolean)
	*/
	public function array_search_recursive($needle, $haystack, &$indexes=array()) {
	    foreach ( $haystack as $key => $value ) {
	        if ( is_array( $value ) ) {
	            $indexes[] = $key;
	            $status = $this->array_search_recursive( $needle, $value, $indexes );
	            if ( $status ) {
	                return true;
	            } else {
	                $indexes = array();
	            }
	        } else if ( $value === $needle ) {
	            $indexes[] = $key;
	            return true;
	        }
	    }
	    return false;
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
		if ( ! is_readable( get_template_directory() .'/models' ) ) {
			error_log( get_template_directory() .'/models was not found.' );
			return false;
		}
		if ( ! is_readable(get_template_directory() .'/partials' ) ) {
			error_log( get_template_directory() .'/partials was not found.');
			return false;
		}

		return true;
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
		foreach ($results as &$match) {
	    	$match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
		}

	 	return implode($char, $results);
	}
}

// Create an instance of the plugin after checking a few things
	// Contact Form 7 Ajax call filter
	if ( !( isset( $_POST['_wpcf7_is_ajax_call'] ) || isset( $_GET['_wpcf7_is_ajax_call'] ) ) ) {
		$dustpress = new DustPress();
	}