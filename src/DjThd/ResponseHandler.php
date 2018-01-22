<?php

namespace DjThd;

class ResponseHandler
{
	protected $requestData;
	protected $response;
	protected $totalLength;

	public function __construct($requestData, $response)
	{
		$this->requestData = $requestData;
		$this->response = $response;
		$this->totalLength = 0;
	}

	public function handle($output, $progress)
	{
		switch($this->response->getCode()) {
			case '401':
				$progress->write(str_repeat(" ", 120) . "\r");
				$output->write("401: " . $this->requestData . "\n");
				break;
			case '301':
				$headers = $this->response->getHeaders();
				$headers = array_change_key_case($headers, CASE_LOWER);
				$progress->write(str_repeat(" ", 120) . "\r");
				if(isset($headers['location'])) {
					$output->write("301: " . $this->requestData . " - " . $headers['location'] . "\n");
				} else {
					$output->write("301: " . $this->requestData . " - " . "NO LOCATION\n");
				}
				break;
			case '302':
				$headers = $this->response->getHeaders();
				$headers = array_change_key_case($headers, CASE_LOWER);
				if(isset($headers['location'])) {
					$output->write("302: " . $this->requestData . " - " . $headers['location'] . "\n");
				} else {
					$output->write("302: " . $this->requestData . " - " . "NO LOCATION\n");
				}
				break;
			case '200':
				$progress->write(str_repeat(" ", 120) . "\r");
				$output->write("200: " . $this->requestData . "\n");
				break;
			case '403':
				$progress->write(str_repeat(" ", 120) . "\r");
				$output->write("403: " . $this->requestData . "\n");
				break;
			case '404':
				break;
			default:
				$progress->write(str_repeat(" ", 120) . "\r");
				$output->write("UNKNOWN: " . $this->requestData . " - " . $this->response->getCode() . "\n");
				break;
		}
		//$this->response->on('data', function($data) {
		//	$this->totalLength += strlen($data);
		//});
		//$this->response->on('end', function() use ($output) {
		//	$output->write('Total length: ' . $this->totalLength);
		//});
	}
}
