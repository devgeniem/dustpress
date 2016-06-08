<?php
namespace DustPress;

class WPHead extends Helper {
    public function output() {
		ob_start();
		wp_head();
		return ob_get_clean();
    }
}

$this->add_helper( 'wphead', new WPHead() );