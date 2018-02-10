<?php

namespace DjThd\HttpClient;

use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;
use React\Socket\Connector;
use React\Promise;
use React\Promise\Deferred;
use Evenement\EventEmitter;

class Client extends EventEmitter
{
    private $loop;
    private $connector;
    private $maxPoolConnections;
    private $connections = array();
    private $pendingConnections = array();

    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null, $maxPoolConnections = 8)
    {
        $this->loop = $loop;

        if ($connector === null) {
            $connector = new Connector($loop);
        }

        $this->connector = $connector;
        $this->maxPoolConnections = $maxPoolConnections;
    }

    public function request($method, $url, array $headers = [], $protocolVersion = '1.0')
    {
        $requestData = new RequestData($method, $url, $headers, $protocolVersion);
        $uri = ($requestData->getScheme() === 'https' ? 'tls' : 'tcp') . '://' .
            $requestData->getHost() . ':' . $requestData->getPort();
        if(!isset($this->connections[$uri])) {
            $this->connections[$uri] = new \SplObjectStorage();
        }
        if(!isset($this->pendingConnections[$uri])) {
            $this->pendingConnections[$uri] = 0;
        }

        $connection = $this->fillConnections($uri);

        $request = new Request($connection, $requestData);
        $request->on('close', function() use ($connection, $uri) {
            $connection->done(function($connection) use ($uri) {
                if(!$this->connections[$uri]->contains($connection) && $connection->isReadable()) {
                    $this->connections[$uri]->attach($connection);
                }
            });
        });

        return $request;
    }

    public function fillConnections($uri)
    {
        $deferred = new Deferred();
        while((count($this->connections[$uri]) + $this->pendingConnections[$uri]) < $this->maxPoolConnections) {
            $this->connector->connect($uri)->then(function($connection) use ($uri, $deferred) {
                $this->pendingConnections[$uri]--;
                $connection->on('close', function() use ($uri, $connection) {
                    $this->connections[$uri]->detach($connection);
                });
                $this->connections[$uri]->attach($connection);
                $deferred->resolve($connection);
            })->otherwise(function() use ($uri, $deferred) {
                $this->pendingConnections[$uri]--;
                $this->fillConnections($uri);
            });
            $this->pendingConnections[$uri]++;
        }
        $this->loop->futureTick(function() use ($uri, $deferred) {
            if(count($this->connections[$uri]) > 0) {
                $this->connections[$uri]->rewind();
                $connection = $this->connections[$uri]->current();
                $this->connections[$uri]->detach($connection);
                $deferred->resolve($connection);
            } else {
                $this->fillConnections($uri)->done(function($connection) use ($deferred) {
                    $deferred->resolve($connection);
                });
            }
        });
        return $deferred->promise();
    }

    public function close()
    {
        $this->maxPoolConnections = 0;
        foreach($this->connections as $uri => $connections) {
            foreach($connections as $connection) {
                $connection->close();
            }
        }
        $this->emit('close');
    }
}
