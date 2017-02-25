<?php
/**
 * This class wraps all the cache functionalities with DustPress.
 * To override these functionalities, create a your own DustPress\Cache
 * class and require it before DustPress is initalized within your theme.
 */
namespace DustPress;

/**
 * Class Cache
 *
 * @package DustPress
 */
class Cache {

    /**
     * The prefix for cache keys.
     *
     * @var string
     */
    protected static $cache_prefix = 'dp_cached_';

    /**
     * Cache constructor.
     * This class is not to be constructed.
     */
    protected function __construct() {
    }

    /**
     * This function checks if the function is defined as cacheable and returns the cache if it exists.
     *
     * @type   function
     * @date   25/02/2017
     * @since  1.5.12
     *
     * @param  string $method The function name.
     * @param  Model  $model  The model instance.
     * @return N/A
     */
    public static function get_cached( $method, $model ) {

        if ( ! dustpress()->get_setting('cache') ) {
            return false;
        }

        $args = $model->get_args();
        $hash = self::generate_cache_key( $model->class_name, $args, $method );
        $model->set_hash( $hash );

        $cached = get_transient( $hash );

        if ( false === $cached ) {
            return false;
        }

        // Run stored submodel calls
        if ( isset( $cached->subs ) && is_array( $cached->subs ) ) {
            foreach ( $cached->subs as $sub_data ) {
                // Run submodel without caching it.
                $model->bind_sub( $sub_data['class_name'], $sub_data['args'], false );
            }
        }

        return $cached->data;
    }

    /**
     * This function stores data sets to transient cache if it is enabled
     * and indexes cache keys for model-function-pairs.
     *
     * @type   function
     * @date   25/02/2017
     * @since  1.5.12
     *
     * @param  string $method The function name.
     * @param  Model  $model  The model instance.
     * @param  string $hash   The cache hash key.
     *
     * @return boolean  True if the data was cached, false if not.
     */
    public function maybe_cache( $method, $model ) {
        // Check whether cache is enabled and model has ttl-settings.
        if ( dustpress()->get_setting( 'cache' ) && $this->is_cacheable_function( $method ) ) {

            if ( isset( $model->called_subs ) ) {
                $subs = $model->called_subs;
            }
            else {
                $subs = null;
            }

            // Extend data with submodels
            $to_cache = (object) [ 'data' => $data, 'subs' => $subs ];
            set_transient( $hash, $to_cache, $this->ttl[ $method ] );
            // If no hash key exists, bail
            if ( ! isset( $hash ) ) {
                return false;
            }
            // Index key for cache clearing
            $index      = self::generate_cache_key( $this->class_name, $method );
            $hash_index = get_transient( $index );
            if ( ! is_array( $hash_index ) ) {
                $hash_index = [];
            }
            // Set the data hash key to the index array of this model function
            if ( ! in_array( $hash, $hash_index ) ) {
                $hash_index[] = $this->hash;
            }
            // Store transient for 30 days
            set_transient( $index, $hash_index, 30 * DAY_IN_SECONDS );

            return true;
        }

        return false;
    }

    /**
     * Checks whether the function is to be cached.
     *
     * @type   function
     * @date   25/02/2017
     * @since  1.5.12
     *
     * @param  string $method The function name.
     * @param  Model  $model  The model instance.
     *
     * @return boolean
     */
    public static function is_cacheable_function( $method, $model ) {
        // No caching set
        if ( ! is_array( $model->ttl ) ) {
            return false;
        }
        if ( is_array( $model->ttl ) ) {
            foreach ( $model->ttl as $key => $val ) {
                if ( $method === $key ) {
                    if ( is_integer( $val ) ) {
                        return true;
                    }
                    if ( is_array( $val ) ) {
                        if ( isset( $val[0] ) &&
                             isset( $val[1] ) &&
                             is_string( $val[0] ) &&
                             is_int( $val[1] )
                        ) {
                            return true;
                        }
                    } else {
                        error_log( "DustPress: The ttl settings for '$method' are invalid." );
                        return false;
                    }
                }
            }
        }

        return false;
    }

    /**
     * This functions creates a cache key hash from parameters.
     *
     * @type   function
     * @date   17/12/2015
     * @since  0.3.0
     *
     * @param  ellipsis $args Pass any number of parameters.
     * @return string   $key  The generated hash key.
     */
    public static function generate_cache_key() {
        $args = func_get_args();
        $seed = '';

        foreach( $args as $arg ) {
            $seed .= serialize( $arg );
        }

        return sha1( $seed );
    }
}