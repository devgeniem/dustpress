<?php
$this->dust->helpers['permalink'] = function ( \Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $ctx, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params ) {
	if ( $bodies->dummy !== true ) {
		return $chunk->write( get_permalink() );
	}
};