<?php

/*
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

	/*
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

	/*
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
	public function fetch_data() {
		$className = get_class( $this );

		// Create a place to store the wanted data in the global data structure.
		if ( ! isset( $this->data[ $className ] ) ) $this->data[ $className ] = new \StdClass();
		if ( ! isset( $this->data[ $className ]->Content ) ) $this->data[ $className ]->Content = new \StdClass();

		// Fetch all methods from given class.
		$methods = $this->get_class_methods( $className );

		foreach( $methods as &$method ) {			
			$method = array( $this, $method );
		}

		$methods = apply_filters( "dustpress/methods", $methods, $className );

		// Loop through all methods and run the ones starting with "bind" that deliver data to the views.
		foreach( $methods as $m ) {
			if ( is_array( $m ) ) {
				if ( isset( $m[1] ) && is_string( $m[1] ) ) {	
					$data = call_user_func( $className . '::' . $m[1] );

					if ( ! is_null( $data ) ) {
						$this->data[ $className ]->Content->{ $m[1] } = $data;
					}
				}
			}
			else if ( is_callable( $m ) ) {
				$data = call_user_func( $m );

				if ( ! is_null( $data ) ) {
					$this->data[ $className ]->Content->{ $m[1] } = $data;
				}
			}
		}
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

	private function get_class_methods( $className ) {
		$rc = new \ReflectionClass( $className );
		$rmpu = $rc->getMethods( \ReflectionMethod::IS_PUBLIC );

		$methods = array();
		foreach ( $rmpu as $r ) {
			if ( $r->class === $className ) {
				$methods[] = $r->name;
			}
		}

		return $methods;
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
	*  @param	$name (string)
	*  @param	$args (array)
	*/

	public function bind_sub( $name, $args = null ) {
		$model = new $name( $args, $this );

		$this->data[$name] = $model->fetch_data();

		$this->submodels->{$name} = $model;
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
	*  @param   $model (string)
	*  @return	true/false (boolean)
	*/

	public function bind_data( $data, $key = null, $model = null ) {
		$className = get_class( $this );

		if ( ! $key ) {
			$key = $this->get_previous_function();
		}

		if ( $model ) {
			if ( !isset( $this->data[ $model ] ) ) {
				$this->data[ $model ] = (object)[];
			}

			$this->data[ $model ]->{ $key } = $data;
		}
		else {
			if ( ! $this->parent ) {
				$this->data[ $className ]->Content->{ $key } = $data;
			}
			else {
				$this->data[ $className ]->{ $key } = $data;	
			}
		}
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

	/*
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
}