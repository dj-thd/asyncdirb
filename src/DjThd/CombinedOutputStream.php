<?php

namespace DjThd;

use \Evenement\EventEmitter;
use \React\Stream\WritableStreamInterface;
use \React\Stream\Util;

class CombinedOutputStream extends EventEmitter implements WritableStreamInterface
{
	protected $streams = array();

	public function __construct(...$streams)
	{
		$this->streams = $streams;
		foreach($streams as $stream) {
			$stream->on('error', function($error) {
				$this->emit('error', array($error));
			});
			$stream->on('close', function() {
				$this->emit('close');
			});
		}
	}

	public function isWritable()
	{
		foreach($this->streams as $stream) {
			if($stream->isWritable()) {
				return true;
			}
		}
		return false;
	}

	public function write($data)
	{
		foreach($this->streams as $stream) {
			if($stream->isWritable()) {
				$stream->write($data);
			}
		}
		return true;
	}

	public function end($data = null)
	{
		foreach($this->streams as $stream) {
			$stream->end($data);
		}
	}

	public function close()
	{
		foreach($this->streams as $stream) {
			$stream->close();
		}
	}
}
