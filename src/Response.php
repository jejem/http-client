<?php
/**
 * Http/Response.php
 *
 * @author Jérémy 'Jejem' Desvages <jejem@phyrexia.org>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
**/

namespace Phyrexia\Http;

class Response extends \GuzzleHttp\Psr7\Response implements \Psr\Http\Message\ResponseInterface {
	private $headersSize;
	private $rawHeaders;
	private $rawBody;
	private $rawOutput;

	public function __toString() {
		return (string)$this->getBody();
	}

	public function getHeadersSize() {
		return $this->headersSize;
	}

	public function setHeadersSize($headersSize) {
		$this->headersSize = $headersSize;

		return true;
	}

	public function getRawHeaders() {
		return $this->rawHeaders;
	}

	public function setRawHeaders($rawHeaders) {
		$this->rawHeaders = $rawHeaders;

		return true;
	}

	public function getRawBody() {
		return $this->rawBody;
	}

	public function setRawBody($rawBody) {
		$this->rawBody = $rawBody;

		return true;
	}

	public function getRawOutput() {
		return $this->rawOutput;
	}

	public function setRawOutput($rawOutput) {
		$this->rawOutput = $rawOutput;

		return true;
	}
}
