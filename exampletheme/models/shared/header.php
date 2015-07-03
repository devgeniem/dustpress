<?php
class Header extends DustPress {
	public function bind_SomeData() {
		$this->bind_data("This is the header.");
	}

	public function bind_DataToFooter() {
		// We may want to put data in footer too

		$random = [
			"Something" => "This comes from the header model!",
			"Great" => "How great is that!"
		];

		$this->bind_data( $random, "Something", "Footer" );
	}
}