<?php

namespace DjThd;

class DirectoryListerArgvParser
{
	protected $options = array();

	public function __construct($argv)
	{
		// Skip first element (program name)
		$command = array_shift($argv);

		// First argument: URL
		$url = array_shift($argv);

		// Second argument: wordlist
		$wordlist = array_shift($argv);
		if(is_null($wordlist)) {
			$wordlist = '/usr/share/wordlists/dirb/common.txt';
		}

		// Check
		if(is_null($url)) {
			$this->showUsage($command);
			throw new \Exception('Invalid commandline arguments');
		}

		// Default options
		$this->options = array(
			'url' => $url,
			'wordlist' => $wordlist,
			'user_agent' => 'dj.thd/asyncdirb 1.0',
			'cookie' => false,
			'headers' => array(),
			'outfile' => false,
			'silent' => false,
			'auth' => false,
			'extensions' => false
		);

		// Next arguments: options
		while(!empty($argv)) {
			$option = array_shift($argv);
			switch($option) {
				case '-a':
					$userAgent = array_shift($argv);
					$this->checkOption($option, $userAgent);
					$this->options['user_agent'] = $userAgent;
					break;

				case '-c':
					$cookie = array_shift($argv);
					$this->checkOption($option, $cookie);
					$this->options['cookie'] = $cookie;
					break;

				case '-H':
					$header = array_shift($argv);
					$this->checkOption($option, $header);
					$headers = explode(':', $header, 2);
					// TODO: check
					$headers[1] = ltrim($headers[1]);
					$this->options['headers'][$headers[0]] = $headers[1];
					break;

				case '-o':
					$outfile = array_shift($argv);
					$this->checkOption($option, $outfile);
					$this->options['outfile'] = $outfile;
					break;

				case '-S':
					$this->options['silent'] = true;
					break;

				case '-u':
					$auth = array_shift($argv);
					$this->checkOption($option, $auth);
					$this->options['auth'] = $auth;
					break;

				case '-X':
					$extensions = array_shift($argv);
					$this->checkOption($option, $extensions);
					$this->options['extensions'] = array_map(function($i) { return ltrim($i, '.'); }, explode(',', $extensions));
					break;

				case '-f':
				case '-i':
				case '-l':
				case '-N':
				case '-p':
				case '-r':
				case '-R':
				case '-t':
				case '-v':
				case '-W':
				case '-x':
					$this->showNotImplemented($option);
					throw new \Exception('Not implemented commandline argument');
					break;

				default:
					$this->showUnknown($option, $command);
					throw new \Exception('Unknown commandline argument');
					break;
			}
		}
	}

	public function getOptions()
	{
		return $this->options;
	}

	public function printOptions()
	{
		$this->printBanner();

		$startTime = date('D M d H:i:s Y');
		$extnum = 0;
		if($this->options['extensions']) {
			$extlist = implode(',', $this->options['extensions']);
		} else {
			$extlist = '<none>';
		}

		fputs(STDERR, <<<EOD
START_TIME: $startTime
OUTPUT_FILE: {$this->options['outfile']}
URL_BASE: {$this->options['url']}
WORDLIST_FILES: {$this->options['wordlist']}
USER_AGENT: {$this->options['user_agent']}
COOKIE: {$this->options['cookie']}
EXTENSIONS_LIST: $extlist [NUM = $extnum]

-----------------

---- Scanning URL: {$this->options['url']} ----


EOD
		);
	}

	protected function printBanner()
	{
		fputs(STDERR, <<<EOD

-----------------
AsyncDirb v1.0
By dj.thd
Credit to The Dark Raver for the original dirb
-----------------


EOD
		);
	}

	protected function showUsage($command)
	{
		$this->printBanner();

		fputs(STDERR, <<<EOD
$command <url_base> [<wordlist_file(s)>] [options]

========================= NOTES =========================
 <url_base> : Base URL to scan.
 <wordlist_file(s)> : List of wordfiles. (wordfile1,wordfile2,wordfile3...)

======================== OPTIONS ========================
 NOTE: those beggining with /!\ have not been implemented.

 -a <agent_string> : Specify your custom USER_AGENT.
 -c <cookie_string> : Set a cookie for the HTTP request.
 /!\ -f : Fine tunning of NOT_FOUND (404) detection.
 -H <header_string> : Add a custom header to the HTTP request.
 /!\ -i : Use case-insensitive search.
 /!\ -l : Print "Location" header when found.
 /!\ -N <nf_code>: Ignore responses with this HTTP code.
 -o <output_file> : Save output to disk.
 /!\ -p <proxy[:port]> : Use this proxy. (Default port is 1080)
 /!\ -P <proxy_username:proxy_password> : Proxy Authentication.
 /!\ -r : Don't search recursively.
 /!\ -R : Interactive recursion. (Asks for each directory)
 -S : Silent Mode. Don't show tested words. (For dumb terminals)
 /!\ -t : Don't force an ending '/' on URLs.
 -u <username:password> : HTTP Authentication.
 -v : Show also NOT_FOUND pages.
 /!\ -w : Don't stop on WARNING messages.
 -X <extensions> / -x <exts_file> : Append each word with this extensions.
 /!\ -z <millisecs> : Add a milliseconds delay to not cause excessive Flood.

======================== EXAMPLES =======================
 $command http://url/directory/ (Simple Test)
 $command http://url/ -X .html (Test files with '.html' extension)
 $command http://url/ /usr/share/dirb/wordlists/vulns/apache.txt (Test with apache.txt wordlist)
 $command https://secure_url/ (Simple Test with SSL)


EOD
);
	}

	protected function showUnknown($option, $command)
	{
		fputs(STDERR, "Unknown option: $option\n");
		$this->showUsage($command);
	}

	protected function checkOption($option, $value)
	{
		if(!is_string($value)) {
			fputs(STDERR, "Invalid value for option $option\n");
			throw new \Exception('Invalid value for commandline argument');
		}
	}
}
