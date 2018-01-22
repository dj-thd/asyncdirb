<?php

namespace DjThd;

class RateLimiter
{
	protected $loop;
	protected $inputStream;
	protected $maxProcesses;
	protected $callback = null;

	protected $isInputStreamPaused = false;
	protected $currentProcesses = 0;
	protected $queue = array();

	protected function pauseStream()
	{
		if(!$this->isInputStreamPaused) {
			$this->inputStream->pause();
		}
		$this->isInputStreamPaused = true;
	}

	protected function resumeStream()
	{
		if($this->isInputStreamPaused) {
			$this->inputStream->resume();
		}
		$this->isInputStreamPaused = false;
	}

	public function __construct($loop, $inputStream, $maxProcesses)
	{
		$this->loop = $loop;
		$this->inputStream = $inputStream;
		$this->maxProcesses = $maxProcesses;
		$this->pauseStream();
		$this->inputStream->on('data', array($this, 'handleStreamData'));
	}

	public function run($callback)
	{
		$this->callback = $callback;
		$this->queue = array();
		$this->concurrentProcesses = 0;
		$this->resumeStream();
	}

	public function handleStreamData($data)
	{
		if(!$this->callback) {
			$this->pauseStream();
			return;
		}

		if($this->currentProcesses > $this->maxProcesses) {
			$this->pauseStream();
			$this->enqueueItem($data);
		} else {
			$this->currentProcesses++;
			call_user_func($this->callback, $data);
		}
	}

	public function finishedProcess()
	{
		$this->currentProcesses--;
		$this->loop->futureTick(array($this, 'processPending'));
	}

	public function processPending()
	{
		if(!empty($this->queue)) {
			$item = array_pop($this->queue);
			$this->currentProcesses++;
			call_user_func($this->callback, $item);
		} else {
			$this->resumeStream();
		}
	}

	public function enqueueItem($item)
	{
		$this->queue[] = $item;
	}
}
