<?php
/**
 * Http/Request.php
 *
 * @author Jérémy 'Jejem' Desvages <jejem@phyrexia.org>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
**/

namespace Phyrexia\Http;

class Request extends \GuzzleHttp\Psr7\Request implements \Psr\Http\Message\RequestInterface {
	public function __construct($method, $uri, array $headers = array(), $body = NULL, $protocolVersion = '1.1') {
		parent::__construct($method, $uri, $headers, $body, $protocolVersion);

		if (! in_array($this->getMethod(), array('HEAD', 'GET', 'POST')))
			throw new \InvalidArgumentException('Invalid or unsupported method '.$this->getMethod());

		if (! in_array($this->getUri()->getScheme(), array('http', 'https')))
			throw new \InvalidArgumentException('Invalid or unsupported scheme '.$this->getUri()->getScheme());

		if (! filter_var((string)$this->getUri(), FILTER_VALIDATE_URL))
			throw new \InvalidArgumentException('Invalid or unsupported URI '.(string)$this->getUri());
	}
}
