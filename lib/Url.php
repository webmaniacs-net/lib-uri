<?php
namespace wmlib\uri;

use wmlib\uri\Exception\SyntaxException;
use Psr\Http\Message\UriInterface;

/**
 * Represents a Uniform Resource Identifier (URI) reference.
 *
 * Aside from some minor deviations noted below, an instance of this class represents a URI reference
 * as defined by RFC 2396: Uniform Resource Identifiers (URI): Generic Syntax, amended by RFC 2732: Format for Literal IPv6 Addresses in URLs.
 * This class provides constructor for creating URI instances from their string forms, methods for accessing the various components of an instance,
 * and methods for normalizing and resolving URI instances.
 * Instances of this class are immutable.
 *
 */
class Url extends Uri implements UriInterface
{
    protected $userInfo;

    protected $host;

    protected $port;

    /**
     * Set authority component of this URI.
     *
     * @throws SyntaxException
     * @param string $authority The decoded authority component of this URI, or null if the authority is undefined.
     * @return Url
     */
    public function setAuthority($authority)
    {
        $aparts = [];
        if (preg_match('/^(([^\@]+)\@)?(.*?)(\:(.+))?$/S', $authority, $aparts)) {
            parent::setAuthority($authority);

            if (isset($aparts[2]) && $aparts[2]) {
                $this->userInfo = $aparts[2];
            }
            if (isset($aparts[3]) && $aparts[3]) {
                $this->host = $aparts[3];
            }
            if (isset($aparts[5]) && $aparts[5]) {
                $this->port = (int)$aparts[5];
            }
        } else {
            throw new SyntaxException("Hierarchical URL authority part syntax error");
        }

        return $this;
    }

    /**
     * Returns the host component of this URI.
     *
     * The host component of a URI, if defined, will have one of the following forms:
     *  - A domain name consisting of one or more labels separated by period characters ('.'),
     *  optionally followed by a period character. Each label consists of alphanum characters as well as hyphen characters ('-'),
     *  though hyphens never occur as the first or last characters in a label.
     *  The rightmost label of a domain name consisting of two or more labels, begins with an alpha character.
     *
     *  - A dotted-quad IPv4 address of the form digit+.digit+.digit+.digit+, where no digit sequence is longer than three characters and no sequence has a value larger than 255
     *
     *  - An IPv6 address enclosed in square brackets ('[' and ']') and consisting of hexadecimal digits, colon characters (':'), and possibly an embedded IPv4 address.
     *  The full syntax of IPv6 addresses is specified in RFC 2373: IPv6 Addressing Architecture.
     *
     * The host component of a URI cannot contain escaped octets, hence this method does not perform any decoding.
     *
     * @return string The host component of this URI, or null if the host is undefined
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Set host
     *
     * @param string $host
     * @return Uri Fluent API support
     */
    public function setHost($host)
    {
        $this->host = $host;

        $this->hash = null;

        return $this;
    }

    /**
     * Returns the port number of this URI.
     *
     * @return string The port component of this URI, or null if the port is undefined
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Set port
     *
     * @param string $port
     * @return Uri Fluent API support
     */
    public function setPort($port)
    {
        $this->port = $port;

        $this->hash = null;

        return $this;
    }

    /**
     * Returns the decoded user-information component of this URI.
     *
     * @return string The decoded user-information component of this URI, or null if the user information is undefined
     */
    public function getUserInfo()
    {
        return urldecode($this->userInfo);
    }

    /**
     * Returns the authority parts string of url
     *
     * @see buildStr()
     * @return string
     */
    protected function buildAuthorityStr()
    {
        if ($this->host != null) {
            $authority = '//';
            if ($this->userInfo != null) {
                $authority .= $this->userInfo . '@';
            }
            $flag = (strpos($this->host, ':') !== false) && !(substr($this->host, 0,
                        1) == '[') && !(substr($this->host, -1) == ']');
            if ($flag) {
                $authority .= '[';
            }
            $authority .= $this->host;
            if ($flag) {
                $authority .= ']';
            }
            if ($this->port != null) {
                $authority .= ':' . $this->port;
            }
            return $authority;
        } else {
            return parent::buildAuthorityStr();
        }
    }

    /**
     * Push value to query encoded
     *
     * @param string $name
     * @param string $value
     * @return Uri
     */
    public function pushQueryValue($name, $value)
    {
        parse_str($this->query, $data);
        $data[$name] = $value;

        $this->query = http_build_query($data);

        $this->hash = null;

        return $this;
    }


    /**
     * Push values to query encoded
     *
     * @param array $values
     * @return Uri
     */
    public function pushQueryValues(array $values)
    {
        if ($this->query !== null) {
            parse_str($this->query, $data);
        } else {
            $data = [];
        }

        $this->query = http_build_query($values + $data);

        $this->hash = null;

        return $this;
    }

    /**
     * Create a new instance with the specified scheme.
     *
     * This method MUST retain the state of the current instance, and return
     * a new instance that contains the specified scheme. If the scheme
     * provided includes the "://" delimiter, it MUST be removed.
     *
     * Implementations SHOULD restrict values to "http", "https", or an empty
     * string but MAY accommodate other schemes if required.
     *
     * An empty scheme is equivalent to removing the scheme.
     *
     * @param string $scheme The scheme to use with the new instance.
     * @return self A new instance with the specified scheme.
     * @throws \InvalidArgumentException for invalid or unsupported schemes.
     */
    public function withScheme($scheme)
    {
        $url = clone $this;
        $url->setScheme($scheme);

        return $url;
    }

    /**
     * Create a new instance with the specified user information.
     *
     * This method MUST retain the state of the current instance, and return
     * a new instance that contains the specified user information.
     *
     * Password is optional, but the user information MUST include the
     * user; an empty string for the user is equivalent to removing user
     * information.
     *
     * @param string $user User name to use for authority.
     * @param null|string $password Password associated with $user.
     * @return self A new instance with the specified user information.
     */
    public function withUserInfo($user, $password = null)
    {
        $url = clone $this;
        $url->userInfo = $user . ($password ? (':' . $password) : '');

        return $url;
    }

    /**
     * Create a new instance with the specified host.
     *
     * This method MUST retain the state of the current instance, and return
     * a new instance that contains the specified host.
     *
     * An empty host value is equivalent to removing the host.
     *
     * @param string $host Hostname to use with the new instance.
     * @return self A new instance with the specified host.
     * @throws \InvalidArgumentException for invalid hostnames.
     */
    public function withHost($host)
    {
        $url = clone $this;
        $url->setHost($host);

        return $url;
    }

    /**
     * Create a new instance with the specified port.
     *
     * This method MUST retain the state of the current instance, and return
     * a new instance that contains the specified port.
     *
     * Implementations MUST raise an exception for ports outside the
     * established TCP and UDP port ranges.
     *
     * A null value provided for the port is equivalent to removing the port
     * information.
     *
     * @param null|int $port Port to use with the new instance; a null value
     *     removes the port information.
     * @return self A new instance with the specified port.
     * @throws \InvalidArgumentException for invalid ports.
     */
    public function withPort($port)
    {
        $url = clone $this;
        $url->withPort($port);

        return $url;
    }

    /**
     * Create a new instance with the specified path.
     *
     * This method MUST retain the state of the current instance, and return
     * a new instance that contains the specified path.
     *
     * The path MUST be prefixed with "/"; if not, the implementation MAY
     * provide the prefix itself.
     *
     * An empty path value is equivalent to removing the path.
     *
     * @param string $path The path to use with the new instance.
     * @return self A new instance with the specified path.
     * @throws \InvalidArgumentException for invalid paths.
     */
    public function withPath($path)
    {
        $url = clone $this;
        $url->path = $path;

        return $url;
    }

    /**
     * Create a new instance with the specified query string.
     *
     * This method MUST retain the state of the current instance, and return
     * a new instance that contains the specified query string.
     *
     * If the query string is prefixed by "?", that character MUST be removed.
     * Additionally, the query string SHOULD be parseable by parse_str() in
     * order to be valid.
     *
     * An empty query string value is equivalent to removing the query string.
     *
     * @param string $query The query string to use with the new instance.
     * @return self A new instance with the specified query string.
     * @throws \InvalidArgumentException for invalid query strings.
     */
    public function withQuery($query)
    {
        $url = clone $this;
        $url->setQuery($query);

        return $url;
    }

    /**
     * Create a new instance with the specified URI fragment.
     *
     * This method MUST retain the state of the current instance, and return
     * a new instance that contains the specified URI fragment.
     *
     * If the fragment is prefixed by "#", that character MUST be removed.
     *
     * An empty fragment value is equivalent to removing the fragment.
     *
     * @param string $fragment The URI fragment to use with the new instance.
     * @return self A new instance with the specified URI fragment.
     */
    public function withFragment($fragment)
    {
        $url = clone $this;
        $url->fragment = $fragment;

        return $url;
    }

    /**
     * Indicate whether the URI is in origin-form.
     *
     * Origin-form is a URI that includes only the path, and optionally the
     * query string.
     *
     * @link http://tools.ietf.org/html/rfc7230#section-5.3.1
     * @return bool
     */
    public function isOrigin()
    {
        return (!$this->scheme && $this->getAuthority());
    }

    /**
     * Indicate whether the instance represents an authority-form request
     * target.
     *
     * An authority-form request-target contains ONLY the authority information.
     *
     * @see getAuthority()
     * @link http://tools.ietf.org/html/rfc7230#section-5.3.3
     * @return bool
     */
    public function isAuthority()
    {
        return (!$this->scheme && !$this->query && !$this->fragment && $this->getAuthority());
    }

    /**
     * Indicate whether the instance represents an asterisk-form request
     * target.
     *
     * An asterisk-form request-target will contain ONLY the string "*".
     *
     * @link http://tools.ietf.org/html/rfc7230#section-5.3.4
     * @return bool
     */
    public function isAsterisk()
    {
        return ($this->__toString() === '*');
    }
}