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
				if ( property_exists( $this, "cache" ) ) {
					$hash = sha1( get_class( $this ) . serialize( $params ) );

					if ( ! ( $output = wp_cache_get( $hash, "dustpress/helpers" ) ) ) {
						$output = $this->output();

						if ( isset( $this->ttl ) ) {
							$ttl = $this->ttl;
						}
						else {
							$ttl = 0;
						}

						wp_cache_set( $hash, $output, "dustpress/helpers", $ttl );
					}
				}
				else {
					$output = $this->output();
				}

				return $this->chunk->write( $output );
			}
		} else {
            if ( method_exists( $this, "prerun" ) ) {
                $this->prerun();
            }
        }
	}
}