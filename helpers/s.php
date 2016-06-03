<?php
class S_DP extends \DustPress\Helper
{
    public function output() {
        if ( $this->bodies->dummy !== true ) {
			if ( $this->params->s ) {
				return __( $this->params->s );
			}
			else {
				return __('Helper missing parameter "s".');
			}
		}
    }
}

$this->dust->helpers['s'] = new S_DP();