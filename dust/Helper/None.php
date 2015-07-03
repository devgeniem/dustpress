<?php
namespace Dust\Helper;

use Dust\Evaluate;

class None 
{
		public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context, Evaluate\Bodies $bodies, Evaluate\Parameters $params) {
      
      $selectInfo = $context->get('__selectInfo');

			if($selectInfo != NULL && !$selectInfo->selectComparisonSatisfied ) {
      	$selectInfo->selectComparisonSatisfied = true;
        return $chunk->render($bodies->block, $context);
    	} else {
      	return $chunk;
			}

    }

}