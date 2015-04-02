<?php
/*
Plugin Name: DustPress
Plugin URI: http://www.geniem.com
Description: Dust templating system for WordPress
Author: Miika Arponen & Ville Siltala / Geniem Oy
Author URI: http://www.geniem.com
Version: 0.0.6
*/

class DustPress {

	// Instance of DustPHP
	private $dust;

	// Instances of other classes
	public $classes;

	// Data collection
	public $data;

	// Possible parent
	public $parent;

	// Possible arguments from external caller
	public $args;

	// Possible partial name
	public $partial;

	// Do we want to render
	public $donotrender;

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
	public function __construct($parent = null, $args = null) {
		if("DustPress" === get_class($this)) {
			// Autoload DustPHP classes
			spl_autoload_register(function ($class) {

			    // project-specific namespace prefix
			    $prefix = 'Dust\\';

			    // base directory for the namespace prefix
			    $base_dir = __DIR__ . '/dust/';

			    // does the class use the namespace prefix?
			    $len = strlen($prefix);
			    if (strncmp($prefix, $class, $len) !== 0) {
			        // no, move to the next registered autoloader
			        return;
			    }

			    // get the relative class name
			    $relative_class = substr($class, $len);

			    // replace the namespace prefix with the base directory, replace namespace
			    // separators with directory separators in the relative class name, append
			    // with .php
			    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

			    // if the file exists, require it
			    if (file_exists($file)) {
			        require $file;
			    }
			});

			// Autoload DustPress classes
			spl_autoload_register( function($class) {
				$paths = array(
					__DIR__ . '/classes/',
					get_template_directory() . '/models',
				);

				$filename = strtolower($class) .".php";

				foreach($paths as $path) {
					if(is_readable($path)) {
						foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
							if(strpos($file,$filename)) {
								if(is_readable($file)) {
									require_once($file);
									return;
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

			// Find and include Dust helpers both from DustPress and under the theme
			$paths = array(
				__DIR__ . '/helpers',
				get_template_directory() . '/helpers'
			);

			foreach($paths as $path) {
				if(is_readable($path)) {
					foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
						if(is_readable($file)) {
							require_once($file);
						}
					}
				}
			}

			// Create data collection
			$this->data = array();

			// Create classes array
			$this->classes = array();

			if(is_array($args))
				$this->args = $args;
			else
				$this->args = array();

			// Add createInstance to right action hook if we are not on the admin side
			if(!is_admin())
				add_action( 'shutdown', array( $this, 'createInstance' ) );

			// Add admin menu
			add_action( 'admin_menu', array($this, 'pluginMenu') );

			// Add admin stuff
			add_action( 'plugins_loaded', array($this, 'adminStuff') );

			return;
		}
		else {
			$template = $this->getTemplateFileName();

			if($parent)
				$this->parent = $parent;

			if(strtolower($template) == strtolower(get_class($this))) {
				$this->populateDataCollection();

				$this->getData();

				if($accepts = $this->getPostBody()) === true) {
					if(!($partial = $this->getPartial()))
						$partial = strtolower($template);

					if(!$this->getRenderStatus)
						$this->render($partial);
				}
				else {
					$response = array();

					foreach($accepts as $accept) {
						if(isset($dustpress->data[$accept->function])) {
							if(isset($accept->dp_partial)) {
								$response[$accept->function] = $dustpress->render($accept->dp_partial, $dustpress->data, "html", false);
							}
							else if(!isset($accept->dp_type) && ($accept->dp_type == "json")) {
								$response[$accept->function] = $dustpress->data[$accept->function];
							}
						}
					}

					echo json_encode($response);
					return;
				}

			}
			else {
				$this->getData();
			}
		}
	}

	/*
	*  adminStuff
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

	public function adminStuff() {
		global $current_user;

		get_currentuserinfo();

		// If admin and debug is set to true, enqueue JSON printing
		if( current_user_can( 'manage_options') && true == get_option('dustpress_debug') ) {
			wp_enqueue_script( 'jquery' );

			wp_enqueue_script( "jquery.jsonview",  plugin_dir_url( __FILE__ ) . 'js/jquery.jsonview.js', null, null, false );
			wp_enqueue_script( "dustpress",  plugin_dir_url( __FILE__ ) .'js/dustpress.js', null, null, false );

			wp_enqueue_style( "jquery.jsonview", plugin_dir_url( __FILE__ ) .'css/jquery.jsonview.css', null, null, null );
		}
	}

	/*
	*  pluginMenu
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

	public function pluginMenu() {
		add_options_page( 'DustPress Options', 'DustPress', 'manage_options', 'dustpressoptions', array( $this, 'dustpressoptions') );
	}

	/*
	*  dustpressoptions
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

	public function dustpressoptions() {
		if( !current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		if( isset($_POST['dustpress_hidden_send']) && $_POST['dustpress_hidden_send'] == 1 ) {
			$debug = $_POST['debug'];

			update_option('dustpress_debug', $debug);

			echo '<div class="updated"><p>Settings saved.</p></div>';
		}

		$debug_val = get_option('dustpress_debug');

		if($debug_val)
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
	*  createInstance
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
	public function createInstance() {
		global $post;
		global $dustpress;

		// Get current template name tidied up a bit.
		$template = $this->getTemplateFilename();

		// If class exists with the template's name, create new instance with it.
		// We do not throw error if the class does not exist, to ensure that you can still create
		// templates in traditional style if needed.
		if(class_exists($template))
			new $template($dustpress);
	}

	/*
	*  getData
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
	public function getData() {
		global $dustpress;

		$className = get_class($this);

		// Create a place to store the wanted data in the global data structure.
		if(!isset($dustpress->data[$className])) $dustpress->data[$className] = new \StdClass();
		if(!isset($dustpress->data[$className]->Content)) $dustpress->data[$className]->Content = new \StdClass();

		// Fetch all methods from given class.
		$methods = $this->getClassMethods($className);

		// Loop through all methods and run the ones starting with "bind" that deliver data to the views.
		foreach($methods as $method) {
			if(strpos($method, "bind") !== false) {
				call_user_func( array($this, $method) );
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
	public function render($partial, $data = -1, $type = 'html', $echo = true) {
		global $dustpress;

		// If no data attribute given, take contents from object data collection
		if($data == -1) $data = $dustpress->data;

		// Fetch Dust partial by given name. Throw error if there is something wrong.
		try {
			$template = $this->getTemplate($partial);
		}
		catch(Exception $e) {
			$data = array(
				'dustPressError' => "DustPress error: ". $e->getMessage()				
			);
			$template = $this->getErrorTemplate();
			$error = true;
		}

		// Ensure we have a DustPHP instance.
		if(isset($this->dust)) $dust = $this->dust;
		else $dust = $this->parent->dust;

		// Create debug data if wanted.
		if( current_user_can( 'manage_options') && true == get_option('dustpress_debug') ) {
			$jsondata = json_encode($data);
		}

		//var_dump($data);

		// Create output with wanted format.
		switch ($type) {
			case 'html':
				if($error) {
					$compiled = $dust->compile($data);
				}
				else {
					try {
						$compiled = $dust->compileFile($template);
					}
					catch(Exception $e) {
						die("DustPress error: ". $e->getMessage());
					}
				}								 
				$output = $dust->renderTemplate($compiled, $data);				
				break;
			case 'json':
				$output = json_encode($data);							
				break;
			default:
				$output = 'Template type does not exist.';
				break;
		}

		// If logged in and debug option set true in admin side, show the debugging pane.
		if( current_user_can( 'manage_options') && true == get_option('dustpress_debug') ) {
			$string = '
				<script>
				jQuery(document).ready(function() {
					var jsondata = '. $jsondata .';

					var div = "<div class=\"jsonview_debug\"></div>";

					$(div).appendTo(".jsonview_data_debug");

					$(".jsonview_debug").jsonView(jsondata);

					$(".jsonview_open_debug").click(function() {
						$(".jsonview_debug").slideToggle();

					jQuery(div).appendTo(".jsonview_data_debug");

					jQuery(".jsonview_debug").jsonView(jsondata);

					jQuery(".jsonview_open_debug").click(function() {
						jQuery(".jsonview_debug").slideToggle();
					});
				});
				</script>
				<div class="jsonview_data_debug">
					<button class="jsonview_open_debug">Show/Hide data debug</button>
				</div>
				</body>';

			$output = str_replace("</body>",$string,$output);
		}

		if ($echo)
			echo $output;
		else
			return $output;

		if ($error)
			return false;
		else
			return true;

	}

	/*
	*  isWanted
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
	public function isWanted($partial) {
		global $dustpress;

		if(($accepts = $this->getPostBody()) === true) {
			return true;
		}

		foreach($accepts as $accept) {
			if($partial == $accept->function)
				return true;
		}

		return false;
	}

	/*
	*  getPostBody
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
	private function getPostBody() {
		global $dustpress;

		if(isset($dustpress->body))
			$body = $dustpress->body;
		else {
			$dustpress->body = stream_get_contents(STDIN);
			$body = $dustpress->body;
		}

		$accepts = array();

		try($json = json_decode($body)) {
			if($json["ajax"] === true) {
				$accepts[] = "Content";
			}

			foreach($json as $container) {
				if(isset($container->function)) {
					$temp = new StdClass();
					$temp->function = $container->function;

					if(isset($container->args->dp_type)) {
						$temp->type = $container->args->dp_type;
					}

					if(isset($container->args->dp_partial)) {
						$temp->partial = $container->args->dp_partial;
					}

					$accepts[] = $temp;

					if(isset($container->args)) {
						$dustpress->args->{$container->function} = $container->args;
					}
				}
			}
		}
		catch(Exception $e) {
			return true;
		}

		return $accepts;
	}

	/*
	*  getTemplate
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
	private function getTemplate($partial) {
		// Check if we have received an absolute path.
		if (file_exists($partial))
			return $partial;
		else {
			$templatefile =  $partial . '.dust';
			$templatepath = get_template_directory() . '/partials/';
			foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templatepath)) as $file) {
				if(strpos($file,$templatefile) !== false) {
					if(is_readable($file)) {
						return $templatepath . $templatefile;
					}
				}
			}
			
			// If we could not find such template.
			throw new Exception("Error loading template file: " . $template, 1);
		}
	}

	/*
	*  getErrorTemplate
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
	private function getErrorTemplate() {
		return '<p class="dustpress-error">{dustPressError}</p>';
	}

	/*
	*  populateDataCollection
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
	private function populateDataCollection() {
		global $dustpress;

		$WP = array();

		// Insert Wordpress blog info data to collection
		$infos = array("name","description","wpurl","url","admin_email","charset","version","html_type","text_direction","language","stylesheet_url","stylesheet_directory","template_url","template_directory","pingback_url","atom_url","rdf_url","rss_url","rss2_url","comments_atom_url","comments_rss2_url","siteurl","home");

		foreach($infos as $info) {
			$WP[$info] = get_bloginfo($info);
		}

		// Insert user info to collection

		$currentuser = wp_get_current_user();		
		
		if(0 === $currentuser->ID) {
			$WP["loggedin"] = false;
		}
		else {
			$WP["loggedin"] = true;
			$WP["user"] = $currentuser->data;
			unset($WP["user"]->user_pass);
		}

		// Insert WP title to collection
		ob_start();
		wp_title();
		$WP["title"] = ob_get_clean();

		// Insert admin ajax url
		$WP["admin_ajax_url"] = admin_url( 'admin-ajax.php' );

		// Push array to collection
		$dustpress->data["WP"] = $WP;
	}

	/*
	*  getClassMethods
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
	private function getClassMethods($className) {
		$rc = new \ReflectionClass($className);
		$rmpu = $rc->getMethods(\ReflectionMethod::IS_PUBLIC);

		$methods = array();
		foreach($rmpu as $r) {
			$r->class === $className && $methods[] = $r->name;
		}

		return $methods;
	}

	/*
	*  getTemplateFileName
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
	private function getTemplateFileName() {
		global $post;

		$pageTemplate = get_post_meta( $post->ID, '_wp_page_template', true );

		// if no template set, return default
		if(!$pageTemplate && $type = get_post_type()) {
			if($type == "post") $type = "single";
			return $type;
		}
		else if(!$pageTemplate) return "default";
		
		$array = explode("/",$pageTemplate);

		$filename = array_pop($array);

		// strip out .php
		$filename = str_replace(".php","",$filename);

		// strip out page-, single-
		$filename = str_replace("page-","",$filename);
		$filename = str_replace("single-","",$filename);

		return $filename;
	}

	/*
	*  bindSub
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
	public function bindSub($name, $args = null) {
		global $dustpress;

		if($this->isWanted($name)) {
			$dustpress->classes[$name] = new $name();

			if(is_array($args))
				$dustpress->args->{$name} = $args;

			if(!isset($dustpress->data[$name])) $dustpress->data[$name] = new \StdClass();
		}
	}

	/*
	*  getArgs
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
	public function getArgs($name = null) {
		global $dustpress;

		if($name) {
			if(isset($dustpress->args->{$name}))
				return $dustpress->args->{$name};
			else
				return null;
		}
		else {
			$module = $this->getClass();

			if(isset($dustpress->args{$name}))
				return $dustpress->args->{$module};
			else
				return null;
		}
	}

	/*
	*  bindData
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
	public function bindData($data, $key = null) {
		global $dustpress;

		$temp = array();

		$module = $this->getClass();

		if(!$key) {
			$key = $this->getPreviousFunction();
		}

		if($this->isSubModule() || ($key == "__")) {
			if(isset($dustpress->data[$module])) {
				$dustpress->data[$module]->{$key} = $data;
			}
		}
		else if($key == "Content") {
			$dustpress->data[$module]->Content = (object)array_merge((array)$dustpress->data[$module]->Content, (array)$data);
		}
		else {
			if(isset($dustpress->data[$module])) {
				$dustpress->data[$module]->Content->{$key} = $data;
			}	
		}
	}

	/*
	*  getClass
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
	public static function getClass() {
		return get_called_class();
	}

	/*
	*  getPreviousFunction
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
	public function getPreviousFunction() {
		$backtrace = debug_backtrace();

		if(isset($backtrace[2])) {
			$function = $backtrace[2]["function"];

			// strip out extra or get to get the block
			$function = str_replace("bind","",$function);
			return $function;
		}
		else
			return false;
	}

	/*
	*  isSubModule
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
	public function isSubModule() {
		if($this->array_search_recursive("bindSub", debug_backtrace()))
			return true;
		else
			return false;
		
	}

	public function getPartial() {
		global $dustpress;

		if(isset($dustpress->partial))
			return $dustpress->partial;
		else
			return false;
	}

	public function setPartial($partial) {
		global $dustpress;

		if($partial) {
			$dustpress->partial = $partial;
		}
	}

	public function getRenderStatus() {
		global $dustpress;

		return $dustpress->donotrender;
	}

	public function doNotRender() {
		global $dustpress;

		$dustpress->donotrender = true;
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
	    foreach ($haystack as $key => $value) {
	        if (is_array($value)) {
	            $indexes[] = $key;
	            $status = $this->array_search_recursive($needle, $value, $indexes);
	            if ($status) {
	                return true;
	            } else {
	                $indexes = array();
	            }
	        } else if ($value == $needle) {
	            $indexes[] = $key;
	            return true;
	        }
	    }
	    return false;
	}
}

// Create an instance of the plugin if we are on the public side
$dustpress = new DustPress();