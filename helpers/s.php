<?php
$this->dust->helpers['s'] = function ( \Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $ctx, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params ) {
	global $dustpress;
	
	if ( $params->s ) {
		$output = __( $params->s );
	}
	else {
		$output = __('Helper missing parameter "s".');
	}

	return $chunk->write( $output );
};