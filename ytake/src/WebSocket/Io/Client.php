<?php
namespace Ytake\WebSocket\Io;

use Closure;
use Ytake\WebSocket\Io\Exceptions\SocketErrorException;
use Ytake\WebSocket\Io\Exceptions\SocketHandshakeException;

/**
 * Class Client
 * @package Ytake\WebSocket\Io
 * @author  yuuki.takezawa<yuuki.takezawa@excite.jp>
 */
class Client
{
    const TYPE_DISCONNECT   = 0;
    const TYPE_CONNECT      = 1;
    const TYPE_HEARTBEAT    = 2;
    const TYPE_MESSAGE      = 3;
    const TYPE_JSON_MESSAGE = 4;
    const TYPE_EVENT        = 5;
    const TYPE_ACK          = 6;
    const TYPE_ERROR        = 7;
    const TYPE_NOOP         = 8;

    private $session;
    private $fd;
    private $read;
    private $heartbeatStamp = 0;
    private $checkSslPeer = true;
    private $debug;
    private $handshakeTimeout = null;
    /** @var array  */
    private $callbacks = [];
    /** @var array */
    private $response;
    /** @var string */
    protected $url;
    /** @var   */
    protected $query;
    /** @var null  */
    protected $namespace;
    /** @var \Ytake\WebSocket\Io\PayloadInterface  */
    protected $payload;
    /** @var \Ytake\WebSocket\Io\HeaderInterface  */
    protected $header;
    /** @var \Ytake\WebSocket\Io\LogInterface  */
    protected $log;

    /**
     * @param PayloadInterface $payload
     * @param HeaderInterface $header
     * @param LogInterface $log
     */
    public function __construct(PayloadInterface $payload, HeaderInterface $header, LogInterface $log)
    {
        $this->payload = $payload;
        $this->header = $header;
        $this->log = $log;
    }

    /**
     * socket.io client
     * @param string $url
     * @param string $ioPath
     * @param int $protocol
     * @param bool $read
     * @param bool $checkSslPeer
     * @param bool $debug
     * @return $this
     */
    public function client(
        $url = 'http://localhost:3000', $ioPath = 'socket.io', $protocol = 1,
        $read = true, $checkSslPeer = true, $debug = false
    ) {
        $this->url = "{$url}/{$ioPath}/" . (string)$protocol;
        $this->read = $read;
        $this->debug = $debug;
        $this->checkSslPeer = $checkSslPeer;
        return $this;
    }

    /**
     * namespace support
     * @param null $namespace
     * @return $this
     */
    public function of($namespace = null)
    {
        if(!is_null($namespace))
        {
            $this->namespace = $namespace;
        }
        return $this;
    }

    /**
     * add handshake query
     * @param array $array
     * @return $this
     */
    public function query(array $array)
    {
        if(count($array))
        {
            $this->query = '?' . http_build_query($array);
        }
        return $this;
    }

    /**
     * Set Handshake timeout in milliseconds
     *
     * @param int $delay
     * @return $this
     */
    public function setHandshakeTimeout($delay)
    {
        $this->handshakeTimeout = $delay;
        return $this;
    }

    /**
     * socket.io connection
     * @param callable $callback
     * @return $this
     */
    public function connection(callable $callback = null)
    {
        // connect
        $this->connect();
        if(!is_null($callback))
        {
            call_user_func($callback, $this);
        }
        return $this;
    }

    /**
     * Attach an event handler function for a given event
     *
     * @access public
     * @param string $event
     * @param Closure $callback
     * @throws \InvalidArgumentException
     * @return string
     */
    public function on($event, callable $callback)
    {
        if (!is_callable($callback))
        {
            throw new \InvalidArgumentException('argument 2 must be callable.');
        }

        if (!isset($this->callbacks[$event]))
        {
            $this->callbacks[$event] = array();
        }
        // @TODO Handle case where callback is a string
        if (in_array($callback, $this->callbacks[$event]))
        {
            $this->debug('Skip existing callback');
            return false;
        }
        $this->callbacks[$event][] = $callback;
        return $this;
    }

    /**
     * Emit an event
     *
     * @param string $event
     * @param array $args
     * @return $this
     */
    public function emit($event, array $args)
    {
        $this->send(
            self::TYPE_EVENT, null, $this->namespace, json_encode(['name' => $event, 'args' => $args])
        );
        return $this;
    }

    /**
     * Send message to the websocket
     *
     * @access public
     * @param int $type
     * @param int $id
     * @param int $endpoint
     * @param string $message
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function send($type, $id = null, $endpoint = null, $message = null)
    {
        if (!is_int($type) || $type > 8)
        {
            throw new \InvalidArgumentException('type parameter must be an integer strictly inferior to 9.');
        }

        $rawMessage = "{$type}:{$id}:{$endpoint}:{$message}";
        $this->payload->setOpcode(Payload::OPCODE_TEXT)->setMask(true)->setPayload($rawMessage);
        $encoded = $this->payload->encodePayload();

        fwrite($this->fd, $encoded);
        // wait 100ms before closing connexion
        usleep(100 * 1000);
        $this->debug('Sent '.$rawMessage);
        return $this;
    }

    /**
     * @return array
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return mixed
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * disconnect
     * @return bool
     */
    public function disconnect()
    {
        if (is_resource($this->fd))
        {
            $this->send(self::TYPE_DISCONNECT, '', $this->namespace);
            fclose($this->fd);
            return true;
        }
        return false;
    }

    /**
     * Keep the connection alive and dispatch events
     *
     * @access public
     * @todo work on callbacks
     */
    public function keepAlive()
    {
        while (is_resource($this->fd))
        {
            if ($this->session['heartbeat_timeout'] > 0 && $this->session['heartbeat_timeout']+$this->heartbeatStamp-5 < time())
            {
                $this->send(self::TYPE_HEARTBEAT);
                $this->heartbeatStamp = time();
            }

            $r = array($this->fd);
            $w = $e = null;

            if (stream_select($r, $w, $e, 5) == 0) continue;
            $result = $this->read();
            $session = explode(':', $result, 4);
            if ((int)$session[0] === self::TYPE_EVENT)
            {
                unset($session[0], $session[1], $session[2]);

                $response = json_decode(implode(':', $session), true);
                $name = $response['name'];
                $data = $response['args'][0];

                $this->debug("Receive event {$name} with data {$data['message']}");
                if (!empty($this->callbacks[$name]))
                {
                    foreach ($this->callbacks[$name] as $callback)
                    {
                        call_user_func($callback, $data);
                    }
                }
            }
        }
    }

    /**
     * Read the buffer and return the oldest event in stack
     *
     * @access public
     * @return string
     * // https://tools.ietf.org/html/rfc6455#section-5.2
     */
    public function read()
    {
        // Ignore first byte, I hope Socket.io does not send fragmented frames, so we don't have to deal with FIN bit.
        // There are also reserved bit's which are 0 in socket.io, and opcode, which is always "text frame" in Socket.io
        fread($this->fd, 1);

        // There is also masking bit, as MSB, but it's 0 in current Socket.io
        $payload_len = ord(fread($this->fd, 1));

        switch ($payload_len) {
            case 126:
                $payload_len = unpack("n", fread($this->fd, 2));
                $payload_len = $payload_len[1];
                break;
            case 127:
                $this->debug("Next 8 bytes are 64bit uint payload length, not yet implemented, since PHP can't handle 64bit longs!");
                break;
        }
        // Use buffering to handle packet size > 16Kb
        $read = 0;
        $payload = '';
        while ($read < $payload_len && ($buff = fread($this->fd, $payload_len-$read))) {
            $read += strlen($buff);
            $payload .= $buff;
        }
        $this->debug('Received ' . $payload);
        return $payload;
    }

    /**
     * @access private
     * @param $message
     * @return void
     */
    private function debug($message)
    {
        if (!$this->debug)
        {
            return;
        }
        $this->log->writer('debug', $message);
    }

    /**
     * Handshake with socket.io server
     *
     * @access private
     * @throws Exceptions\SocketHandshakeException
     * @return bool
     */
    private function handshake()
    {
        $url = $this->url;
        if (!empty($this->query))
        {
            $url .= $this->query;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // check ssl
        if (!$this->checkSslPeer)
        {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        //
        if (!is_null($this->handshakeTimeout))
        {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->handshakeTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->handshakeTimeout);
        }
        // exec
        $result = curl_exec($ch);
        if ($result === false || $result === '')
        {
            $errorInfo = curl_error($ch);
            curl_close($ch);
            throw new SocketHandshakeException($errorInfo);
        }
        //
        $this->response = curl_getinfo($ch);
        if($this->response['http_code'] != 200)
        {
            $header = $this->header->getResponseHeader();
            throw new SocketHandshakeException($header, $this->response['http_code']);
        }

        $session = explode(':', $result);
        $this->session['session_id'] = $session[0];
        $this->session['heartbeat_timeout'] = $session[1];
        $this->session['connection_timeout'] = $session[2];
        $this->session['supported_transports'] = array_flip(explode(',', $session[3]));

        if (!isset($this->session['supported_transports']['websocket']))
        {
            throw new SocketHandshakeException('This socket.io server do not support websocket protocol. Terminating connection...');
        }
        return true;
    }


    /**
     * Connects using websocket protocol
     *
     * @access private
     * @throws Exceptions\SocketErrorException
     * @throws \Exception
     * @return bool
     */
    private function connect()
    {
        $this->handshake();
        //
        $url = parse_url($this->url);
        $url['port'] = (isset($url['port'])) ? $url['port'] : null;
        if (array_key_exists('scheme', $url) && $url['scheme'] == 'https')
        {
            $url['host'] = 'ssl://' . $url['host'];
            if (!$url['port'])
            {
                $url['port'] = 443;
            }
        }
        // socket open
        $this->fd = fsockopen($url['host'], $url['port'], $errorCode, $errorMessage);
        if (!$this->fd)
        {
            throw new SocketErrorException("fsockopen returned: {$errorMessage}", $errorCode);
        }
        //
        $uri = $url['path'] . "/websocket/". $this->session['session_id'] . $this->query;

        $output = $this->header->setRequestHeader($uri, $url['host'], $this->generateKey());

        fwrite($this->fd, $output);
        $response = fgets($this->fd);
        // response error
        if ($response === false)
        {
            throw new SocketErrorException('Socket.io did not respond properly. Aborting...');
        }
        // error
        if ($message = substr($response, 0, 12) != 'HTTP/1.1 101')
        {
            throw new SocketErrorException("Unexpected Response. Expected HTTP/1.1 101 got {$message} Aborting...");
        }

        while(true)
        {
            $res = trim(fgets($this->fd));
            if ($res === '') break;
        }

        if($this->read)
        {
            if ($this->read() != '1::')
            {
                throw new \Exception('Socket.io did not send connect response. Aborting...');
            } else {
                $this->debug('Server report us as connected !');
            }
        }

        if($this->namespace)
        {
            $this->send(self::TYPE_CONNECT, "", $this->namespace);
        }
        $this->heartbeatStamp = time();
    }

    /**
     * @access private
     * @param int $length
     * @return string
     */
    private function generateKey($length = 16)
    {
        $c = 0;
        $tmp = '';
        while($c++ * 16 < $length)
        {
            $tmp .= md5(mt_rand(), true);
        }
        return base64_encode(substr($tmp, 0, $length));
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}