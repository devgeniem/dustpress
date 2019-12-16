<?php
/**
 * DustPress Model Class
 *
 * Extendable class which contains basic functions for DustPress models.
 *
 * @class DustPress_Model
 */

namespace DustPress;

use get_class;

/**
 * The base class for template models.
 */
class Model {

    // The data
    public $data;

    // Class name
    protected $class_name;

    // Arguments of this instance
    private $args = array();

    // Instances of all submodels initiated from this class
    private $submodels;

    // Possible parent model
    private $parent;

    // Possible wanted template
    private $template;

    // Temporary hash key
    private $hash;

    // Is execution terminated
    private $terminated;

    // Called submodels
    protected $called_subs;

    // Methods that are allowed to run externally
    protected $api;

    // The TTL for model cache
    protected $ttl;

    /**
     * Constructor for DustPress model class.
     *
     * @type   function
     * @date   10/8/2015
     * @since  0.2.0
     *
     * @param  array  $args
     * @param  mixed $parent
     */
    public function __construct( $args = [], $parent = null ) {
        $this->fix_deprecated();

        if ( ! empty( $args ) ) {
            $this->args = $args;
        }

        $this->submodels = (object)[];

        $this->parent = $parent;
    }

    /**
     * Get model's arguments
     * 
     * @type  function
     * @date  3/6/2016
     * @since 0.4.0
     *
     * @return array
     */
    public function get_args() {
        return $this->args;
    }

    /**
     * Set the arguments for a model
     *
     * @type  function
     * @date  26/1/2018
     * @since 1.11.0
     * 
     * @param [type] $args
     * @return void
     */
    public function set_args( $args ) {
        $this->args = $args;
    }

    /**
     * Get the data from this model after fetch_data() has been run.
     * 
     * @type  function
     * @date  3/6/2016
     * @since 0.4.0
     *
     * @return void
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Get the instance of an instantiated submodel
     * 
     * @type  function
     * @date  3/6/2016
     * @since 0.4.0
     *
     * @return \Dustpress\Model
     */
    public function get_submodel( $name ) {
        return $this->submodels->{$name};
    }

    /**
     * Get all instantiated submodels for this model as an array
     * 
     * @type  function
     * @date  3/6/2016
     * @since 0.4.0
     *
     * @return array
     */
    public function get_submodels() {
        return $this->submodels;
    }

    /**
     * Get the ancestor of this model.
     * 
     * @type  function
     * @date  3/6/2016
     * @since 0.4.0
     *
     * @return \Dustpress\Model
     */
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
     * This function ensures deprecated functionalities will not break the model.
     *
     * @type   function
     * @date   16/02/2017
     * @since  1.5.5
     */
    public function fix_deprecated() {
        // Reassign deprecated "allowed_functions" to "api".
        if ( isset( $this->allowed_functions ) ) {
            error_log('DustPress: Model property "allowed_functions" is deprecated, use "api" instead.');
            $this->api = $this->allowed_functions;
        }
    }

    /**
     * This function gets the data from models and binds it to the global data structure.
     *
     * @type   function
     * @date   15/10/2015
     * @since  0.2.0
     *
     * @param string $functions
     * @param bool   $tidy
     *
     * @return mixed
     */
    public function fetch_data( $functions = null, $tidy = false ) {
        $this->class_name = get_class( $this );

        // Create a place to store the wanted data in the global data structure.
        if ( ! isset( $this->data[ $this->class_name ] ) ) $this->data[ $this->class_name ] = new \stdClass();

        // Fetch all methods from given class and in its parents.
        $methods = $this->get_class_methods( $this->class_name );

        $method_names = [];

        // If a method has been overridden, remove duplicate instances so that they won't get run twice.
        foreach ( $methods as $model => $values ) {
            foreach ( $values as $key => $value ) {
                if ( isset( $method_names[ $value ] ) ) {
                    unset( $methods[ $model ][ $key ] );
                }
                else {
                    $method_names[ $value ] = true;
                }
            }
        }

        unset( $method_names );

        // Check that all asked functions exist
        if ( is_array( $functions ) && count( $functions ) > 0 ) {
            foreach ( $functions as $function ) {
                if ( ! $this->in_array_r( $function, $methods ) ) {
                    die( json_encode( [ "error" => "Method '". $function ."' is not allowed to be run via AJAX or does not exist." ] ) );
                }
            }
        }

        // If we are on an AJAX call, we may want to run some private or protected functions too
        $private_methods = [];

        // Loop through the methods
        foreach( $methods as $class => &$class_methods ) {
            foreach( $class_methods as $index => $method_item ) {
                $reflection = new \ReflectionMethod( $class, $method_item );

                // If we have wanted list of functions, check if we can run them and don't run
                // anything else.
                if ( is_array( $functions ) && count( $functions ) > 0 ) {
                    if ( ! $this->in_array_r( $method_item, $functions ) ) {
                        continue;
                    }
                    else {
                        if ( ! $this->is_function_allowed( $method_item ) ) {
                            die( json_encode( [ "error" => "Method '". $method_item ."' is not allowed to be run via AJAX or does not exist." ] ) );
                        }
                        else if ( $reflection->isProtected() || $reflection->isPrivate() ) {
                            $private_methods[] = $method_item;
                            continue;
                        }
                        else {
                            // If the method has parameters, it should be run manually
                            if ( $reflection->getNumberOfParameters() > 0 ) {
                                unset( $class_methods[ $index ] );
                            }
                            else {
                                $class_methods[ $index ] = array( $class, $method_item );
                            }
                        }
                    }
                }
                else {
                    if ( $reflection->isPublic() ) {
                        // If the method has parameters, it should be run manually
                        if ( $reflection->getNumberOfParameters() > 0 ) {
                            unset( $class_methods[ $index ] );
                        }
                        else {
                            $class_methods[ $index ] = array( $class, $method_item );
                        }
                    }
                    else {
                        unset( $class_methods[ $index ] );
                    }
                }
            }
        }

        // Add some filters
        $methods = apply_filters( "dustpress/methods", $methods, $this->class_name );
        $private_methods = apply_filters( "dustpress/private_methods", $private_methods, $this->class_name );

        // If we want tidy output, init variable for that
        if ( $tidy ) {
            $tidy_data = (object)[];
        }

        $methods = array_reverse( $methods );

        // Loop through all public methods and run the ones we wanted to deliver the data to the views.
        foreach( $methods as $class => $class_methods ) {
            foreach( $class_methods as $name => $m ) {
                if ( is_array( $m ) ) {
                    if ( isset( $m[1] ) && is_string( $m[1] ) ) {
                        if ( $m[1] == "__construct" ) {
                            continue;
                        }

                        $method = str_replace( "bind_", "", $m[1] );

                        if ( ! isset( $this->data[ $this->class_name ] ) ) {
                            $this->data[ $this->class_name ] = (object)[];
                        }

                        $data = $this->run_function( $m[1], $class );

                        if ( $tidy ) {
                            $tidy_data->{ $m[1] } = $data;
                        }
                        else {
                            if ( ! is_null( $data ) ) {
                                $content = (array) $this->data[ $this->class_name ];
                                $content[ $method ] = $data;
                                $this->data[ $this->class_name ] = (object) $content;
                            }
                        }
                    }
                }
                else if ( is_callable( $m ) ) {
                    if ( $m == "__construct" ) {
                        continue;
                    }

                    $method = str_replace( "bind_", "", $m );

                    if ( ! isset( $this->data[ $this->class_name ]->{ $method } ) ) {
                        $this->data[ $this->class_name ]->{ $method } = [];
                    }

                    $data = $this->run_function( $m, $class );

                    if ( ! is_null( $data ) ) {
                        if ( $tidy ) {
                            $tidy_data->{ $method } = $data;
                        }
                        else {
                            $this->data[ $this->class_name ]->{ $method } = $data;
                        }
                    }
                }

                if ( $this->terminated == true ) {
                    break 2;
                }
            }

            unset( $class_methods );
        }

        // If there are private methods to run, run them too.
        if ( is_array( $private_methods ) && count( $private_methods ) > 0 ) {
            foreach( $private_methods as $method ) {
                $data = $this->run_restricted( $method );

                if ( ! is_null( $data ) ) {
                    if ( $tidy ) {
                        $tidy_data->{ $method } = $data;
                    }
                    else {
                        $content = (array) $this->data[ $this->class_name ];
                        $content[ $method ] = $data;
                        $this->data[ $this->class_name ] = (object) $content;
                    }
                }
            }
        }

        if ( $tidy ) {
            $this->data = $tidy_data;
            return $tidy_data;
        }
        else {
            return $this->data[ $this->class_name ];
        }
    }

    /**
     * This function returns all public methods from current class and it parents up to
     * but not including Model.
     *
     * @type   function
     * @date   19/3/2015
     * @since  0.0.1
     *
     * @param  string $class_name
     * @param  array $methods
     * @return $methods (array)
     */
    private function get_class_methods( $class_name, $methods = array() ) {
        $rc = new \ReflectionClass( $class_name );
        $rmpu = $rc->getMethods();

        if ( isset( $methods ) ) {
            if ( ! isset( $methods[ $class_name ] ) ) {
                $methods[ $class_name ] = array();
            }
        }
        else {
            $methods = array();
        }

        foreach ( $rmpu as $r ) {
            if ( $r->class === $class_name ) {
                $methods[ $class_name ][] = $r->name;
            }
        }

        $parent = get_parent_class( $class_name );

        if ( ! empty( $parent ) && 'DustPress\Model' !== $parent ) {
            $methods = $this->get_class_methods( $parent, $methods );
        }

        return $methods;
    }

    /**
     * This function checks if a bound submodel is wanted to run and if it is, runs it.
     *
     * @type   function
     * @date   17/3/2015
     * @since  0.0.1
     *
     * @param  $name (string), $args (array), $cache_sub (boolean)
     */
    public function bind_sub( $name, $args = null, $cache_sub = true ) {
        if ( $this->terminated == true ) {
            return;
        }

        $this->class_name = get_class( $this );
        if ( is_string( $name ) ) {
            $model = new $name( $args, $this );
        }
        else {
            throw new \Exception("DustPress error: bind_sub was called with invalid class name: " . print_r( $name, true ) );
        }

        // If the submodel is not on the root level, set it under the current submodel.
        if ( $this->parent ) {
            $data = $model->fetch_data();
            $class_name = $model->class_name;

            if ( isset( $this->data[ $this->class_name ]->{ $class_name } ) ) {
                $this->data[ $this->class_name ]->{$class_name } = array_merge( (array)$this->data[ $this->class_name ]->{ $class_name }, (array)$data );
            }
            else {
                $this->data[ $this->class_name ]->{ $class_name } = $data;
            }
        }
        // Set submodel under the main model.
        else {
            $data = $model->fetch_data();
            $class_name = $model->class_name;

            if ( isset( $this->data[ $class_name ] ) ) {
                $this->data[ $class_name ] = array_merge( (array)$this->data[ $class_name ], (array)$data );
            }
            else {
                $this->data[ $class_name ] = $data;
            }
        }

        if ( ! is_object( $this->submodels ) ) {
            $this->submodels = (object)[];
        }

        $this->submodels->{ $name } = $model;


        // Store called submodels for caching purposes.
        if ( $cache_sub ) {
            if ( empty( $this->called_subs ) ) {
                $this->called_subs = [];
            }
            $this->called_subs[] = [ 'class_name' => $name, 'args'  => $args ];
        }

        if ( $model->terminated == true ) {
            $this->terminate();
        }
    }

    /**
     *   This function binds the data from the models to the global data structure.
     *   It takes the data key as second parameter and optional data block name as third.
     *
     *   @type   function
     *   @date   17/3/2015
     *   @since  0.0.1
     *
     *   @param  N/A $data
     *   @param  string $key
     *   @param  string $model
     *   @return true/false (boolean)
     */
    public function bind( $data, $key = null, $model = null ) {
        if ( ! $key ) {
            die("DustPress error: You need to specify the key if you use bind(). Use return if you want to use the function name.");
        }

        if ( ! isset( $this->class_name ) ) {
            $this->class_name = get_class( $this );
        }

        if ( $model ) {
            // Create a place to store the wanted data in the global data structure.
            if ( ! isset( $this->data[ $model ] ) ) $this->data[ $model ] = new \stdClass();

            if ( ! isset( $this->data[ $model ] ) ) {
                $this->data[ $model ] = (object)[];
            }
            $this->data[ $model ]->{ $key } = $data;
        }
        else {
            // Create a place to store the wanted data in the global data structure.
            if ( ! isset( $this->data[ $this->class_name ] ) ) $this->data[ $this->class_name ] = new \stdClass();

            if ( ! $this->parent ) {
                if ( is_array( $data ) ) {
                    if ( isset( $this->data[ $this->class_name ]->{ $key } ) ) {
                        $this->data[ $this->class_name ]->{ $key } = array_merge( (array) $this->data[ $this->class_name ]->{ $key }, $data );
                    }
                    else {
                        $this->data[ $this->class_name ]->{ $key } = $data;
                    }
                }
                else {
                    $this->data[ $this->class_name ]->{ $key } = $data;
                }
            }
            else {
                $this->data[ $this->class_name ]->{ $key } = $data;
            }
        }
    }

    /**
     * This function returns the desired Dust template, if the developer has defined one instead of default. Otherwise return false.
     *
     * @type   function
     * @date   15/10/2015
     * @since  0.2.0
     *
     * @param  N/A
     * @return mixed
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
     * This function lets the developer to set the template to be used to render a page.
     *
     * @type   function
     * @date   15/10/2015
     * @since  0.2.0
     *
     * @param  string $template
     */
    public function set_template( $template ) {
        $ancestor = $this->get_ancestor();

        if ( $template ) {

            if ( $this === $ancestor ) {
                $this->template = $template;
            }
            else {
                $ancestor->set_template( $template );
            }
        }
    }

    /**
     * run_function
     *
     * This function checks whether data exists in cache (if cache is enabled)
     * and returns the data or runs the function and returns its return data.
     *
     * @type   function
     * @date   29/01/2016
     * @since  0.3.1
     *
     * @param  string $m
     *
     * @return mixed
     */
    private function run_function( $m, $class = null ) {
        $cached = $this->get_cached( $m );

        if ( is_null( $class ) ) {
            $class = $this->class_name;
        }

        if ( $cached ) {
            //error_log('this is a cache: ' . $m);
            return $cached;
        }

        $reflection = new \ReflectionMethod( $class, $m );

        if ( $reflection->isStatic() ) {
            $data = call_user_func( $class . '::' . $m );
        }
        else {
            $data = call_user_func( [ $this, $m ] );
        }

        if ( isset( $this->called_subs ) ) {
            $subs = $this->called_subs;
        }
        else {
            $subs = null;
        }

        $this->maybe_cache( $m, $data, $subs );

        // Unset called submodels for this run
        $this->called_subs = null;

        return $data;
    }

    /**
     * This function checks if the function is defined as cacheable and returns the cache if it exists.
     *
     * @type   function
     * @date   29/01/2016
     * @since  0.3.1
     *
     * @param  string $m
     *
     * @return mixed|bool
     */
    private function get_cached( $m ) {

        if ( ! dustpress()->get_setting('cache') ) {
            return false;
        }

        $args       = $this->get_args();
        $this->hash = $this->generate_cache_key( $this->class_name, $args, $m );

        $cached = get_transient( $this->hash );

        if ( false === $cached ) {
            return false;
        }

        // Run stored submodel calls
        if ( isset( $cached->subs ) && is_array( $cached->subs ) ) {
            foreach ( $cached->subs as $sub_data ) {
                // Run submodel without cacheing it.
                $this->bind_sub( $sub_data['class_name'], $sub_data['args'], false );
            }
        }

        return $cached->data;
    }

    /**
     * This function stores data sets to transient cache if it is enabled
     * and indexes cache keys for model-function-pairs.
     *
     * @type   function
     * @date   29/01/2016
     * @since  0.3.1
     *
     * @param  string $m
     * @param  mixed $data
     * @param  array $subs
     */
    private function maybe_cache( $m, $data, $subs ) {

        // Check whether cache is enabled and model has ttl-settings.
        if ( ! dustpress()->get_setting('cache') || ! $this->is_cacheable_function( $m ) ) {
            return;
        }

        // If no hash key exists, bail out
        if ( empty( $this->hash ) ) {
            return;
        }

        // Extend data with submodels
        $to_cache = (object)[ 'data' => $data, 'subs' => $subs ];

        set_transient( $this->hash, $to_cache, $this->ttl[ $m ] );

        // Index key for cache clearing
        $index      = $this->generate_cache_key( $this->class_name, $m );
        $hash_index = get_transient( $index );

        if ( ! is_array( $hash_index ) ) {
            $hash_index = [];
        }

        // Set the data hash key to the index array of this model function
        if ( ! in_array( $this->hash, $hash_index ) ) {
            $hash_index[] = $this->hash;
        }

        // Store transient for 30 days
        set_transient( $index, $hash_index, 30 * DAY_IN_SECONDS );
    }

    /**
     *  Checks whether the function is to be cached.
     *
     *  @param  $m (string), $ttl (array)
     *  @return (boolean)
     */
    private function is_cacheable_function( $m ) {
        // No caching set
        if ( ! empty( $this->ttl ) && ! is_array( $this->ttl ) ) {
            return false;
        }
        if ( ! empty( $this->ttl ) && is_array( $this->ttl ) ) {
            foreach ( $this->ttl as $key => $val ) {
                if ( $m === $key ) {

                    return true;
                }
            }
        }
        return false;
    }

    /**
     * This functions returns true if asked private or protected functions is
     * allowed to be run via the run wrapper.
     *
     * @type   function
     * @date   17/12/2015
     * @since  0.3.0
     *
     * @param   string $function
     * @return  $allowed (boolean)
     */
    private function is_function_allowed( $function ) {
        if ( ! defined('DOING_AJAX') ) {
            $reflection = new \ReflectionMethod( $this, $function );
            if ( $reflection->isPublic() ) {
                return true;
            }
            else {
                return false;
            }
        }
        else if ( isset( $this->api ) && is_array( $this->api ) && in_array( $function, $this->api ) ) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * This functions creates a cache key hash from parameters.
     *
     * @type   function
     * @date   17/12/2015
     * @since  0.3.0
     *
     * @param  ellipsis $args
     * @return $key (string)
     */
    private function generate_cache_key() {
        $args = func_get_args();
        $seed = '';

        foreach( $args as $arg ) {
            $seed .= serialize( $arg );
        }

        $seed = apply_filters( 'dustpress/cache/seed', $seed );

        return sha1( $seed );
    }

    /**
     * This function runs a restricted function if it exists in the allowed functions
     * and returns whatever the wanted function returns.
     *
     * @type   function
     * @date   17/12/2015
     * @since  0.3.0
     *
     *
     * @param   string $function
     * @param   array $args
     * @return mixed
     */
    public function run_restricted( $function ) {
        if ( $this->is_function_allowed( $function ) ) {
            return $this->run_function( $function );
        }
        else {
            return (object)["error" => "Wanted function does not exist in the allowed functions list."];
        }
    }

    /**
     * Rename current model's data block. Probably for template changing purposes.
     *
     * @type   function
     * @date   14/09/2016
     * @since  1.2.0
     *
     *
     * @param   string $function
     * @param   array $args
     * @return mixed
     */
    protected function rename_model( $name ) {
        $original         = $this->class_name;

        if ( $original === $name ) {
            return;
        }

        $this->class_name = $name;

        if ( isset( $this->data[ $original ] ) ) {
            $this->data[ $name ] = $this->data[ $original ];
            unset( $this->data[ $original ] );
        }
        else if ( ! isset( $this->data[ $name ] ) ) {
            $this->data[ $name ] = (object)[];
        }
    }

    /**
     * A recursive array search.
     *
     * @param  mixed    $needle
     * @param  array    $haystack
     * @param  boolean  $strict
     * @return boolean
     */
    protected function in_array_r($needle, $haystack, $strict = true) {
        foreach ($haystack as $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict))) {
                return true;
            }
        }
        return false;
    }

    public function terminate() {
        $this->terminated = true;
    }

    public function get_terminated() {
        return $this->terminated;
    }
}
