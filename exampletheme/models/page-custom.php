<?php
/*
Template name: Custom
*/

class PageCustom extends DustPress {
	private $variable;

	public function bind_content() {

		// Create a DustPressHelper instance
		$dp = new DustPressHelper();

		// Fetch the post object
		$args = [ 'recursive' => true ];
		$post = $dp->get_acf_post( get_the_ID(), $args );

		// Here we just want to save some variable to $variable
		$this->variable = "Some variable";

		$this->bind_data( $post );
	}

	public function bind_submodules() {
		// Include header in the page
		$this->bind_sub("Header");

		// Include footer in the page, but give it $variable that
		// we included in bind_content()!
		$this->bind_sub("Footer", [ "variable" => $this->variable ] );
	}
}