<?php
$this->dust->helpers['permalink'] = function ( \Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $ctx, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params ) {

	if ( isset( $params->id ) ) {
		$id = $params->id;
	}
	else {
		$id = get_the_ID();
	}

	return $chunk->write( get_permalink( $id ) );
};