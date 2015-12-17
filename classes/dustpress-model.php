<?php

/**
*  DustPress Model Class
*
*  Extendable class which contains basic functions for DustPress models.
*
*  @class DustPress_Model
*/

class DustPressModel {

	// The data
	public $data;

	// Arguments of this instance
	private $args;

	// Instances of all submodels initiated from this class
	private $submodels;

	// Possible parent model
	private $parent;

	// Possible wanted template
	private $template;

	// List of functions that are allowed to be run via AJAX
	//private $allowed_functions = [];

	/**
	*  __construct
	*
	*  Constructor for DustPress model class.
	*
	*  @type	function
	*  @date	10/8/2015
	*  @since	0.2.0
	*
	*  @param	load (boolean)
	*  @return	N/A
	*/
	
	public function __construct( $args = [], $parent = null ) {
		if ( ! empty( $args ) ) {
			$this->args = $args;
		}

		$this->submodels = (object)[];

		$this->parent = $parent;
	}

	public function get_args() {
		return $this->args;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_submodel( $name ) {
		return $this->submodels->{$name};
	}

	public function get_submodels() {
		return $this->submodels;
	}

	public function get_ancestor( $model = null ) {
		if ( ! isset( $model ) ) {
			return $this->get_ancestor( $this );
		}
		else {
			if ( isset( $model->parent ) ) {
				return $this->get_ancestor( $model->parent );
			}
			else {
				return $model;
			}
		}
	}

	/**
	*  fetch_data
	*
	*  This function gets the data from models and binds it to the global data structure
	*
	*  @type	function
	*  @date	15/10/2015
	*  @since	0.2.0
	*
	*  @param	N/A
	*  @return	N/A
	*/
	public function fetch_data( $functions = null ) {
		$class_name = get_class( $this );

		// Create a place to store the wanted data in the global data structure.
		if ( ! isset( $this->data[ $class_name ] ) ) $this->data[ $class_name ] = new \StdClass();
		if ( ! $this->parent && ! isset( $this->data[ $class_name ]->Content ) ) $this->data[ $class_name ]->Content = new \StdClass();

		// Fetch all methods from given class.
		$methods = $this->get_class_methods( $class_name );

		// If we are on an AJAX call, we may want to run some private or protected functions too
		$private_methods = [];

		// Loop through the methods
		foreach( $methods as $index => $method_item ) {
			$reflection = new ReflectionMethod( $class_name, $method_item );

			// If we have wanted list of functions, check if we can run them and don't run
			// anything else.
			if ( is_array( $functions ) && count( $functions ) > 0 ) {
				if ( ! in_array( $method_item, $functions ) ) {
					continue;
				}
				else {
					if ( ! $reflection->isPublic() && ! $this->is_function_allowed( $method_item ) ) {
						die( json_encode( [ "error" => "Method '". $method_item ."' is not allowed to be run via AJAX." ] ) );
					}
					else if ( $reflection->isProtected() || $reflection->isPrivate() ) {
						$private_methods[] = $method_item;
						continue;
					}
					else {
						if ( $reflection->getNumberOfParameters() > 0 ) {
							unset( $methods[ $index ] );
						}
						else {
							$methods[ $index ] = array( $this, $method_item );
						}
					}
				}
			}
			else {
				if ( $reflection->isPublic() ) {
					if ( $reflection->getNumberOfParameters() > 0 ) {
						unset( $methods[ $index ] );
					}
					else {
						$methods[ $index ] = array( $this, $method_item );
					}
				}
			}
		}

		// Add some filters
		$methods = apply_filters( "dustpress/methods", $methods, $class_name );
		$private_methods = apply_filters( "dustpress/private_methods", $private_methods, $class_name );

		// Loop through all public methods and run the ones we wanted to deliver the data to the views.
		foreach( $methods as $m ) {
			if ( is_array( $m ) ) { 
				if ( isset( $m[1] ) && is_string( $m[1] ) ) { 
					if ( $m[1] == "__construct" ) {
						continue;
					} 

					$method = str_replace( "bind_", "", $m[1] );

					if ( "Content" == $method ) {
						if ( ! isset( $this->data[ $class_name ]->{ $method } ) ) {
							$this->data[ $class_name ]->{ $method } = new stdClass();
						}

						$data = call_user_func( $class_name . '::' . $m[1] );

						if ( ! is_null( $data ) ) {
							$this->data[ $class_name ]->{ $method } = $data;
						}
					}
					else {
						$data = call_user_func( $class_name . '::' . $m[1] );
						if ( ! is_null( $data ) ) {
							if ( $this->parent ) {
								$content = (array) $this->data[ $class_name ];
								$content[ $method ] = $data;
								$this->data[ $class_name ] = (object) $content;
							}
							else {
								$content = (array) $this->data[ $class_name ]->Content;
								$content[ $method ] = $data;
								$this->data[ $class_name ]->Content = (object) $content;
							}
						}
					}
				}
			}
			else if ( is_callable( $m ) ) {
				if ( $m == "__construct" ) {
					continue;
				} 

				$method = str_replace( "bind_", "", $m );

				if ( ! isset( $this->data[ $class_name ]->Content->{ $method } ) ) {
					$this->data[ $class_name ]->Content->{ $method } = [];
				}

				$data = call_user_func( $m );

				if ( ! is_null( $data ) ) {
					if ( "content" == strtolower( $method )  ) {
    					$this->data[$class_name]->Content = (object) array_merge( (array) $this->data[$class_name]->Content, [ $method => $data ] );
					}
					else {
						if ( $this->parent ) {
							$this->data[ $class_name ]->{ $method } = $data;
						}
						else {
							$this->data[ $class_name ]->Content->{ $method } = $data;
						}
					}
				}
			}
		}

		// If there are private methods to run, run them too.
		if ( is_array( $private_methods ) && count( $private_methods ) > 0 ) {
			foreach( $private_methods as $method ) {		
				$data = $this->run_restricted( $method );

				if ( ! is_null( $data ) ) {
					if ( $this->parent ) {
						$content = (array) $this->data[ $class_name ];
						$content[ $method ] = $data;
						$this->data[ $class_name ] = (object) $content;
					}
					else {
						$content = (array) $this->data[ $class_name ]->Content;
						$content[ $method ] = $data;
						$this->data[ $class_name ]->Content = (object) $content;
					}
				}
			}
		}

		return $this->data[ $class_name ];
	}

	/**
	*  get_class_methods
	*
	*  This function returns all public methods from given class. Only class' own methods, no inherited.
	*
	*  @type	function
	*  @date	19/3/2015
	*  @since	0.0.1
	*
	*  @param	$class_name (string)
	*  @return	$methods (array)
	*/

	private function get_class_methods( $class_name ) {
		$rc = new \ReflectionClass( $class_name );
		$rmpu = $rc->getMethods();

		$methods = array();
		foreach ( $rmpu as $r ) {
			if ( $r->class === $class_name ) {
				$methods[] = $r->name;
			}
		}

		return $methods;
	}

	/**
	*  bind_sub
	*
	*  This function checks if a bound submodel is wanted to run and if it is, runs it.
	*
	*  @type	function
	*  @date	17/3/2015
	*  @since	0.0.1
	*
	*  @param	$name (string)
	*  @param	$args (array)
	*/

	public function bind_sub( $name, $args = null ) {
		$class_name = get_class( $this );
		$model = new $name( $args, $this );

		// If the submodel is not on the root level, set it under the current submodel.
		if ( $this->parent ) {
			$this->data[$class_name]->{ $name } = $model->fetch_data();
		}
		// Set submodel under the main model.
		else {
			$this->data[$name] = $model->fetch_data();
		}

		if ( ! is_object( $this->submodels ) ) {
			$this->submodels = (object)[];
		}

		$this->submodels->{$name} = $model;
	}

	/**
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
	*  @param   $model (string)
	*  @return	true/false (boolean)
	*/

	public function bind_data( $data, $key = null, $model = null ) {
		$class_name = get_class( $this );

		if ( ! $key ) {
			$key = $this->get_previous_function();
		}

		if ( $model ) {

			// Create a place to store the wanted data in the global data structure.
			if ( ! isset( $this->data[ $model ] ) ) $this->data[ $model ] = new \StdClass();
			if ( ! $this->parent && ! isset( $this->data[ $model ]->Content ) ) $this->data[ $model ]->Content = new \StdClass();

			if ( !isset( $this->data[ $model ] ) ) {
				$this->data[ $model ] = (object)[];
			}

			$this->data[ $model ]->{ $key } = $data;
		}
		else {
			// Create a place to store the wanted data in the global data structure.
			if ( ! isset( $this->data[ $class_name ] ) ) $this->data[ $class_name ] = new \StdClass();
			if ( ! $this->parent && ! isset( $this->data[ $class_name ]->Content ) ) $this->data[ $class_name ]->Content = new \StdClass();

			if ( ! $this->parent ) {
				if ( "Content" == $key ) {
					// array merge ei toimi, jos data ei ole array
					// jos data on tyhjä, tulee php warning
					// TODO: luo virheilmoitus
					if ( is_array( $data ) ) {
						$this->data[ $class_name ]->{ $key } = (object) array_merge( (array) $this->data[$class_name]->Content, $data );
					}
				}
				else {					
					$this->data[ $class_name ]->Content->{ $key } = $data;
				}
			}
			else {
				$this->data[ $class_name ]->{ $key } = $data;
			}
		}
	}

	/**
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

	/**
	*  get_template
	*
	*  This function returns the desired Dust template, if the developer has defined one instead of default. Otherwise return false.
	*
	*  @type	function
	*  @date	15/10/2015
	*  @since	0.2.0
	*
	*  @param	N/A
	*  @return	mixed
	*/

	public function get_template() {
		$ancestor = $this->get_ancestor();

		if ( $this == $ancestor ) {
			return $this->template;
		}
		else {
			return $ancestor->get_template();
		}
	}

	/**
	*  set_template
	*
	*  This function lets the developer to set the template to be used to render a page.
	*
	*  @type	function
	*  @date	15/10/2015
	*  @since	0.2.0
	*
	*  @param	$template (string)
	*  @return	N/A
	*/

	public function set_template( $template ) {
		$ancestor = $this->get_ancestor();

		if ( $template ) {
			if ( $this == $ancestor ) {
				$this->template = $template;
			}
			else {
				$ancestor->set_template( $template );
			}
		}
	}

	/**
	*  use_comments
	*
	*  This function adds scripts and styles needed with the Comments-helper.
	*
	*  @type	function
	*  @date	13/8/2015
	*  @since	0.1.1
	*
	*  @return	N/A
	*/

	public function use_comments( $post_id = null, $form_id = null, $status_id = null, $reply_label = null ) {		
		global $post;

		$js_args = [
			'ajaxurl' 			=> admin_url( 'admin-ajax.php' ),
			'comments_per_page' => get_option('comments_per_page'),
			'post_id'			=> $post_id 	? $post_id		: $post->ID,
			'form_id' 			=> $form_id 	? $form_id 		: 'commentform',
			'status_id' 		=> $status_id 	? $status_id 	: 'comments__status',			
			'reply_label' 		=> $reply_label ? $reply_label 	: __( 'Reply to comment', 'DustPress-Comments')
		];

		// styles
		wp_enqueue_style( 'dustpress-comments-styles', get_template_directory_uri().'/dustpress/css/dustpress-comments.css', false, 1, all );		
		
		// js		
		wp_register_script( 'dustpress-comments', get_template_directory_uri().'/dustpress/js/dustpress-comments.js', array('jquery'), null, true);
		wp_localize_script( 'dustpress-comments', 'comments', $js_args );
		wp_enqueue_script( 'dustpress-comments' );
	}

	/**
	*  is_function_allowed
	*
	*  This functions returns true if asked private or protected functions is
	*  allowed to be run via the run wrapper.
	*
	*  @type	function
	*  @date	17/12/2015
	*  @since	0.3.0
	*
	*  @param   $function (string)
	*  @return	$allowed (boolean)
	*/

	private function is_function_allowed( $function ) {
		error_log( $function );
		error_log( print_r( $this->allowed_functions, true ) );

		if ( in_array( $function, $this->allowed_functions ) ) {
			return true;
		}
		else {
			return false;
		}
	}

	/**
	*  run_restricted
	*
	*  This function runs a restricted function if it exists in the allowed functions
	*  and returns whatever the wanted function returns.
	*
	*  @type	function
	*  @date	17/12/2015
	*  @since	0.3.0
	*
	*
	*  @param   $function (string)
	*  @param   $args (array)
	*  @return	mixed
	*/

	public function run_restricted( $function ) {
		if ( $this->is_function_allowed ) {
			return call_user_func( [ $this, $function ] );
		}
		else {
			return (object)["error" => "Wanted function does not exist in the allowed functions list."];
		}
	}
}