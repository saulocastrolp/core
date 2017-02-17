<?php

namespace Simples\Core\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Simples\Core\Kernel\Http;

/**
 * Class Request
 * @package Simples\Core\Http
 */
class Request implements RequestInterface
{
    /**
     * @var boolean
     */
    private $strict;

    /**
     * @var string
     */
    private $uri = '';

    /**
     * @var string
     */
    private $method = '';

    /**
     * @var string
     */
    private $url = '';

    /**
     * @var array
     */
    private $headers = [];

    /**
     * @var array
     */
    private $body = [];

    /**
     * @var string
     */
    private $protocolVersion = '';

    /**
     * @var string
     */
    private $target = '';

    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     *
     * Request constructor.
     * @param boolean $strict
     * @param string $method
     * @param string $url
     * @param string $uri
     * @param array $headers
     */
    public function __construct($strict = false, $method = '', $url = '', $uri = '', $headers = [])
    {
        $this->strict = $strict;
        $this->method = $method;
        $this->url = $url;
        $this->uri = $uri;
        $this->headers = $headers;
    }


    /**
     * @return $this
     */
    public function fromServer()
    {
        $this->getMethodFromServer();

        $this->getHeadersFromServer();

        $this->getUrlFromServer();

        $this->getDataFromServer();

        return $this;
    }

    /**
     * @return $this
     */
    private function getMethodFromServer()
    {
        $method = server('REQUEST_METHOD');
        $method = iif(get('_method'), $method);
        $method = iif(post('_method'), $method);
        $this->method = strtolower($method);

        return $this;
    }

    /**
     * @return $this
     * @SuppressWarnings("Superglobals")
     */
    private function getHeadersFromServer()
    {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $this->headers[headerify(substr($name, 5))] = $value;
            }
        }
        return $this;
    }

    /**
     * @return $this
     */
    private function getUrlFromServer()
    {
        $self = str_replace('index.php/', '', server('PHP_SELF'));
        $uri = server('REQUEST_URI') ? explode('?', server('REQUEST_URI'))[0] : '';
        $start = '';

        if ($self !== $uri) {
            $peaces = explode('/', $self);
            array_pop($peaces);

            $start = implode('/', $peaces);
            $search = '/' . preg_quote($start, '/') . '/';
            $uri = preg_replace($search, '', $uri, 1);
        }
        $this->uri = substr($uri, -1) !== '/' ? $uri . '/' : $uri;

        $this->url = server('HTTP_HOST') ? server('HTTP_HOST') . $start : $this->url;

        return $this;
    }

    /**
     * @return $this
     * @SuppressWarnings("Superglobals")
     */
    private function getDataFromServer()
    {
        $_PAYLOAD = (array)json_decode(file_get_contents("php://input"));
        if (!$_PAYLOAD) {
            $_PAYLOAD = [];
        }

        $this->set('GET', $_GET);
        $this->set('POST', array_merge($_POST, $_PAYLOAD));

        if ($this->strict) {
            $_GET = [];
            $_POST = [];
        }

        return $this;
    }

    /**
     * @param $source
     * @param $data
     */
    private function set($source, $data)
    {
        if (isset($data['_method'])) {
            unset($data['_method']);
        }
        $this->body[$source] = $data;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return array
     */
    public function getInputs()
    {
        $inputs = [];
        foreach ($this->body as $datum) {
            foreach ($datum as $key => $value) {
                $inputs[$key] = new Input($value);
            }
        }
        return $inputs;
    }

    /**
     * @param $name
     * @return null|Input
     */
    public function getInput($name)
    {
        $value = $this->get($name);
        if (is_null($value)) {
            $value = $this->post($name);
        }
        if (is_null($value)) {
            return null;
        }
        return new Input($value);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function get($name)
    {
        return off($this->body['GET'], $name);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function post($name)
    {
        return off($this->body['POST'], $name);
    }

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     * @return static
     */
    public function withProtocolVersion($version)
    {
        $copy = clone $this;
        $copy->protocolVersion = $version;
        return $copy;
    }

    /**
     * Retrieves all message header values.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * While header names are not case-sensitive, getHeaders() will preserve the
     * exact case in which headers were originally specified.
     *
     * @return string[][] Returns an associative array of the message's headers. Each
     *     key MUST be a header name, and each value MUST be an array of strings
     *     for that header.
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header field name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($name)
    {
        return array_key_exists($name, $this->headers);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name Case-insensitive header field name.
     * @return string An array of string values as provided for the given
     *    header. If the header does not appear in the message, this method MUST
     *    return an empty array.
     */
    public function getHeader($name)
    {
        return $this->hasHeader($name) ? $this->headers[$name] : null;
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name)
    {
        return $this->getHeader($name);
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * @return static
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withHeader($name, $value)
    {
        if (!$this->hasHeader($name)) {
            throw new \InvalidArgumentException("Invalid header name `{$name}`");
        }
        $copy = clone $this;
        $copy->headers[$name] = $value;
        return $copy;
    }

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string $name Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     * @return static
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withAddedHeader($name, $value)
    {
        return $this->withHeader($name, $value);
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $name Case-insensitive header field name to remove.
     * @return static
     */
    public function withoutHeader($name)
    {
        $copy = clone $this;
        unset($copy->headers[$name]);
        return $copy;
    }

    /**
     * Gets the body of the message.
     *
     * @return array StreamInterface Returns the body as a stream.
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body Body.
     * @return static
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function withBody(StreamInterface $body)
    {
        $copy = clone $this;
        $copy->body = $body;
        return $copy;
    }

    /**
     * Retrieves the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget()
    {
        return $this->target;
    }

    /**
     * Return an instance with the specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request target.
     *
     * @link http://tools.ietf.org/html/rfc7230#section-5.3 (for the various
     *     request-target forms allowed in request messages)
     * @param mixed $requestTarget
     * @return static
     */
    public function withRequestTarget($requestTarget)
    {
        $copy = clone $this;
        $copy->target = $requestTarget;
        return $copy;
    }

    /**
     * Return an instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request method.
     *
     * @param string $method Case-sensitive method.
     * @return static
     * @throws \InvalidArgumentException for invalid HTTP methods.
     */
    public function withMethod($method)
    {
        if (!in_array($method, Http::METHODS)) {

        }
        $copy = clone $this;
        $copy->method = $method;
        return $copy;
    }

    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     *
     * Returns an instance with the provided URI.
     *
     * This method MUST update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header MUST be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, this method interacts with the Host header in the following ways:
     *
     * - If the Host header is missing or empty, and the new URI contains
     *   a host component, this method MUST update the Host header in the returned
     *   request.
     * - If the Host header is missing or empty, and the new URI does not contain a
     *   host component, this method MUST NOT update the Host header in the returned
     *   request.
     * - If a Host header is present and non-empty, this method MUST NOT update
     *   the Host header in the returned request.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @param UriInterface $uri New request URI to use.
     * @param bool $preserveHost Preserve the original state of the Host header.
     * @return static
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $copy = clone $this;
        if ($preserveHost) {
            // TODO: management of uri
        }
        $copy->uri = $uri;
        return $copy;
    }
}
