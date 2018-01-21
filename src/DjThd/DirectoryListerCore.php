<?php

namespace DjThd;

class DirectoryListerCore
{
	protected $loop = null;
	protected $wordlistStream = null;
	protected $httpClient = null;
	protected $outputStream = null;
	protected $progressStream = null;
	protected $options = null;

	protected $concurrentRequests = 0;
	protected $queuedRequests = array();

	public function __construct(array $parameters, array $options)
	{
		$this->loop = $parameters['loop'];
		$this->wordlistStream = $parameters['wordlistStream'];
		$this->httpClient = $parameters['httpClient'];
		$this->outputStream = $parameters['outputStream'];
		$this->progressStream = $parameters['progressStream'];
		$this->options = $options;

		$this->concurrentRequests = 0;
		$this->queuedRequests = array();

		if(!preg_match('/\/$/', $this->options['url'])) {
			$this->options['url'] .= '/';
		}

		$this->wordlistStream->on('data', function($word) {
			$this->emitRequest($word);
		});

		$this->wordlistStream->on('close', function() {
			while(!empty($this->queuedRequests) && $this->concurrentRequests <= $this->options['max_concurrent_requests']) {
				$word = array_pop($this->queuedRequests);
				$this->emitRequest($word);
			}
		});
	}

	public function emitRequest($word)
	{
		// Word with size 0 -> return
		if(strlen($word) == 0) {
			return;
		}

		// Too many concurrent requests, pause stream and enqueue word
		if($this->concurrentRequests > $this->options['max_concurrent_requests']) {
			$this->wordlistStream->pause();
			$this->queuedRequests[] = $word;
			return;
		}

		// Build URL with word
		$url = $this->options['url'] . $word;
		if(strpos($url, ' ') !== false) {
			$url = str_replace(' ', '%20', $url);
		}

		// Write progress message
		if(!$this->options['silent']) {
			$this->progressStream->write("Testing: $url" . str_repeat(" ", 120-strlen($url)) . "\r");
		}

		// Build request
		$request = $this->httpClient->request($this->options['method'], $url, $this->options['headers']);

		// Add response handler
		$request->on('response', function($response) use ($url) {
			$handler = new ResponseHandler($url, $response);
			$handler->handle($this->outputStream, $this->progressStream);
		});

		// Add error handler (put word again into queue)
		$request->on('error', function($error) use ($word) {
			$this->progressStream->write("ERROR: $word, $error\n");
			$this->queuedRequests[] = $word;
		});

		// Add close handler
		$request->on('close', function() {

			// Decrement request counter
			$this->concurrentRequests--;

			// If we are into concurrent request limit, resume streams
			if($this->concurrentRequests <= $this->options['max_concurrent_requests']) {

				// Get words from queue and emit requests
				while(!empty($this->queuedRequests) && $this->concurrentRequests <= $this->options['max_concurrent_requests']) {
					$word = array_pop($this->queuedRequests);
					$this->emitRequest($word);
				}

				// Resume input stream
				$this->wordlistStream->resume();
			}
		});

		// Increment request counter
		$this->concurrentRequests++;

		// Send request
		$request->end();
	}
}
