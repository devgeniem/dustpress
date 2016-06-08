<?php
namespace DustPress;

class Title extends Helper {
    public function output() {
		ob_start();
		the_title();
		return ob_get_clean();
    }
}

$this->add_helper( 'title', new Title() );