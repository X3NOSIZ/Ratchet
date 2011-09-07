<?php
namespace Ratchet;
use Ratchet\Protocol\ProtocolInterface;

/**
 * A wrapper for the PHP socket_ functions
 * @author Chris Boden <shout at chrisboden dot ca>
 */
class Socket {
    /**
     * @type resource
     */
    public $_resource;

    public static $_defaults = Array(
        'domain'   => AF_INET
      , 'type'     => SOCK_STREAM
      , 'protocol' => SOL_TCP
    );

    /**
     * @param int Specifies the protocol family to be used by the socket.
     * @param int The type of communication to be used by the socket
     * @param int Sets the specific protocol within the specified domain to be used when communicating on the returned socket
     * @throws Ratchet\Exception
     */
    public function __construct($domain = null, $type = null, $protocol = null) {
        list($domain, $type, $protocol) = static::getConfig($domain, $type, $protocol);

        $this->_resource = @socket_create($domain, $type, $protocol);

        if (!is_resource($this->_resource)) {
            throw new Exception();
        }
    }

    /**
     * @param Ratchet\Protocol\ProtocolInterface
     * @return Ratchet\Socket
     * @throws Ratchet\Exception
     */
    public static function createFromConfig(ProtocolInterface $protocol) {
        $config = $protocol::getDefaultConfig();
        $class  = get_called_class();

        $socket = new $class($config['domain'] ?: null, $config['type'] ?: null, $config['protocol'] ?: null);

        if (is_array($config['options'])) {
            foreach ($config['options'] as $level => $pair) {
                foreach ($pair as $optname => $optval) {
                    $socket->set_option($level, $optname, $optval);
                }
            }
        }

        return $socket;
    }

    /**
     * @internal
     * @param int Specifies the protocol family to be used by the socket.
     * @param int The type of communication to be used by the socket
     * @param int Sets the specific protocol within the specified domain to be used when communicating on the returned socket
     * @return Array
     */
    protected static function getConfig($domain = null, $type = null, $protocol = null) {
        foreach (static::$_defaults as $key => $val) {
            if (null === $$key) {
                $$key = $val;
            }
        }

        return Array($domain, $type, $protocol);
    }

    /**
     * Since PHP is retarded and their golden hammer, the array, doesn't implement any interfaces I have to hackishly overload socket_select
     * @param Iterator|Array|NULL The sockets listed in the read array will be watched to see if characters become available for reading (more precisely, to see if a read will not block - in particular, a socket resource is also ready on end-of-file, in which case a socket_read() will return a zero length string).
     * @param Iterator|Array|NULL The sockets listed in the write array will be watched to see if a write will not block.
     * @param Iterator|Array|NULL The sockets listed in the except array will be watched for exceptions.
     * @param int The tv_sec and tv_usec together form the timeout parameter. The timeout is an upper bound on the amount of time elapsed before socket_select() return. tv_sec may be zero , causing socket_select() to return immediately. This is useful for polling. If tv_sec is NULL (no timeout), socket_select() can block indefinitely.
     * @param int
     * @throws \InvalidArgumentException
     * @todo See if this crack-pot scheme works!
     */
    public function select(&$read, &$write, &$except, $tv_sec, $tv_usec = 0) {
        $read   = static::mungForSelect($read);
        $write  = static::mungForSelect($write);
        $except = static::mungForSelect($except);

        socket_select($read, $write, $except, $tv_sec, $tv_usec);
    }

    /**
     * @param Iterator|Array|NULL
     * @return Array|NULL
     * @throws \InvalidArgumentException
     */
    protected static function mungForSelect($collection) {
        if (null === $collection || is_array($collection)) {
            return $collection;
        }

        if (!($collection instanceof \Traversable)) {
            throw new \InvalidArgumentException('Object pass is not traversable');
        }

        $return = Array();
        foreach ($collection as $key => $socket) {
            $return[$key] = ($socket instanceof \Ratchet\Socket ? $socket->_resource : $socket);
        }

        return $return;
    }

    /**
     * @internal
     * @param string
     * @param Array
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($method, $arguments) {
        if (function_exists('socket_' . $method)) {
            array_unshift($arguments, $this->_resource);
            return call_user_func_array('socket_' . $method, $arguments);
        }

        throw new \BadMethodCallException("{$method} is not a valid socket function");
    }
}