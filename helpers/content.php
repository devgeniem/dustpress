<?php
$this->dust->helpers['content'] = function ( \Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $ctx, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params ) {
	global $dustpress, $post;

	if ( $bodies->dummy !== true ) {
		if ( $params->data ) {		
			$output = apply_filters( 'the_content', $params->data );
		}
		else {
			ob_start();
			setup_postdata( $post );
			the_content();
			wp_reset_postdata();
			$output = ob_get_clean();
		}

		return $chunk->write( $output );
	}
};