<?php
/**
 * Http/Request.php
 *
 * @author Jérémy 'Jejem' Desvages <jejem@phyrexia.org>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
 * @version 1.1.0
**/

namespace Phyrexia\Http;

class Request {
	private $url = NULL;
	private $method = 'GET';

	private $contentType = 'application/x-www-form-urlencoded';
	private $userAgent = NULL;
	private $postFields = NULL;
	private $httpCredentials = NULL;
	private $followRedirects = true;

	private $responseCode = 0;
	private $responseContentType = NULL;
	private $responseHeader = NULL;
	private $responseBody = NULL;

	private $responseErrno = 0;
	private $responseError = NULL;

	private $maxRequests = 5;
	private $connectTimeout = 5;
	private $requestTimeout = 10;

	public function __construct($url=NULL, $method='GET', $options=array()) {
		$this->setUrl($url);
		$this->setMethod($method);

		foreach ($options as $k => $v)
			$this->$k = $v;
	}

	private function reset() {
		$this->contentType = 'application/x-www-form-urlencoded';
		$this->userAgent = NULL;
		$this->postFields = NULL;
		$this->httpCredentials = NULL;
		$this->followRedirects = true;
	}

	public function setUrl($url) {
		if (! is_string($url))
			return false;

		if (! filter_var($url, FILTER_VALIDATE_URL))
			return false;

		$this->url = $url;

		return true;
	}

	public function setMethod($method) {
		if (! is_string($method))
			return false;

		if (! in_array($method, array('HEAD', 'GET', 'POST')))
			return false;

		$this->method = $method;

		return true;
	}

	public function setContentType($contentType) {
		if (! is_string($contentType))
			return false;

		$this->contentType = $contentType;

		return true;
	}

	public function setUserAgent($userAgent) {
		if (! is_string($userAgent))
			return false;

		$this->userAgent = $userAgent;

		return true;
	}

	public function setPostFields($postData) {
		if (is_array($postData) && count($postData) > 0)
			$postData = http_build_query($postData);

		if (! is_string($postData))
			return false;

		$this->postFields = $postData;

		return true;
	}

	public function setHttpCredentials($httpCredentials) {
		if (! is_string($httpCredentials))
			return false;

		$this->httpCredentials = $httpCredentials;

		return true;
	}

	public function setFollowRedirects($followRedirects) {
		if (! is_bool($followRedirects))
			return false;

		$this->followRedirects = $followRedirects;

		return true;
	}

	public function setConnectTimeout($connectTimeout) {
		if (! is_numeric($connectTimeout))
			return false;

		$this->connectTimeout = (int)$connectTimeout;

		return true;
	}

	public function setRequestTimeout($requestTimeout) {
		if (! is_numeric($requestTimeout))
			return false;

		$this->requestTimeout = (int)$requestTimeout;

		return true;
	}

	public function getResponseCode() {
		return $this->responseCode;
	}

	public function getResponseContentType() {
		return $this->responseContentType;
	}

	public function getResponseHeader() {
		return $this->responseHeader;
	}

	public function getResponseBody() {
		return $this->responseBody;
	}

	public function getResponseErrno() {
		return $this->responseErrno;
	}

	public function getResponseError() {
		return $this->responseError;
	}

	public function send() {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $this->url);
		if (is_string($this->userAgent) && strlen($this->userAgent) > 0)
			curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
		if (is_string($this->httpCredentials) && strlen($this->httpCredentials) > 0)
			curl_setopt($ch, CURLOPT_USERPWD, $this->httpCredentials);
		switch ($this->method) {
			case 'HEAD':
				curl_setopt($ch, CURLOPT_NOBODY, 1);
				break;
			case 'GET':
				curl_setopt($ch, CURLOPT_HTTPGET, 1);
				break;
			case 'POST':
			default:
				curl_setopt($ch, CURLOPT_POST, 1);
				if (! is_null($this->postFields))
					curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postFields);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: close', 'Content-Type: '.$this->contentType));
				break;
		}
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->requestTimeout);

		$ret = curl_exec($ch);

		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$this->responseHeader = trim(substr($ret, 0, $headerSize));
		$this->responseBody = substr($ret, $headerSize);

		$i = 1;
		while ((curl_errno($ch) == CURLE_COULDNT_CONNECT || curl_errno($ch) == CURLE_RECV_ERROR || curl_errno($ch) == CURLE_OPERATION_TIMEOUTED || curl_errno($ch) == CURLE_GOT_NOTHING) && $i < $this->maxRequests) {
			usleep(rand(1000000, 3000000));
			$this->responseBody = curl_exec($ch);
			$i++;

			if ((curl_errno($ch) == CURLE_COULDNT_CONNECT || curl_errno($ch) == CURLE_RECV_ERROR || curl_errno($ch) == CURLE_OPERATION_TIMEOUTED || curl_errno($ch) == CURLE_GOT_NOTHING) && $i > $this->maxRequests)
				throw new \Exception(curl_error($ch), curl_errno($ch));
		}

		$this->responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->responseContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

		$this->responseErrno = curl_errno($ch);
		$this->responseError = curl_error($ch);

		if (in_array($this->getResponseCode(), array(301, 302)) && ! is_null($this->followRedirects) && $this->followRedirects === true) {
			$this->setUrl(curl_getinfo($ch, CURLINFO_REDIRECT_URL));
			return $this->send();
		}

		curl_close($ch);

		$this->reset();

		if ($this->method == 'HEAD')
			return true;

		return $this->responseBody;
	}
}
