<?php
namespace DustPress;

class Not extends \Dust\Helper\Comparison
{
    public function isValid($key, $value) {
        return $key != $value;
    }

}

$this->dust->helpers['not'] = new Not();