<?php
/**
 * Http/Client.php
 *
 * @author Jérémy 'Jejem' Desvages <jejem@phyrexia.org>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
**/

namespace Phyrexia\Http;

class Client {
	private $request;
	private $response;
	private $options = array();

	private $errno;
	private $error;

	private $followRedirects = true;
	private $maxTries = 3;
	private $connectTimeout = 5;
	private $timeout = 5;

	private $headersSize = 0;

	public function __construct($url = NULL, $method = 'GET', array $options = array()) {
		if (! is_null($url)) {
			$uri = new Uri($url);
			if ($uri->getScheme() == '')
				$url = 'http://'.$url;

			$this->request = new Request($method, $url);
			$this->request = $this->request->withHeader('Expect', '');
			$this->request = $this->request->withHeader('Connection', 'close');
			$this->request = $this->request->withHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

			if (array_key_exists(CURLOPT_HTTPHEADER, $options) && is_array($options[CURLOPT_HTTPHEADER])) {
				foreach ($options[CURLOPT_HTTPHEADER] as $header) {
					$buf = explode(':', $header, 2);
					$this->request = $this->request->withHeader($buf[0], ltrim($buf[1]));
				}
			}
		}

		if (array_key_exists(CURLOPT_FOLLOWLOCATION, $options)) {
			$this->setFollowRedirects($options[CURLOPT_FOLLOWLOCATION]);
		}

		if (array_key_exists(CURLOPT_CONNECTTIMEOUT, $options)) {
			$this->setConnectTimeout($options[CURLOPT_CONNECTTIMEOUT]);
		}

		if (array_key_exists(CURLOPT_TIMEOUT, $options)) {
			$this->setTimeout($options[CURLOPT_TIMEOUT]);
		}

		$this->options = $options;
	}

	public function getRequest() {
		return $this->request;
	}

	public function setRequest(\Psr\Http\Message\RequestInterface $request) {
		$this->request = $request;

		return $this;
	}

	public function getResponse() {
		return $this->response;
	}

	public function setResponse(\Psr\Http\Message\ResponseInterface $response) {
		$this->response = $response;

		return $this;
	}

	public function getErrno() {
		return $this->errno;
	}

	public function getError() {
		return $this->error;
	}

	public function setMethod($method) {
		$this->request = $this->request->withMethod($method);

		return $this;
	}

	public function setUri($uri) {
		$this->request = $this->request->withUri($uri);

		return $this;
	}

	public function setUrl($url) {
		return $this->setUri($url);
	}

	public function getFollowRedirects() {
		return $this->followRedirects;
	}

	public function setFollowRedirects($followRedirects) {
		$this->followRedirects = (bool)$followRedirects;

		return $this;
	}

	public function getMaxTries() {
		return $this->maxTries;
	}

	public function setMaxTries($maxTries) {
		$this->maxTries = (int)$maxTries;

		return $this;
	}

	public function getConnectTimeout() {
		return $this->connectTimeout;
	}

	public function setConnectTimeout($connectTimeout) {
		$this->connectTimeout = (int)$connectTimeout;

		return $this;
	}

	public function getTimeout() {
		return $this->timeout;
	}

	public function setTimeout($timeout) {
		$this->timeout = (int)$timeout;

		return $this;
	}

	public function send($request = NULL) {
		if (is_object($request) && $request instanceof \Psr\Http\Message\RequestInterface)
			$this->request = $request;

		if (! is_object($this->request) || ! $this->request instanceof \Psr\Http\Message\RequestInterface)
			throw new ClientException('No valid Request to execute', 255);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_ENCODING, '');

		curl_setopt_array($ch, $this->options);

		curl_setopt($ch, CURLOPT_URL, (string)$this->request->getUri());
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

		$headers = array();
		foreach ($this->request->getHeaders() as $k => $v)
			$headers[] = $k.': '.$v[0];
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		if ($this->request->getUri()->getUserInfo() != '')
			curl_setopt($ch, CURLOPT_USERPWD, $this->request->getUri()->getUserInfo());

		switch ($this->request->getMethod()) {
			case 'HEAD':
				curl_setopt($ch, CURLOPT_NOBODY, 1);
				break;
			case 'GET':
			default:
				curl_setopt($ch, CURLOPT_HTTPGET, 1);
				break;
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, 1);
				if ((string)$this->request->getBody() != '')
					curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$this->request->getBody());
				break;
		}

		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $h) { $this->headersSize += strlen($h); return strlen($h); });

		$this->headersSize = 0;
		$ret = curl_exec($ch);
		$this->parseOutput($ch, $ret);

		$i = 1;
		while ((curl_errno($ch) == CURLE_COULDNT_CONNECT || curl_errno($ch) == CURLE_RECV_ERROR || curl_errno($ch) == CURLE_OPERATION_TIMEOUTED || curl_errno($ch) == CURLE_GOT_NOTHING) && $i < $this->maxTries) {
			usleep(rand(1000000, 3000000));
			$this->headersSize = 0;
			$ret = curl_exec($ch);
			$this->parseOutput($ch, $ret);
			$i++;
		}

		$this->errno = curl_errno($ch);
		$this->error = curl_error($ch);

		if (! is_null($this->response) && substr($this->response->getStatusCode(), 0, 1) == 3 && $this->getFollowRedirects()) {
			$this->setUri(new Uri(curl_getinfo($ch, CURLINFO_REDIRECT_URL)));
			return $this->send();
		}

		curl_close($ch);

		if (! $ret || $this->errno == CURLE_COULDNT_CONNECT || $this->errno == CURLE_RECV_ERROR || $this->errno == CURLE_OPERATION_TIMEOUTED || $this->errno == CURLE_GOT_NOTHING)
			throw new ClientException($this->error, $this->errno);

		return $this->response;
	}

	public static function head($url, $queryString = NULL, array $options = array()) {
		$c = new Client($url, 'HEAD', $options);
		$c->setQueryString($queryString);
		return $c->send();
	}

	public static function get($url, $queryString = NULL, array $options = array()) {
		$c = new Client($url, 'GET', $options);
		$c->setQueryString($queryString);
		return $c->send();
	}

	public static function post($url, $postData = NULL, $queryString = NULL, array $options = array()) {
		$c = new Client($url, 'POST', $options);
		$c->setPostData($postData);
		$c->setQueryString($queryString);
		return $c->send();
	}

	public function setQueryString($queryString) {
		if (is_array($queryString) || is_object($queryString))
			$queryString = http_build_query($queryString);

		if (is_string($queryString))
			$this->request = $this->request->withUri($this->request->getUri()->withQuery(($this->request->getUri()->getQuery() != '')?$this->request->getUri()->getQuery().'&'.$queryString:$queryString));

		return true;
	}

	public function setPostData($postData) {
		$this->setMethod('POST');

		if (is_array($postData) || is_object($postData))
			$postData = http_build_query($postData);

		if (is_string($postData))
			$this->request = $this->request->withBody(\GuzzleHttp\Psr7\stream_for($postData));

		return true;
	}

	private function parseOutput($ch, $ret) {
		if (! is_resource($ch) || ! $ret)
			return false;

		$headers_size = $this->headersSize;
		if (is_numeric($headers_size) && $headers_size > 0) {
			$headers = trim(substr($ret, 0, $headers_size));
			$body = substr($ret, $headers_size);
		} else
			list($headers, $body) = preg_split('/\r\n\r\n|\r\r|\n\n/', $ret, 2);
		if (! $body)
			$body = '';

		$raw_headers = $headers;
		$header_lines = explode("\r\n", $headers);
		$headers = array();
		foreach ($header_lines as $header_line) {
			$buf = explode(': ', $header_line);
			if (count($buf) == 1)
				continue;

			$headers[$buf[0]] = $buf[1];
		}

		$this->response = new Response(curl_getinfo($ch, CURLINFO_HTTP_CODE), $headers, $body);

		$this->response->setHeadersSize($headers_size);
		$this->response->setRawHeaders($raw_headers);
		$this->response->setRawBody($body);
		$this->response->setRawOutput($ret);

		return true;
	}
}
