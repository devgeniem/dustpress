<?php
namespace DustPress;

class S extends Helper
{
    public function output() {
		if ( isset( $this->params->s ) ) {
			if ( isset( $this->params->td ) ) {
				return __( $this->params->s, $this->params->td );
			}
			else {
				return __( $this->params->s );
			}
		}
		else {
			return __('Helper missing parameter "s".');
		}
    }
}

$this->add_helper( "s", new S() );