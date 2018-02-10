<?php

namespace DjThd;

use \Evenement\EventEmitter;
use \React\Stream\ReadableStreamInterface;
use \React\Stream\WritableStreamInterface;
use \React\Stream\Util;

final class StreamLineReader extends ReadableStreamWrapper implements ReadableStreamInterface
{
	protected $stream;
	protected $buffered = '';
	protected $isPaused = false;
	protected $closed = false;
	protected $ended = false;

	public function __construct(ReadableStreamInterface $stream)
	{
		parent::__construct($stream);
		$stream->on('data', function($data) {
			if(strlen($this->buffered) > 0) {
				$this->stream->pause();
				$data = $this->buffered . $data;
				$this->buffered = '';
				$this->processData($data);
				if(!$this->isPaused) {
					$this->stream->resume();
				}
			} else {
				$this->processData($data);
			}
		});
		$stream->on('close', function() {
			$this->closed = true;
			if(strlen($this->buffered) === 0) {
				$this->emit('close');
				$this->removeAllListeners();
			}
		});
		$stream->on('error', function() {
			$this->emit('error', func_get_args());
		});
		$stream->on('end', function() {
			$this->ended = true;
			if(strlen($this->buffered) === 0) {
				$this->emit('end');
			}
		});
	}

	public function processData($data)
	{
		$lines = explode("\n", $data);
		$pending_buffer = '';
		if(strlen($data) > 0 && $data[strlen($data)-1] !== "\n") {
			$pending_buffer = array_pop($lines);
		}
		$data = null;
		foreach($lines as $line) {
			if(!$this->isPaused) {
				$line = rtrim($line, "\r\n");
				if(strlen($line) > 0) {
					$this->emit('data', array($line));
				}
			} else {
				$this->buffered .= $line . "\n";
			}
		}
		$this->buffered .= $pending_buffer;
		if($this->ended && strlen($this->buffered) === 0) {
			$this->emit('end');
		}
		if($this->closed && strlen($this->buffered) === 0) {
			$this->emit('close');
			$this->removeAllListeners();
		}
	}

	public function pause()
	{
		$this->isPaused = true;
		return parent::pause();
	}

	public function resume()
	{
		$this->isPaused = false;
		if(strlen($this->buffered) > 0) {
			$data = $this->buffered;
			$this->buffered = '';
			$this->processData($data);
		}
		if(!$this->isPaused) {
			parent::resume();
		}
	}
}
