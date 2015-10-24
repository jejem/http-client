<?php
/**
 * Http/Client.php
 *
 * @author Jérémy 'Jejem' Desvages <jejem@phyrexia.org>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
 * @version 1.3.0
**/

namespace Phyrexia\Http;

class Client {
	private $url;
	private $method = 'GET';

	private $contentType = 'application/x-www-form-urlencoded';
	private $userAgent;
	private $postFields;
	private $followRedirects = true;

	private $responseCode;
	private $responseContentType;
	private $requestHeader;
	private $responseFull;
	private $responseHeaderSize;
	private $responseHeader;
	private $responseBody;

	private $responseErrno;
	private $responseError;

	private $maxRequests = 5;
	private $connectTimeout = 5;
	private $requestTimeout = 10;

	public function __construct(Uri $url, $method = 'GET', array $options = array()) {
		$this->setUrl($url);
		$this->setMethod($method);

		foreach ($options as $k => $v)
			$this->$k = $v;
	}

	public function __toString() {
		return print_r($this, true);
	}

	private function reset() {
		$this->contentType = 'application/x-www-form-urlencoded';
		$this->userAgent = NULL;
		$this->postFields = NULL;
		$this->followRedirects = true;
	}

	public function setUrl(Uri $url) {
		if (! filter_var((string)$url, FILTER_VALIDATE_URL))
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

	public function setFollowRedirects($followRedirects) {
		if (! is_bool($followRedirects))
			return false;

		$this->followRedirects = $followRedirects;

		return true;
	}

	public function setMaxRequests($maxRequests) {
		if (! is_numeric($maxRequests))
			return false;

		$this->maxRequests = (int)$maxRequests;

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

	public function getRequestHeader() {
		return $this->requestHeader;
	}

	public function getResponseFull() {
		return $this->responseFull;
	}

	public function getResponseHeaderSize() {
		return $this->responseHeaderSize;
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
		if (! $this->url instanceof Uri)
			return false;

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, (string)$this->url);
		if (! is_null($this->userAgent))
			curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
		if ($this->url->getUserInfo() != '')
			curl_setopt($ch, CURLOPT_USERPWD, $this->url->getUserInfo());
		switch ($this->method) {
			case 'HEAD':
				curl_setopt($ch, CURLOPT_NOBODY, 1);
				break;
			case 'GET':
			default:
				curl_setopt($ch, CURLOPT_HTTPGET, 1);
				break;
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, 1);
				if (! is_null($this->postFields))
					curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postFields);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:', 'Connection: close', 'Content-Type: '.$this->contentType));
				break;
		}
		curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->requestTimeout);

		$ret = curl_exec($ch);
		$this->parseOutput($ch, $ret);

		$i = 1;
		while ((curl_errno($ch) == CURLE_COULDNT_CONNECT || curl_errno($ch) == CURLE_RECV_ERROR || curl_errno($ch) == CURLE_OPERATION_TIMEOUTED || curl_errno($ch) == CURLE_GOT_NOTHING) && $i < $this->maxRequests) {
			usleep(rand(1000000, 3000000));
			$ret = curl_exec($ch);
			$this->parseOutput($ch, $ret);
			$i++;
		}

		$this->responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->responseContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

		$this->responseErrno = curl_errno($ch);
		$this->responseError = curl_error($ch);

		if (in_array($this->responseCode, array(301, 302)) && ! is_null($this->followRedirects) && $this->followRedirects === true) {
			$this->setUrl(curl_getinfo($ch, CURLINFO_REDIRECT_URL));
			return $this->send();
		}

		curl_close($ch);

		$this->reset();

		if (! $ret || $this->responseErrno == CURLE_COULDNT_CONNECT || $this->responseErrno == CURLE_RECV_ERROR || $this->responseErrno == CURLE_OPERATION_TIMEOUTED || $this->responseErrno == CURLE_GOT_NOTHING)
			throw new ClientException($this->responseError, $this->responseErrno);

		if ($this->method == 'HEAD')
			return true;

		return $this->responseBody;
	}

	private function parseOutput(&$ch, $ret) {
		if (! $ret)
			return false;

		$this->requestHeader = trim(curl_getinfo($ch, CURLINFO_HEADER_OUT));

		$this->responseFull = $ret;
		$this->responseHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		if (is_numeric($this->responseHeaderSize) && $this->responseHeaderSize > 0) {
			$this->responseHeader = trim(substr($ret, 0, $this->responseHeaderSize));
			$this->responseBody = substr($ret, $this->responseHeaderSize);
		} else
			list($this->responseHeader, $this->responseBody) = preg_split('/\r\n\r\n|\r\r|\n\n/', $ret, 2);

		return true;
	}
}
