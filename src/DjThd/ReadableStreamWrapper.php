<?php

namespace DjThd;

use \Evenement\EventEmitter;
use \React\Stream\ReadableStreamInterface;
use \React\Stream\WritableStreamInterface;
use \React\Stream\Util;

class ReadableStreamWrapper extends EventEmitter implements ReadableStreamInterface
{
	protected $stream;

	public function __construct(ReadableStreamInterface $stream)
	{
		$this->stream = $stream;
	}

	public function isReadable()
	{
		return $this->stream->isReadable();
	}

	public function pause()
	{
		return $this->stream->pause();
	}

	public function resume()
	{
		return $this->stream->resume();
	}

	public function pipe(WritableStreamInterface $dest, array $options = array())
	{
		return Util::pipe($this, $dest, $options);
	}

	public function close()
	{
		return $this->stream->close();
	}
}
