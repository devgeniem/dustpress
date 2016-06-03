<?php
class DustHelper {
	protected $chunk;
	protected $context;
	protected $bodies;
	protected $params;

	public function __invoke(\Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $context, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params) {

		$this->chunk = $chunk;
		$this->context = $context;
		$this->bodies = $bodies;
		$this->params = $params;

		if ( function_exists( array( $this, "init" ) ) ) {
			return $this->init();
		}
		else if ( function_exists( array( $this, "output" ) ) ) {
			return $this->chunk->write( $this->output() );
		}
	}
}