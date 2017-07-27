<?php
namespace DustPress;

class Strtodate extends Helper {
    public function output() {
		$value 	= $this->params->value;
	    	if ( isset( $this->params->format ) ) {
			$format	= $this->params->format;
		} else {
			$format = get_option( 'date_format' );
		}
		$now	= $this->params->now;
		
		return date_i18n( $format, strtotime( $value, $now ) );
    }
}

$this->add_helper( 'strtodate', new Strtodate() );
