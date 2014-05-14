<?php
namespace Ytake\WebSocket\Io;

interface LogInterface
{

    /**
     * @param $errorLevel
     * @param $message
     */
    public function writer($errorLevel, $message);
}