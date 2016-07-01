<?php
namespace DustPress;

class Set extends Helper {
    private $allowed_methods = [
        "add",
        "subtract",
        "multiply",
        "divide",
        "mod"
    ];

    public function init() {
        // Let's find the root of the data tree to store our variable there
        $root = $this->find_root( $this->context );

        // Key is a mandatory parameter
        if ( isset( $this->params->key ) ) {
            $key = $this->params->key;
        } else {
            return $this->chunk->write( 'DustPress set helper error: No key specified.' );
        }

        // It also must be a string
        if ( ! is_string( $key ) ) {
            return $this->chunk->write( 'DustPress set helper error: Key is not a string.' );
        }

        // At least one of the actions we need should be there
        if ( ! $this->action_specified() ) {
            return $this->chunk->write( 'DustPress set helper error: No action specified.' );
        } else {
            // Set a specific value to a variable
            if ( isset( $this->params->value ) ) {
                if ( is_array( $root->head->value ) ) {
                    $root->head->value[ $key ] = $this->params->value;              
                }
                else if ( is_object( $root->head->value ) ) {
                    $root->head->value->{$key} = $this->params->value;
                }
            }
            // Perform mathematic operations on a variable
            else {
                foreach( $this->allowed_methods as $method ) {
                    if ( isset( $this->params->{$method} ) ) {
                        $this->method( $root, $key, $this->params->{$method}, $method );
                        break;
                    }
                }
            }
        }
        
        return $this->chunk;
    }

    // Do we have an action that we need?
    private function action_specified() {
        foreach( $this->allowed_methods as $method ) {
            if ( isset( $this->params->{$method} ) ) {
                return true;
            }
        }

        if ( isset( $this->params->value ) ) {
            return true;
        }

        return false;
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

    // Perform mathematic operations
    private function method( &$root, $key, $value, $method ) {
        if ( ! is_numeric( $value ) ) {
            return $this->chunk->write( 'DustPress set helper error: '. $method .' value is not a number.' );
        }

        // If there is no such variable, define it 
        if ( is_array( $root->head->value ) && ! isset( $root->head->value[ $key ] ) ) {
            $root->head->value[ $key ] = 0;
        }
        if ( is_object( $root->head->value ) && ! isset( $root->head->value->{$key} ) ) {
            $root->head->value->{$key} = 0;
        }

        switch( $method ) {
            case 'add':
                if ( is_array( $root->head->value ) ) {
                    $root->head->value[ $key ] += $value;
                }
                else if ( is_object( $root->head->value ) ) {
                    $root->head->value->{$key} += $value;   
                }
                break;
            case 'subtract':
                if ( is_array( $root->head->value ) ) {
                    $root->head->value[ $key ] -= $value;
                }
                else if ( is_object( $root->head->value ) ) {
                    $root->head->value->{$key} -= $value;   
                }
                break;
            case 'multiply':
                if ( is_array( $root->head->value ) ) {
                    $root->head->value[ $key ] *= $value;
                }
                else if ( is_object( $root->head->value ) ) {
                    $root->head->value->{$key} *= $value;   
                }
                break;
            case 'divide':
                if ( is_array( $root->head->value ) ) {
                    $root->head->value[ $key ] /= $value;
                }
                else if ( is_object( $root->head->value ) ) {
                    $root->head->value->{$key} /= $value;   
                }
                break;
            case 'mod':
                if ( is_array( $root->head->value ) ) {
                    $root->head->value[ $key ] %= $value;
                }
                else if ( is_object( $root->head->value ) ) {
                    $root->head->value->{$key} %= $value;   
                }
                break;
        }
    }
}

$this->add_helper( 'set', new Set() );