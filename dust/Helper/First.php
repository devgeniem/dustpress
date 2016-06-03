<?php
namespace Dust\Helper;

use Dust\Evaluate;

class First
{
    public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context, Evaluate\Bodies $bodies) {
        $iterationCount = $context->get('$iter');
        if($iterationCount === NULL)
        {
            $chunk->setError('First must be inside an array');
        }
        $len = $context->get('$len');
        if($iterationCount == 0)
        {
            return $chunk->render($bodies->block, $context);
        }
        else
        {
            return $chunk;
        }
    }

}