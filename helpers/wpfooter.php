<?php
namespace DustPress;

class WPFooter extends Helper {
    public function output() {
		ob_start();
		wp_footer();
		return ob_get_clean();
    }
}

$this->add_helper( 'wpfooter', new WPFooter() );