<?php
namespace Ytake\WebSocket\Io;

use Monolog\Logger;
use Monolog\Handler\SyslogHandler;

/**
 * Class Log
 * @package Ytake\WebSocket\Io
 * @author  yuuki.takezawa<yuuki.takezawa@comnect.jp.net>
 */
class Log implements LogInterface
{

    protected $name = 'socket.io/client';

    public function __construct()
    {
        $this->logger = new Logger($this->name);
        $this->logger->pushHandler(new SyslogHandler('socket.io/client'));
    }

    /**
     * @param $errorLevel
     * @param $message
     */
    public function writer($errorLevel, $message)
    {
        $errorType = ucfirst(strtolower($errorLevel));
        $method = "add" . $errorType;
        $this->logger->$method($message);
    }
}