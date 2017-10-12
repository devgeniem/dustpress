<?php
namespace DustPress;

class S extends Helper
{
    public function output() {
		if ( isset( $this->params->s ) ) {
			if ( isset( $this->params->td ) ) {
				if ( isset( $this->params->x ) ) {
					return _x( $this->params->s, $this->params->x, $this->params->td );
				}
				else {
					return __( $this->params->s, $this->params->td );
				}
			}
			else if ( isset( $this->params->x ) ) {
				return _x( $this->params->s, $this->params->x );
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