<?php
namespace DustPress;

class escHTML implements \Dust\Filter\Filter {
    public function apply( $item ) {
        if( ! is_string( $item ) ) {
            return $item;
        }

        return \esc_html( $item );
    }
}

$this->add_filter( 'html', new escHTML() );
