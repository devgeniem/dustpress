<?php
namespace DustPress;

class Get extends Helper {
    public function output() {
        if ( isset( $this->params->object ) ) {
            $object = $this->params->object;
        }
        else {
            return 'DustPress get helper error: No object specified.';
        }

        if ( isset( $this->params->key ) ) {
            $key = $this->params->key;
        } else {
            return 'DustPress get helper error: No key specified.';
        }

        if ( is_object( $object ) ) {
            if ( isset( $object->{ $key } ) ) {
                return $object->{ $key };
            }
        }

        if ( is_array( $object ) ) {
            if ( isset( $object[ $key ] ) ) {
                return $object[ $key ];
            }
        }
    }
}

$this->add_helper( 'get', new Get() );