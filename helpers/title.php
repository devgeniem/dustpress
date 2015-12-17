<?php
$this->dust->helpers['title'] = function ( \Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $ctx, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params ) {
	global $dustpress;

	if ( $bodies->dummy !== true ) {
		ob_start();
		the_title();
		$output = ob_get_clean();

		return $chunk->write( $output );
	}
};