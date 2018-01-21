<?php

namespace DjThd;

use \Evenement\EventEmitter;
use \React\Stream\ReadableStreamInterface;
use \React\Stream\WritableStreamInterface;
use \React\Stream\Util;

final class StreamExtensionAdder extends ReadableStreamWrapper implements ReadableStreamInterface
{
	protected $stream;
	protected $pending = array();
	protected $extensionList;
	protected $isPaused = false;

	public function __construct(ReadableStreamInterface $stream, array $extensionList = array())
	{
		parent::__construct($stream);
		$this->extensionList = $extensionList;
		$stream->on('data', function($data) {
			$this->processWord($data);
		});
		$stream->on('close', function() {
			$this->emit('close');
			$this->removeAllListeners();
		});
		$stream->on('error', function() {
			$this->emit('error', func_get_args());
		});
		$stream->on('end', function() {
			$this->emit('end');
		});
	}

	public function processWord($word)
	{
		$words = array();
		if(strlen($word) == 0) {
			return;
		}
		while(!empty($this->pending)) {
			$this->emitData(array_pop($this->pending));
		}
		foreach($this->extensionList as $extension) {
			if(strlen($extension) > 0) {
				$words[] = $word . ".$extension";
			} else {
				$words[] = $word;
			}
		}
		foreach($words as $word) {
			$this->emitData($word);
		}
	}

	public function emitData($data)
	{
		if(!$this->isPaused) {
			$this->emit('data', array($data));
		} else {
			$this->pending[] = $data;
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
		if(!empty($this->pending)) {
			while(!empty($this->pending)) {
				if($this->isPaused) {
					break;
				} else {
					$this->emitData(array_pop($this->pending));
				}
			}
		}
		if(!$this->isPaused) {
			parent::resume();
		}
	}
}
