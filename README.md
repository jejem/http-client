# http-client
PHP PSR-7 compatible HTTP client (using cURL)

[![Latest Stable Version](https://poser.pugx.org/phyrexia/http/v/stable)](https://packagist.org/packages/phyrexia/http)
[![License](https://poser.pugx.org/phyrexia/http/license)](https://packagist.org/packages/phyrexia/http)

## Requirements

- PHP >= 5.3
- PHP extension curl
- Composer [psr/http-message](https://packagist.org/packages/psr/http-message) ^1.0
- Composer [guzzlehttp/psr7](https://packagist.org/packages/guzzlehttp/psr7) ^1.0

## Installation

Install directly via [Composer](https://getcomposer.org):
```bash
$ composer require phyrexia/http
```

## Basic Usage

```php
<?php
require 'vendor/autoload.php';

use Phyrexia\Http\Client as HttpClient;

//HTTP GET to www.google.fr
$response = HttpClient::get('http://www.google.fr');

//And now with a query string
$response = HttpClient::get('http://www.google.fr', 'a=1&b=c');

//Query string in array format
$response = HttpClient::get('http://www.google.fr', array('a' => 1, 'b' => 'c'));

//An HTTP POST with some data
$response = HttpClient::post('http://www.google.fr', array('user' => 'test', 'submit' => 1));

//You can also build an HttpClient object, and provide cURL options (::get, ::post and ::head support cURL options too)
$client = new HttpClient('http://www.google.fr', 'GET', array(CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 5));
$response = $client->send();

//The response is a Response object, if you just want the body, you can cast it as a string
$body = (string)HttpClient::get('http://www.google.fr');
```
