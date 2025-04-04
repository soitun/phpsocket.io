<?php

namespace PHPSocketIO;

use Workerman\Worker;
use PHPSocketIO\Engine\Engine;

class SocketIO
{
    public $worker;
    public $sockets;
    public $nsps = [];
    protected $_nsp = null;
    protected $_socket = null;
    protected $_adapter = null;
    public $engine = null;
    protected $_origins = '*:*';
    protected $_path = null;

    public function __construct($port = null, $opts = [])
    {
        $nsp = $opts['nsp'] ?? '\PHPSocketIO\Nsp';
        $this->nsp($nsp);

        $socket = $opts['socket'] ?? '\PHPSocketIO\Socket';
        $this->socket($socket);

        $adapter = $opts['adapter'] ?? '\PHPSocketIO\DefaultAdapter';
        $this->adapter($adapter);
        if (isset($opts['origins'])) {
            $this->origins($opts['origins']);
        }

        unset($opts['nsp'], $opts['socket'], $opts['adapter'], $opts['origins']);

        $this->sockets = $this->of('/');

        if (! class_exists('Protocols\SocketIO')) {
            class_alias('PHPSocketIO\Engine\Protocols\SocketIO', 'Protocols\SocketIO');
        }
        if ($port) {
            $host = '0.0.0.0';
            if (isset($opts['host'])) {
                $ip = trim($opts['host'], '[]');
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $host = (strpos($ip, ':') !== false) ? "[$ip]" : $ip;
                }
            }
            $worker = new Worker('SocketIO://' . $host . ':' . $port, $opts);
            $worker->name = 'PHPSocketIO';

            if (isset($opts['ssl'])) {
                $worker->transport = 'ssl';
            }

            $this->attach($worker);
        }
    }

    public function nsp($v = null)
    {
        if (empty($v)) {
            return $this->_nsp;
        }
        $this->_nsp = $v;
        return $this;
    }

    public function socket($v = null)
    {
        if (empty($v)) {
            return $this->_socket;
        }
        $this->_socket = $v;
        return $this;
    }

    public function adapter($v = null)
    {
        if (empty($v)) {
            return $this->_adapter;
        }
        $this->_adapter = $v;
        foreach ($this->nsps as $nsp) {
            $nsp->initAdapter();
        }
        return $this;
    }

    public function origins($v = null)
    {
        if ($v === null) {
            return $this->_origins;
        }
        $this->_origins = $v;
        if (isset($this->engine)) {
            $this->engine->origins = $this->_origins;
        }
        return $this;
    }

    public function attach($srv, $opts = []): SocketIO
    {
        $engine = new Engine();
        $engine->attach($srv, $opts);

        // Export http server
        $this->worker = $srv;

        // bind to engine events
        $this->bind($engine);

        return $this;
    }

    public function bind($engine): SocketIO
    {
        $this->engine = $engine;
        $this->engine->on('connection', [$this, 'onConnection']);
        $this->engine->origins = $this->_origins;
        return $this;
    }

    public function of($name, $fn = null)
    {
        if ($name[0] !== '/') {
            $name = "/$name";
        }
        if (empty($this->nsps[$name])) {
            $nsp_name = $this->nsp();
            $this->nsps[$name] = new $nsp_name($this, $name);
        }
        if ($fn) {
            $this->nsps[$name]->on('connect', $fn);
        }
        return $this->nsps[$name];
    }

    public function onConnection($engine_socket): SocketIO
    {
        $client = new Client($this, $engine_socket);
        $client->connect('/');
        return $this;
    }

    public function on()
    {
        $args = array_pad(func_get_args(), 2, null);

        if ($args[0] === 'workerStart') {
            $this->worker->onWorkerStart = $args[1];
        } elseif ($args[0] === 'workerStop') {
            $this->worker->onWorkerStop = $args[1];
        } elseif ($args[0] !== null) {
            return call_user_func_array([$this->sockets, 'on'], $args);
        }
    }

    public function in()
    {
        return call_user_func_array([$this->sockets, 'in'], func_get_args());
    }

    public function to()
    {
        return call_user_func_array([$this->sockets, 'to'], func_get_args());
    }

    public function emit()
    {
        return call_user_func_array([$this->sockets, 'emit'], func_get_args());
    }

    public function send()
    {
        return call_user_func_array([$this->sockets, 'send'], func_get_args());
    }

    public function write()
    {
        return call_user_func_array([$this->sockets, 'write'], func_get_args());
    }
}
