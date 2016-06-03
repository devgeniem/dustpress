<?php
class Sep_DP extends \DustHelper
{
    public function init() {
        if ( isset( $this->params->end ) ) {
            $end = $this->params->end;
        }
        else {
            $end = 1;
        }

        if ( isset( $this->params->start ) ) {
            $start = $this->params->start;
        }
        else {
            $start = 0;
        }

        $iterationCount = $this->context->get('$iter');

        if($iterationCount === NULL)
        {
            $this->chunk->setError('Sep must be inside an array');
        }
        $len = $this->context->get('$len');
        if( $iterationCount >= $start && $iterationCount < $len - $end )
        {
            return $this->chunk->render($this->bodies->block, $this->context);
        }
        else
        {
            return $this->chunk;
        }
    }
}

$this->dust->helpers['sep'] = new Sep_DP();