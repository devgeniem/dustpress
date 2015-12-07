<?php

class Index extends DustPressModel {
	public function bind_submodules() {
		// Include header in the page
		$this->bind_sub("Header");

		// Include footer in the page
		$this->bind_sub("Footer");
	}
}