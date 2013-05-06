<?php

/**
 * Class for sockets
 *
 * Example:
 *
 *     try {
 *         $socket = rexseo_socket::factory('www.example.com');
 *         $socket->setPath('/path/index.php?param=1');
 *         $response = $socket->doGet();
 *         if($response->isOk()) {
 *             $body = $response->getBody();
 *         }
 *     } catch(rexseo_socket_exception $e) {
 *         // error message: $e->getMessage()
 *     }
 *
 *
 * @author gharlan
 */
class rexseo_socket
{
    protected
        $host,
        $port,
        $ssl,
        $path = '/',
        $timeout = 15,
        $headers = array(),
        $stream;

    /**
     * Constructor
     *
     * @param string  $host Host name
     * @param integer $port Port number
     * @param boolean $ssl  SSL flag
     */
    protected function __construct($host, $port = 80, $ssl = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->ssl = $ssl;

        $this->addHeader('Host', $this->host);
        $this->addHeader('User-Agent', 'redaxo4-socket/1.0');
        $this->addHeader('Connection', 'Close');
    }

    /**
     * Factory method
     *
     * @param string  $host Host name
     * @param integer $port Port number
     * @param boolean $ssl  SSL flag
     * @return self Socket instance
     *
     * @see rexseo_socket::factoryUrl()
     */
    public static function factory($host, $port = 80, $ssl = false)
    {
        return new static($host, $port, $ssl);
    }

    /**
     * Creates a socket by a full URL
     *
     * @param string $url URL
     * @throws rexseo_socket_exception
     * @return self Socket instance
     *
     * @see rexseo_socket::factory()
     */
    public static function factoryUrl($url)
    {
        $parts = self::parseUrl($url);

        return static::factory($parts['host'], $parts['port'], $parts['ssl'])->setPath($parts['path']);
    }

    /**
     * Sets the path
     *
     * @param string $path
     * @return self Current socket
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Adds a header to the current request
     *
     * @param string $key
     * @param string $value
     * @return self Current socket
     */
    public function addHeader($key, $value)
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Adds the basic authorization header to the current request
     *
     * @param string $user
     * @param string $password
     * @return self Current socket
     */
    public function addBasicAuthorization($user, $password)
    {
        $this->addHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $password));

        return $this;
    }

    /**
     * Sets the timeout for the connection
     *
     * @param int $timeout Timeout
     * @return self Current socket
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Makes a GET request
     *
     * @return rexseo_socket_response Response
     * @throws rexseo_socket_exception
     */
    public function doGet()
    {
        return $this->doRequest('GET');
    }

    /**
     * Makes a POST request
     *
     * @param string|array|callable $data  Body data as string or array (POST parameters) or a callback for writing the body
     * @param array                 $files Files array, e.g. `array('myfile' => array('path' => $path, 'type' => 'image/png'))`
     * @return rexseo_socket_response Response
     * @throws rexseo_socket_exception
     */
    public function doPost($data = '', array $files = array())
    {
        if (is_array($data) && !empty($files)) {
            $data = function ($stream) use ($data, $files) {
                $boundary = '----------6n2Yd9bk2liD6piRHb5xF6';
                $eol = "\r\n";
                fwrite($stream, 'Content-Type: multipart/form-data; boundary=' . $boundary . $eol);
                $dataFormat = '--' . $boundary . $eol . 'Content-Disposition: form-data; name="%s"' . $eol . $eol;
                $fileFormat = '--' . $boundary . $eol . 'Content-Disposition: form-data; name="%s"; filename="%s"' . $eol . 'Content-Type: %s' . $eol . $eol;
                $end = '--' . $boundary . '--' . $eol;
                $length = 0;
                $temp = explode('&', http_build_query($data, null, '&'));
                $data = array();
                $partLength = strlen(sprintf($dataFormat, '') . $eol);
                foreach ($temp as $t) {
                    list($key, $value) = array_map('urldecode', explode('=', $t, 2));
                    $data[$key] = $value;
                    $length += $partLength + strlen($key) + strlen($value);
                }
                $partLength = strlen(sprintf($fileFormat, '', '', '') . $eol);
                foreach ($files as $key => $file) {
                    $length += $partLength + strlen($key) + strlen(basename($file['path'])) + strlen($file['type']) + filesize($file['path']);
                }
                $length += strlen($end);
                fwrite($stream, 'Content-Length: ' . $length . $eol . $eol);
                foreach ($data as $key => $value) {
                    fwrite($stream, sprintf($dataFormat, $key) . $value . $eol);
                }
                foreach ($files as $key => $file) {
                    fwrite($stream, sprintf($fileFormat, $key, basename($file['path']), $file['type']));
                    $file = fopen($file['path'], 'rb');
                    while (!feof($file)) {
                        fwrite($stream, fread($file, 1024));
                    }
                    fclose($file);
                    fwrite($stream, $eol);
                }
                fwrite($stream, $end);
            };
        } elseif (!is_callable($data)) {
            if (is_array($data))
                $data = http_build_query($data, null, '&');
            $this->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        }
        return $this->doRequest('POST', $data);
    }

    /**
     * Makes a DELETE request
     *
     * @return rexseo_socket_response Response
     * @throws rexseo_socket_exception
     */
    public function doDelete()
    {
        return $this->doRequest('DELETE');
    }

    /**
     * Makes a request
     *
     * @param string          $method HTTP method, e.g. "GET"
     * @param string|callable $data   Body data as string or a callback for writing the body
     * @return rexseo_socket_response Response
     * @throws InvalidArgumentException
     */
    public function doRequest($method, $data = '')
    {
        if (!is_string($data) && !is_callable($data)) {
            throw new InvalidArgumentException(sprintf('Expecting $data to be a string or a callable, but %s given!', gettype($data)));
        }

        $this->openConnection();
        return $this->writeRequest($method, $this->path, $this->headers, $data);
    }

    /**
     * Opens the socket connection
     *
     * @throws rexseo_socket_exception
     */
    protected function openConnection()
    {
        $host = ($this->ssl ? 'ssl://' : '') . $this->host;
        if (!($this->stream = @fsockopen($host, $this->port, $errno, $errstr))) {
            throw new rexseo_socket_exception($errstr . ' (' . $errno . ')');
        }

        stream_set_timeout($this->stream, $this->timeout);
    }

    /**
     * Writes a request to the opened connection
     *
     * @param string          $method  HTTP method, e.g. "GET"
     * @param string          $path    Path
     * @param array           $headers Headers
     * @param string|callable $data    Body data as string or a callback for writing the body
     * @throws rexseo_socket_exception
     * @return rexseo_socket_response Response
     */
    protected function writeRequest($method, $path, array $headers = array(), $data = '')
    {
        $eol = "\r\n";
        $headerStrings = array();
        $headerStrings[] = strtoupper($method) . ' ' . $path . ' HTTP/1.1';
        foreach ($headers as $key => $value) {
            $headerStrings[] = $key . ': ' . $value;
        }
        foreach ($headerStrings as $header) {
            fwrite($this->stream, str_replace(array("\r", "\n"), '', $header) . $eol);
        }
        if (!is_callable($data)) {
            fwrite($this->stream, 'Content-Length: ' . strlen($data) . $eol);
            fwrite($this->stream, $eol . $data);
        } else {
            call_user_func($data, $this->stream);
        }

        $meta = stream_get_meta_data($this->stream);
        if (isset($meta['timed_out']) && $meta['timed_out']) {
            throw new rexseo_socket_exception('Timeout!');
        }

        return new rexseo_socket_response($this->stream);
    }

    /**
     * Parses a full URL and returns an array with the keys "host", "port", "ssl" and "path"
     *
     * @param string $url Full URL
     * @return array URL parts
     * @throws rexseo_socket_exception
     */
    protected static function parseUrl($url)
    {
        $parts = parse_url($url);
        if ($parts !== false && !isset($parts['host']) && strpos($url, 'http') !== 0) {
            $parts = parse_url('http://' . $url);
        }
        if ($parts === false || !isset($parts['host'])) {
            throw new rexseo_socket_exception('It isn\'t possible to parse the URL "' . $url . '"!');
        }

        $port = 80;
        $ssl = false;
        if (isset($parts['scheme'])) {
            $supportedProtocols = array('http', 'https');
            if (!in_array($parts['scheme'], $supportedProtocols)) {
                throw new rexseo_socket_exception('Unsupported protocol "' . $parts['scheme'] . '". Supported protocols are ' . implode(', ', $supportedProtocols) . '.');
            }
            if ($parts['scheme'] == 'https') {
                $ssl = true;
                $port = 443;
            }
        }
        $port = isset($parts['port']) ? (int) $parts['port'] : $port;

        $path = (isset($parts['path'])   ? $parts['path']          : '/')
            . (isset($parts['query'])    ? '?' . $parts['query']    : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

        return array(
            'host' => $parts['host'],
            'port' => $port,
            'ssl' => $ssl,
            'path' => $path
        );
    }
}

/**
 * Socket exception
 *
 * @see rexseo_socket
 */
class rexseo_socket_exception extends Exception {}

















/**
 * Class for rexseo_socket responses
 *
 * @author gharlan
 */
class rexseo_socket_response
{
    private $stream;
    private $chunked = false;
    private $chunkPos = 0;
    private $chunkLength = 0;
    private $statusCode;
    private $statusMessage;
    private $header = '';
    private $headers = array();
    private $body;

    /**
     * Constructor
     *
     * @param resource $stream Socket stream
     * @throws InvalidArgumentException
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException(sprintf('Expecting $stream to be a resource, but %s given!', gettype($stream)));
        }

        $this->stream = $stream;

        while (!feof($this->stream) && strpos($this->header, "\r\n\r\n") === false) {
            $this->header .= fgets($this->stream);
        }
        $this->header = rtrim($this->header);
        if (preg_match('@^HTTP/1\.\d ([0-9]+) (\V+)@', $this->header, $matches)) {
            $this->statusCode = intval($matches[1]);
            $this->statusMessage = $matches[2];
        }
        $this->chunked = stripos($this->header, 'transfer-encoding: chunked') !== false;
    }

    /**
     * Returns the HTTP status code, e.g. 200
     *
     * @return integer
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Returns the HTTP status message, e.g. "OK"
     *
     * @return string
     */
    public function getStatusMessage()
    {
        return $this->statusMessage;
    }

    /**
     * Returns wether the status is "200 OK"
     *
     * @return boolean
     */
    public function isOk()
    {
        return $this->statusCode == 200;
    }

    /**
     * Returns wether the status class is "Informational"
     *
     * @return boolean
     */
    public function isInformational()
    {
        return $this->statusCode >= 100 && $this->statusCode < 200;
    }

    /**
     * Returns wether the status class is "Success"
     *
     * @return boolean
     */
    public function isSuccessful()
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Returns wether the status class is "Redirection"
     *
     * @return boolean
     */
    public function isRedirection()
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Returns wether the status class is "Client Error"
     *
     * @return boolean
     */
    public function isClientError()
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Returns wether the status class is "Server Error"
     *
     * @return boolean
     */
    public function isServerError()
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Returns wether the status is invalid
     *
     * @return boolean
     */
    public function isInvalid()
    {
        return $this->statusCode < 100 || $this->statusCode >= 600;
    }

    /**
     * Returns the header for the given key, or the entire header if no key is given
     *
     * @param string $key     Header key
     * @param string $default Default value (is returned if the header is not set)
     * @return string
     */
    public function getHeader($key = null, $default = null)
    {
        if ($key === null) {
            return $this->header;
        }
        $key = strtolower($key);
        if (isset($this->headers[$key])) {
            return $this->headers[$key];
        }
        if (preg_match('@^' . preg_quote($key, '@') . ': (\V*)@im', $this->header, $matches)) {
            return $this->headers[$key] = $matches[1];
        }
        return $this->headers[$key] = $default;
    }

    /**
     * Returns up to `$length` bytes from the body, or `false` if the end is reached
     *
     * @param integer $length Max number of bytes
     * @return boolean|string
     */
    public function getBufferedBody($length = 1024)
    {
        if (feof($this->stream)) {
            return false;
        }
        if ($this->chunked) {
            if ($this->chunkPos == 0) {
                $this->chunkLength = hexdec(fgets($this->stream));
                if ($this->chunkLength == 0) {
                    return false;
                }
            }
            $pos = ftell($this->stream);
            $buf = fread($this->stream, min($length, $this->chunkLength - $this->chunkPos));
            $this->chunkPos += ftell($this->stream) - $pos;
            if ($this->chunkPos >= $this->chunkLength) {
                fgets($this->stream);
                $this->chunkPos = 0;
                $this->chunkLength = 0;
            }
            return $buf;
        } else {
            return fread($this->stream, $length);
        }
    }

    /**
     * Returns the entire body
     *
     * @return string
     */
    public function getBody()
    {
        if ($this->body === null) {
            while (($buf = $this->getBufferedBody()) !== false) {
                $this->body .= $buf;
            }
        }
        return $this->body;
    }

    /**
     * Writes the body to the given resource
     *
     * @param string|resource $resource File path or file pointer
     * @return boolean `true` on success, `false` on failure
     */
    public function writeBodyTo($resource)
    {
        $close = false;
        if (is_string($resource)) {
            $resource = fopen($resource, 'wb');
            $close = true;
        }
        if (!is_resource($resource)) {
            return false;
        }
        $success = true;
        while ($success && ($buf = $this->getBufferedBody()) !== false) {
            $success = (boolean) fwrite($resource, $buf);
        }
        if ($close) {
            fclose($resource);
        }
        return $success;
    }
}





/**
 * Class for sockets over a proxy
 *
 * @author gharlan
 */
class rexseo_socket_proxy extends rexseo_socket
{
    protected
        $destinationHost,
        $destinationPort,
        $destinationSsl;

    /**
     * Sets the destination
     *
     * @param string  $host Host name
     * @param integer $port Port number
     * @param boolean $ssl  SSL flag
     * @return self Current socket
     */
    public function setDestination($host, $port = 80, $ssl = false)
    {
        $this->destinationHost = $host;
        $this->destinationPort = $port;
        $this->destinationSsl = $ssl;

        $this->addHeader('Host', $host . ':' . $port);

        return $this;
    }

    /**
     * Sets the destination by a full URL
     *
     * @param string $url Full URL
     * @return self Current socket
     */
    public function setDestinationUrl($url)
    {
        $parts = self::parseUrl($url);

        return $this->setDestination($parts['host'], $parts['port'], $parts['ssl'])->setPath($parts['path']);
    }

    /**
     * {@inheritDoc}
     */
    protected function openConnection()
    {
        parent::openConnection();

        if ($this->destinationSsl) {
            $headers = array(
                'Host' => $this->destinationHost . ':' . $this->destinationPort,
                'Proxy-Connection' => 'Keep-Alive'
            );
            $response = $this->writeRequest('CONNECT', $this->destinationHost . ':' . $this->destinationPort, $headers);
            if (!$response->isOk()) {
                throw new rexseo_socket_exception(sprintf('Couldn\'t connect to proxy server, server responds with "%s %s"'), $response->getStatusCode(), $response->getStatusMessage());
            }
            stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_SSLv3_CLIENT);
        } else {
            unset($this->headers['Connection']);
            $this->addHeader('Proxy-Connection', 'Close');
            $this->path = 'http://' . $this->destinationHost . ':' . $this->destinationPort . $this->path;
        }
    }
}
