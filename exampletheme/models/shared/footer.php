<?php

class Footer extends DustPressModel {
	public function bind_SomeData() {
		$data = [
			"key1" => "This is the footer.",
			"key2" => [
				"Here",
				"we",
				"have",
				"some",
				"words",
				"in",
				"an",
				"array",
				"that",
				"form",
				"a",
				"sentence"
			]
		];

		$this->bind_data($data);
	}

	public function bind_Variable() {
		// Let's get the args
		$args = $this->get_args();

		// If we have "variable" key there, bind it to the model
		// (check page-custom.php)

		global $dustpress;

		if( isset( $args["variable"] ) ) {
			$this->bind_data( $args["variable"] );
		}
	}
}