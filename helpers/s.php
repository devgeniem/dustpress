<?php
namespace DustPress;

/**
 * s helper
 *
 * Runs strings through WordPress translate functions and prints them.
 */
class S extends Helper {
	/**
	 * The output method itself.
	 *
	 * @return string
	 */
	public function output() {
		if ( isset( $this->params->no_entities ) ) {
			return $this->translate();
		}

		return \htmlentities( $this->translate(), ENT_QUOTES );
	}

	/**
	 * The heavy-lifting method.
	 *
	 * @return string
	 */
    private function translate() {
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