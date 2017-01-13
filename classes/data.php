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
                // prevent null bytes from raising notices, we don't need them anyway
                $key = str_replace( chr(0), "", $key );

                if ( is_array( $item ) && isset( $item["acf_fc_layout"] ) ) {
                    if ( is_array( $data ) ) {
                        $data[ $key ] = apply_filters( "dustpress/data/component=" . $item["acf_fc_layout"], $item );
                    }
                }
                else if ( is_array( $item ) && $key == "fields" ) {
                    foreach ( $item as $item_key => &$item_data ) {
                        $item_data['c'][0] = apply_filters( "dustpress/data/component=" . $item_key, $item_data['c'][0] );
                    }

                    if ( is_array( $data ) ) {
                        $data[ $key ] = $item;
                    }
                    else if ( get_class($data) == "stdClass" || get_class($data) == "WP_Post" ) {
                        $data->{ $key } = $item;
                    }
                }
                else {
                    if ( is_array( $data ) ) {
                        self::component_handle( $data[ $key ], true );
                    }
                    else if ( get_class($data) == "stdClass" || get_class($data) == "WP_Post" ) {
                        self::component_handle( $data->{$key}, true );
                    }
                }
            }
        }
    }
}