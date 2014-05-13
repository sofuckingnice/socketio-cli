<?php
namespace Ytake\WebSocket\Io;

/**
 * Class Payload
 * @package WebSocket\Io
 * @author  yuuki.takezawa<yuuki.takezawa@excite.jp>
 */
class Payload implements PayloadInterface
{
    //
    const OPCODE_CONTINUE = 0x0;
    const OPCODE_TEXT = 0x1;
    const OPCODE_BINARY = 0x2;
    const OPCODE_NON_CONTROL_RESERVED_1 = 0x3;
    const OPCODE_NON_CONTROL_RESERVED_2 = 0x4;
    const OPCODE_NON_CONTROL_RESERVED_3 = 0x5;
    const OPCODE_NON_CONTROL_RESERVED_4 = 0x6;
    const OPCODE_NON_CONTROL_RESERVED_5 = 0x7;
    const OPCODE_CLOSE = 0x8;
    const OPCODE_PING = 0x9;
    const OPCODE_PONG = 0xA;
    const OPCODE_CONTROL_RESERVED_1 = 0xB;
    const OPCODE_CONTROL_RESERVED_2 = 0xC;
    const OPCODE_CONTROL_RESERVED_3 = 0xD;
    const OPCODE_CONTROL_RESERVED_4 = 0xE;
    const OPCODE_CONTROL_RESERVED_5 = 0xF;

    protected $fin = 0x1;
    protected $rsv1 = 0x0;
    protected $rsv2 = 0x0;
    protected $rsv3 = 0x0;
    protected $opcode;
    protected $mask = 0x0;
    protected $maskKey;
    protected $payload;

    /**
     * @param $fin
     * @return $this
     */
    public function setFin($fin)
    {
        $this->fin = $fin;
        return $this;
    }

    /**
     * @return int
     */
    public function getFin()
    {
        return $this->fin;
    }

    /**
     * @param $rsv1
     * @return $this
     */
    public function setRsv1($rsv1)
    {
        $this->rsv1 = $rsv1;
        return $this;
    }

    /**
     * @return int
     */
    public function getRsv1()
    {
        return $this->rsv1;
    }

    /**
     * @param $rsv2
     * @return $this
     */
    public function setRsv2($rsv2)
    {
        $this->rsv2 = $rsv2;
        return $this;
    }

    /**
     * @return int
     */
    public function getRsv2()
    {
        return $this->rsv2;
    }

    /**
     * @param $rsv3
     * @return $this
     */
    public function setRsv3($rsv3)
    {
        $this->rsv3 = $rsv3;
        return $this;
    }

    /**
     * @return int
     */
    public function getRsv3()
    {
        return $this->rsv3;
    }

    /**
     * @param $opcode
     * @return $this
     */
    public function setOpcode($opcode)
    {
        $this->opcode = $opcode;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOpcode()
    {
        return $this->opcode;
    }

    /**
     * @param $mask
     * @return $this
     */
    public function setMask($mask)
    {
        $this->mask = $mask;
        if ($this->mask == true) {
            $this->generateMaskKey();
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getMask()
    {
        return $this->mask;
    }

    /**
     * @return int
     */
    public function getLength()
    {
        return strlen($this->getPayload());
    }

    /**
     * @param $maskKey
     * @return $this
     */
    public function setMaskKey($maskKey)
    {
        $this->maskKey = $maskKey;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaskKey()
    {
        return $this->maskKey;
    }

    /**
     * @param $payload
     * @return $this
     */
    public function setPayload($payload)
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @return string
     */
    public function generateMaskKey()
    {
        $this->setMaskKey($key = openssl_random_pseudo_bytes(4));
        return $key;
    }

    /**
     * @return int|string
     */
    public function encodePayload()
    {
        $payload = (($this->getFin()) << 1) | ($this->getRsv1());
        $payload = (($payload) << 1) | ($this->getRsv2());
        $payload = (($payload) << 1) | ($this->getRsv3());
        $payload = (($payload) << 4) | ($this->getOpcode());
        $payload = (($payload) << 1) | ($this->getMask());

        if ($this->getLength() <= 125) {
            $payload = (($payload) << 7) | ($this->getLength());
            $payload = pack('n', $payload);
        } elseif ($this->getLength() <= 0xffff) {
            $payload = (($payload) << 7) | 126;
            $payload = pack('n', $payload).pack('n*', $this->getLength());
        } else {
            $payload = (($payload) << 7) | 127;
            $left = 0xffffffff00000000;
            $right = 0x00000000ffffffff;
            $l = ($this->getLength() & $left) >> 32;
            $r = $this->getLength() & $right;
            $payload = pack('n', $payload).pack('NN', $l, $r);
        }

        if ($this->getMask() == 0x1) {
            $payload .= $this->getMaskKey();
            $data = $this->maskData($this->getPayload(), $this->getMaskKey());
        } else {
            $data = $this->getPayload();
        }

        $payload = $payload.$data;

        return $payload;
    }

    /**
     * @param $data
     * @param $key
     * @return string
     */
    public function maskData($data, $key)
    {
        $masked = '';

        for ($i = 0; $i < strlen($data); $i++) {
            $masked .= $data[$i] ^ $key[$i % 4];
        }
        return $masked;
    }
}
