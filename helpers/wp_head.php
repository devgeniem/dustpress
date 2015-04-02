<?php
$this->dust->helpers['wp_head'] = function (\Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $ctx, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params) {
	global $dustpress;

	ob_start();
	wp_head();
	$output = ob_get_clean();

	return $chunk->write($output);
};