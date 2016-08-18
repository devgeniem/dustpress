<?php
namespace DustPress;

class Data {
	/**
	 * A filter to alter the data of an ACF Flexible Content component.
	 * @param  mixed $data
	 * @return mixed
	 */
	public static function component_invoke( $data ) {
		self::component_handle( $data );

		return $data;
	}

	public static function component_handle( &$data, $fields = false ) {
		if ( is_array( $data ) || is_object( $data ) ) {
	        foreach ( (array) $data as $key => $item ) {
	            if ( "fields" == $key ) {
	                if ( is_array( $item ) && isset( $item["acf_fc_layout"] ) ) {
	                	if ( is_array( $data ) ) {
	                    	$data[ $key ] = apply_filters( "dustpress/data/component=" . $item["acf_fc_layout"], $item );
	                    }
	                    else {
	                    	$data->{$key} = apply_filters( "dustpress/data/component=" . $item["acf_fc_layout"], $item );	
	                    }
	                }
	                
	                if ( is_array( $data ) ) {
	                	self::component_handle( $data[ $key ], true );
	                }
	                else {
	                	self::component_handle( $data->{$key}, true );
	                }
	            }
	            else {
	                if ( is_array( $data ) ) {
	                	self::component_handle( $data[ $key ], true );
	                }
	                else if ( $data instanceof stdClass || $data instanceof WP_Post ) {
	                	self::component_handle( $data->{$key}, true );
	                }
	            }
	        }
	    }
	}
}