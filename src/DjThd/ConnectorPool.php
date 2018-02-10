<?php

namespace DjThd;

use Evenement\EventEmitter;
use React\Promise;
use React\Socket\ConnectorInterface;

class ConnectorPool extends EventEmitter implements ConnectorInterface
{
	protected $connector = null;
	protected $uri = false;
	protected $closed = false;
	protected $connections = null;
	protected $pending_connections = 0;
	protected $max_connections = 10;

	public function __construct($connector, $uri = false, $max_connections = 10)
	{
		$this->connections = new \SplObjectStorage();
		$this->connector = $connector;
		$this->uri = $uri;
		$this->max_connections = $max_connections;
		$this->fillPool();
		$this->on('close', function() {
			$this->closed = true;
			foreach($this->connections as $connection) {
				$connection->close();
			}
		});
	}

	public function close()
	{
		$this->emit('close');
	}

	protected function fillPool()
	{
		if($this->closed) {
			return;
		}
		while($this->pending_connections + count($this->connections) < $this->max_connections) {
			if($this->uri === false) {
				return;
			}
			$this->pending_connections++;
			$this->connector->connect($this->uri)->then(function($connection) {
				$this->pending_connections--;
				$this->connections->attach($connection);
				$connection->on('close', function() use ($connection) {
					$this->connections->detach($connection);
					$this->fillPool();
				});
			})
			->otherwise(function() {
				$this->pending_connections--;
				$this->fillPool();
			});
		}
	}

	public function connect($uri)
	{
		if($this->closed) {
			return Promise\resolve(null);
		}
		if($uri === $this->uri && count($this->connections) > 0) {
			$this->connections->rewind();
			$connection = $this->connections->current();
			$this->connections->detach($connection);
			return Promise\resolve($connection);
		} else {
			$this->uri = $uri;
			$this->fillPool();
			return $this->connector->connect($uri);
		}
	}
}
