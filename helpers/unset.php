<?php
namespace DustPress;

class Unset extends Helper {
    public function init() {
        // Let's find the root of the data tree to store our variable there
        $root = $this->find_root( $this->context );

        // Key is a mandatory parameter
        if ( isset( $this->params->key ) ) {
            $key = $this->params->key;
        } else {
            return $this->chunk->write( 'DustPress unset helper error: No key specified.' );
        }

        // It also must be a string
        if ( ! is_string( $key ) ) {
            return $this->chunk->write( 'DustPress unset helper error: Key is not a string.' );
        }

        if ( is_array( $root->head->value ) ) {
            unset( $root->head->value[ $key ] );
        }
        else if ( is_object( $root->head->value ) ) {
            unset( $root->head->value->{$key} );
        }
        
        return $this->chunk;
    }

    // Recursive function to find the root of the data tree
    private function find_root( $ctx ) {
        if ( isset( $ctx->parent ) ) {
            return $this->find_root( $ctx->parent );
        }
        else {
            return $ctx;
        }
    }
}

$this->add_helper( 'unset', new Unset() );