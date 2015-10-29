<?php
/**
 * Http/Response.php
 *
 * @author Jérémy 'Jejem' Desvages <jejem@phyrexia.org>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
 * @version 2.1.0
**/

namespace Phyrexia\Http;

class Response extends \GuzzleHttp\Psr7\Response implements \Psr\Http\Message\ResponseInterface {
	public function __toString() {
		return (string)$this->getBody();
	}
}
