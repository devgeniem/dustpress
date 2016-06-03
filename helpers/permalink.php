<?php
namespace DustPress;

class Permalink extends Helper
{
    public function output() {
		return get_permalink();
	}
}

$this->dust->helpers['permalink'] = new Permalink();