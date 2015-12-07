<?php

class Page extends DustPressModel {
	public function bind_submodules() {
		// Include header in the page
		$this->bind_sub("Header");

		// Include footer in the page
		$this->bind_sub("Footer");
	}

	public function bind_content() {

		// Create a DustPressHelper instance
		$dp = new DustPressHelper();

		// Fetch the post object
		$post = $dp->get_post( get_the_ID() );

		$this->bind_data( $post );
	}
}