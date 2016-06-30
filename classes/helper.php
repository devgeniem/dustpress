<?php
namespace DustPress;

class Helper {
	protected $chunk;
	protected $context;
	protected $bodies;
	protected $params;

	public function __invoke(\Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $context, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params) {

		$this->chunk = $chunk;
		$this->context = $context;
		$this->bodies = $bodies;
		$this->params = $params;

		if ( ! isset( $this->bodies->dummy ) ) {
			if ( method_exists( $this, "init" ) ) {
				return $this->init();
			}
			else if ( method_exists( $this, "output" ) ) {
				return $this->chunk->write( $this->output() );
			}
		} else {
            if ( method_exists( $this, "prerun" ) ) {
                $this->prerun();
            }
        }
	}
}