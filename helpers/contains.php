<?php
namespace DustPress;

class Contains extends \Dust\Helper\Comparison
{
    public function isValid($key, $value) {
    	if ( is_array( $key ) ) {
        	return in_array( $value, $key );
        }
        else {
        	return false;
        }
    }
}

$this->add_helper( "contains", new Contains() );