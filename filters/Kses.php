<?php
namespace DustPress;

class wpKsesPost implements \Dust\Filter\Filter {
    public function apply( $item ) {
        if( ! is_string( $item ) ) {
            return $item;
        }

        return \wp_kses_post( $item );
    }
}

$this->add_filter( 'kses', new wpKsesPost() );
