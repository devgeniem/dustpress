<?php

class DustPress {
	public function __construct( $parent = null, $args = null, $is_main = false ) {
		if ( "DustPress" === $this->get_class() ) {
			$this = new DustPress_Core();
		}
		else {
			$this = new DustPress_Model( $args, $parent );
		}
	}

	public static function get_class() {
		return get_called_class();
	}
}