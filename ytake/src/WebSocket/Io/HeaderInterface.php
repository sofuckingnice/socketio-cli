<?php
namespace Ytake\WebSocket\Io;

/**
 * Interface Header
 * @package Ytake\WebSocket\Io\Response
 * @author  yuuki.takezawa<yuuki.takezawa@excite.jp>
 */
interface HeaderInterface {

    /**
     * @return array
     */
    public function getResponseHeader();

    /**
     * @param $uri
     * @param $host
     * @param $key
     * @param array $options
     * @param int $version
     * @return string
     */
    public function setRequestHeader($uri, $host, $key, array $options = null, $version = 13);
}