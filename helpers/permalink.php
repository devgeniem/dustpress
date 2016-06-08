<?php
namespace DustPress;

class Permalink extends Helper {
    public function output() {
    	if ( isset( $this->params->id ) ) {
    		return get_permalink( $this->params->id );
    	}
    	else {
    		return get_permalink();
    	}
	}
}

$this->dust->helpers['permalink'] = new Permalink();