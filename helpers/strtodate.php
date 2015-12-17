<?php
$this->dust->helpers['strtodate'] = function ( \Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $ctx, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params ) {
	if ( $bodies->dummy !== true ) {
		$value 	= $params->value;
		$format	= $params->format;
		$now	= $params->now;
		
		$output = date( $format, strtotime( $value, $now ) );

		return $chunk->write( $output );
	}
};