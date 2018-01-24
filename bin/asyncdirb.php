#!/usr/bin/env php
<?php

require dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

$loop = React\EventLoop\Factory::create();

// Manual config that is not configurable yet via argv
$method = 'HEAD';
$max_concurrent_requests = 10;
$dns_server = '8.8.8.8';
$request_timeout = 10;

// Parse options from argv
$parser = new DjThd\DirectoryListerArgvParser($argv);
$options = $parser->getOptions();

// Print options
$parser->printOptions();

// Replace host from URL into IP and parse username:password from URL if present
$url = $options['url'];
$parsed_url = parse_url($url);
$parsed_url['scheme'] = isset($parsed_url['scheme']) ? $parsed_url['scheme'] : 'http';
$parsed_url['path'] = isset($parsed_url['path']) ? $parsed_url['path'] : '/';
if(!isset($parsed_url['host'])) {
	throw new \Exception('Invalid host');
}
if(isset($parsed_url['user']) && isset($parsed_url['pass'])) {
	$options['auth'] = $parsed_url['user'].':'.$parsed_url['pass'];
}
$host = $parsed_url['host'];
$ip = gethostbyname($host);
$url = $parsed_url['scheme'].'://'.$ip.$parsed_url['path'].(isset($parsed_url['query']) ? '?'.$parsed_url['query'] : '');
$options['url'] = $url;
if(!isset($options['headers']['Host'])) {
	$options['headers']['Host'] = $host;
}

// Apply auth
if($options['auth'] !== false && !isset($options['headers']['Authorization'])) {
	$options['headers']['Authorization'] = 'Basic ' . base64_encode($options['auth']);
}

// Apply cookie
if($options['cookie'] !== false && !isset($options['headers']['Cookie'])) {
	$options['headers']['Cookie'] = $options['cookie'];
}

// Apply user agent
if($options['user_agent'] !== false && !isset($options['headers']['User-Agent'])) {
	$options['headers']['User-Agent'] = $options['user_agent'];
}

// For fast closing
$options['headers']['Connection'] = 'close';

// Accept */*
$options['headers']['Accept'] = '*/*';

// Add not configurable options
$options['method'] = $method;
$options['max_concurrent_requests'] = $max_concurrent_requests;

// Build connector parameters
$connector = new React\Socket\Connector($loop, array(
	'tcp' => true,
	'tls' => array(
		'verify_peer' => false,
		'verify_peer_name' => false,
		'allow_self_signed' => true,
		'disable_compression' => false
	),
	'dns' => $dns_server,
	'timeout' => $request_timeout,
	'unix' => false
));

// Http client
$client = new React\HttpClient\Client($loop, $connector);

// Input streams
$inputFile = new React\Stream\ReadableResourceStream(fopen($options['wordlist'], 'r'), $loop);
$inputLines = new DjThd\StreamLineReader($inputFile);

// Apply extensions
if($options['extensions'] !== false) {
	$wordlist = new DjThd\StreamExtensionAdder($inputLines, $options['extensions']);
} else {
	$wordlist = $inputLines;
}

// Output streams
$output = new React\Stream\WritableResourceStream(STDOUT, $loop);
$progress = new React\Stream\WritableResourceStream(STDERR, $loop);

// Apply output file
if($options['outfile'] !== false) {
	$outputFile = new React\Stream\WritableResourceStream(fopen($options['outfile'], 'w'), $loop);
	$output = new DjThd\CombinedOutputStream($output, $outputFile);
}

// Parameter array
$parameters = array(
	'loop' => $loop,
	'wordlistStream' => $wordlist,
	'httpClient' => $client,
	'outputStream' => $output,
	'progressStream' => $progress
);

// Run
$directoryLister = new DjThd\DirectoryListerCore($parameters, $options);
$directoryLister->run();

$loop->run();
