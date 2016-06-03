<?php
namespace DustPress;

class S extends Helper
{
    public function output() {
		if ( $this->params->s ) {
			return __( $this->params->s );
		}
		else {
			return __('Helper missing parameter "s".');
		}
    }
}

$this->add_helper( "s", new S() );
//$this->dust->helpers['s'] = new S();