<?php
namespace DustPress;

class escAttr implements \Dust\Filter\Filter {
    public function apply( $item ) {
        if( ! is_string( $item ) ) {
            return $item;
        }

        return \esc_attr( $item );
    }
}

$this->add_filter( 'attr', new escAttr() );
