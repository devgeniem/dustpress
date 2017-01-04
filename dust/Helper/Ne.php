<?php
namespace Dust\Helper;

class Ne extends Comparison
{
    public function isValid($key, $value) {
        return $key != $value;
    }

}
