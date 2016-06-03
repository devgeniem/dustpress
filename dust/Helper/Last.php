<?php
namespace Dust\Helper;

use Dust\Evaluate;

class Last
{
    public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context, Evaluate\Bodies $bodies) {
        $iterationCount = $context->get('$iter');
        if($iterationCount === NULL)
        {
            $chunk->setError('Last must be inside an array');
        }
        $len = $context->get('$len');
        if($iterationCount == $len - 1)
        {
            return $chunk->render($bodies->block, $context);
        }
        else
        {
            return $chunk;
        }
    }

}