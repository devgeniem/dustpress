<?php
namespace DustPress;

class Strtodate extends Helper {
    public function output() {
		$value 	= $params->value;
		$format	= $params->format;
		$now	= $params->now;
		
		return date( $format, strtotime( $value, $now ) );
    }
}

$this->add_helper( 'strtodate', new Strtodate() );