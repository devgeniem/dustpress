<?php
namespace DustPress;

class escUrl implements \Dust\Filter\Filter {
    public function apply( $item ) {
        if( ! is_string( $item ) ) {
            return $item;
        }

        return \esc_url( $item );
    }
}

$this->add_filter( 'url', new escUrl() );
