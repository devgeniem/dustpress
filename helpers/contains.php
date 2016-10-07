<?php
/**
 * The Contains helper
 */
namespace DustPress;

/**
 * This helper extends the DustPHP Comparison class and provides
 * a functionality to check whether a given value is in an array/object.
 */
class Contains extends \Dust\Helper\Comparison {

    /**
     * Implements the isValid function of the Comparison class.
     * Checks whether a given value is in an array/object.
     *
     * @param  object/array $key   The array/object to look from.
     * @param  any          $value The value to look for.
     * @return boolean
     */
    public function isValid( $key, $value ) {
        $haystack = (array) $key;
        return in_array( $value, $haystack );
    }
}

$this->add_helper( 'contains', new Contains() );
