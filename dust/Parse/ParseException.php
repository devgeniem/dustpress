<?php
namespace Dust\Parse
{
    class ParseException extends \Dust\DustException
    {
        /**
         * @param string $message
         * @param int    $line
         * @param int    $col
         */
        public function __construct($message, $line, $col) {

            die("Dust error: " . $message . " (line " . $line . " character " . $col . ")");

            parent::__construct($message . " (line " . $line . " character " . $col . ")");
        }
    }
}

