<?php
/**
 * Http/Uri.php
 *
 * @author Jérémy 'Jejem' Desvages <jejem@phyrexia.org>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
 * @version 1.3.0
**/

namespace Phyrexia\Http;

class Uri implements \Psr\Http\Message\UriInterface {
	private $uri;

	public function __construct($uri = NULL) {
		$this->uri = $uri;
	}

	public function getUri() {
		return $this->buildUri();
	}

	public function getUriParts() {
		return array(
			'Scheme'	=>	$this->getScheme(),
			'UserInfo'	=>	$this->getUserInfo(),
			'Host'		=>	$this->getHost(),
			'Port'		=>	$this->getPort(),
			'Path'		=>	$this->getPath(),
			'Query'		=>	$this->getQuery(),
			'Fragment'	=>	$this->getFragment()
		);
	}

	public function buildUri(array $parts = array()) {
		$parts = array_merge($this->getUriParts(), $parts);

		$ret = '';

		$buf = $parts['Scheme'];
		if ($buf != '')
			$ret .= $buf.':';

		if ($parts['UserInfo'] != '' || $parts['Host'] != '' || ! is_null($parts['Port']))
			$ret .= '//';

		$buf = $parts['UserInfo'];
		if ($buf != '')
			$ret .= $buf.'@';

		$ret .= $parts['Host'];

		$buf = $parts['Port'];
		if (! is_null($buf))
			$ret .= ':'.$buf;

		$buf = $parts['Path'];
		if ($buf != '') {
			if (($parts['UserInfo'] != '' || $parts['Host'] != '' || ! is_null($parts['Port'])) && substr($buf, 0, 1) != '/') {
				$buf = '/'.$buf;
			} elseif (($parts['UserInfo'] == '' && $parts['Host'] == '' && is_null($parts['Port'])) && substr($buf, 0, 2) == '//') {
				while (substr($buf, 0, 2) == '//')
					$buf = substr($buf, 1);
			}
			$ret .= $buf;
		}

		$buf = $parts['Query'];
		if ($buf != '')
			$ret .= '?'.$buf;

		$buf = $parts['Fragment'];
		if ($buf != '')
			$ret .= '#'.$buf;

		return $ret;
	}

	public function getScheme() {
		$buf = parse_url($this->uri, PHP_URL_SCHEME);
		if (! $buf || is_null($buf))
			return '';

		return strtolower($buf);
	}

	public function getAuthority() {
		$ret = '';

		$buf = $this->getUserInfo();
		if ($buf != '')
			$ret .= $buf.'@';

		$ret .= $this->getHost();

		$buf = $this->getPort();
		if (! is_null($buf))
			$ret .= ':'.$buf;

		return $ret;
	}

	public function getUserInfo() {
		$ret = '';

		$buf = parse_url($this->uri, PHP_URL_USER);
		if (! $buf || is_null($buf))
			return '';

		$ret .= $buf;

		$buf = parse_url($this->uri, PHP_URL_PASS);
		if (! $buf || is_null($buf))
			return $ret;

		$ret .= ':'.$buf;

		return $ret;
	}

	public function getHost() {
		$buf = parse_url($this->uri, PHP_URL_HOST);
		if (! $buf || is_null($buf))
			return '';

		return strtolower($buf);
	}

	public function getPort() {
		$buf = parse_url($this->uri, PHP_URL_PORT);
		if (! $buf || is_null($buf))
			return NULL;

		switch ($this->getScheme()) {
			case 'http':
				if ($buf == 80)
					return NULL;
				break;
			case 'https':
				if ($buf == 443)
					return NULL;
				break;
		}

		return (int)$buf;
	}

	public function getPath() {
		$buf = parse_url($this->uri, PHP_URL_PATH);
		if (! $buf || is_null($buf))
			return '';

		return $buf;
	}

	public function getQuery() {
		$buf = parse_url($this->uri, PHP_URL_QUERY);
		if (! $buf || is_null($buf))
			return '';

		return $buf;
	}

	public function getFragment() {
		$buf = parse_url($this->uri, PHP_URL_FRAGMENT);
		if (! $buf || is_null($buf))
			return '';

		return $buf;
	}

	public function withScheme($scheme) {
		if (! is_string($scheme))
			throw new \InvalidArgumentException('Invalid scheme '.$scheme);

		if (! in_array(strtolower($scheme), array('http', 'https')))
			throw new \InvalidArgumentException('Unsupported scheme '.$scheme);

		return new Uri($this->buildUri(array('Scheme' => strtolower($scheme))));
	}

	public function withUserInfo($user, $password = NULL) {
		if (! is_string($user))
			throw new \InvalidArgumentException('Invalid user '.$user);

		if (! is_null($password) && ! is_string($password))
			throw new \InvalidArgumentException('Invalid password '.$password);

		return new Uri($this->buildUri(array('UserInfo' => ($user != '' && ! is_null($password))?$user.':'.$password:$user)));
	}

	public function withHost($host) {
		if (! is_string($host))
			throw new \InvalidArgumentException('Invalid host '.$host);

		return new Uri($this->buildUri(array('Host' => strtolower($host))));
	}

	public function withPort($port) {
		if (! is_numeric($port) || $port < 1 || $port > 65535)
			throw new \InvalidArgumentException('Invalid port '.$port);

		return new Uri($this->buildUri(array('Port' => (int)$port)));
	}

	public function withPath($path) {
		if (! is_string($path))
			throw new \InvalidArgumentException('Invalid path '.$path);

		return new Uri($this->buildUri(array('Path' => $path)));
	}

	public function withQuery($query) {
		if (! is_string($query))
			throw new \InvalidArgumentException('Invalid query '.$query);

		return new Uri($this->buildUri(array('Query' => $query)));
	}

	public function withFragment($fragment) {
		if (! is_string($fragment))
			throw new \InvalidArgumentException('Invalid fragment '.$fragment);

		return new Uri($this->buildUri(array('Fragment' => $fragment)));
	}

	public function __toString() {
		return $this->getUri();
	}
}
