<?php

namespace DjThd;

use Evenement\EventEmitter;

class DirectoryListerCore extends EventEmitter
{
	protected $loop = null;
	protected $wordlistStream = null;
	protected $httpClient = null;
	protected $outputStream = null;
	protected $progressStream = null;
	protected $options = null;

	protected $rateLimiter = null;

	public function __construct(array $parameters, array $options)
	{
		$this->loop = $parameters['loop'];
		$this->wordlistStream = $parameters['wordlistStream'];
		$this->httpClient = $parameters['httpClient'];
		$this->outputStream = $parameters['outputStream'];
		$this->progressStream = $parameters['progressStream'];
		$this->options = $options;

		$this->rateLimiter = new RateLimiter($this->loop, $this->wordlistStream, $options['max_concurrent_requests']);

		if(strpos($this->options['url'], '*') === false) {
			if(!preg_match('/\/$/', $this->options['url'])) {
				$this->options['url'] .= '/';
			}
			$this->options['url'] .= '*';
		}
	}

	public function run()
	{
		$this->rateLimiter->on('finish', function() {
			$this->emit('finish');
		});

		$this->rateLimiter->run(function($data) {
			$this->emitRequest($data, array($this->rateLimiter, 'finishedProcess'), array($this->rateLimiter, 'enqueueItem'));
		});
	}

	public function emitRequest($word, $callbackFinish, $callbackError)
	{
		// Word with size 0 -> return
		if(strlen($word) == 0) {
			call_user_func($callbackFinish);
			return;
		}

		// Build URL with word
		$url = str_replace('*', $word, $this->options['url']);
		if(strpos($url, ' ') !== false) {
			$url = str_replace(' ', '%20', $url);
		}

		// Write progress message
		if(!$this->options['silent']) {
			$this->progressStream->write("Testing: $url" . str_repeat(" ", 120-strlen($url)) . "\r");
		}

		// Build request
		$request = $this->httpClient->request($this->options['method'], $url, $this->options['headers'], '1.1');

		// Add response handler
		$request->on('response', function($response) use ($url) {
			$handler = new ResponseHandler($url, $response);
			$handler->handle($this->outputStream, $this->progressStream);
		});

		// Add error handler (put word again into queue)
		$request->on('error', function($error) use ($callbackError, $word) {
			call_user_func($callbackError, $word);
		});

		// Add close handler
		$request->on('close', $callbackFinish);

		// Send request
		$request->end();
	}
}
