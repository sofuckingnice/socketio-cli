<?php
namespace Comnect\WebSocket\Io;

/**
 * Interface PayloadInterface
 * @package WebSocket\Io
 * @author  yuuki.takezawa<yuuki.takezawa@excite.jp>
 */
interface PayloadInterface
{
    /**
     * @param $fin
     * @return $this
     */
    public function setFin($fin);

    /**
     * @return int
     */
    public function getFin();

    /**
     * @param $opcode
     * @return $this
     */
    public function setOpcode($opcode);

    /**
     * @return mixed
     */
    public function getOpcode();

    /**
     * @param $mask
     * @return $this
     */
    public function setMask($mask);

    /**
     * @return int
     */
    public function getMask();

    /**
     * @return int
     */
    public function getLength();

    /**
     * @param $maskKey
     * @return $this
     */
    public function setMaskKey($maskKey);

    /**
     * @return mixed
     */
    public function getMaskKey();

    /**
     * @param $payload
     * @return $this
     */
    public function setPayload($payload);

    /**
     * @return mixed
     */
    public function getPayload();

    /**
     * @return string
     */
    public function generateMaskKey();

    /**
     * @return int|string
     */
    public function encodePayload();
}