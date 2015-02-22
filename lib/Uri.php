<?php
namespace wmlib\uri;

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
class Uri implements UriInterface
{

    protected $scheme;

    protected $schemeSpecificPart;

    /**
     * @var string
     */
    protected $authority;

    protected $path;

    protected $query;

    protected $fragment;

    protected $token;

    protected $hash;

    protected $userInfo;

    protected $host;

    protected $port;

    /**
     * Public constructor
     *
     * @param string $uri
     */
    public function __construct($uri)
    {
        $this->token = $uri;
        if ($uri !== '') {
            $this->init();
        }
    }

    /**
     * Constructs a URI by parsing the given string.
     *
     * This constructor parses the given string exactly as specified by the grammar in RFC 2396, Appendix A, except for the following deviations:
     *
     *  - An empty authority component is permitted as long as it is followed by a non-empty path, a query component, or a fragment component.
     * This allows the parsing of URIs such as "file:///foo/bar", which seems to be the intent of RFC 2396 although the grammar does not permit it.
     * If the authority component is empty then the user-information, host, and port components are undefined.
     *
     *  - Empty relative paths are permitted; this seems to be the intent of RFC 2396 although the grammar does not permit it.
     * The primary consequence of this deviation is that a standalone fragment such as "#foo" parses as a relative URI with an empty path and the given fragment,
     * and can be usefully resolved against a base URI.
     *
     *  - IPv4 addresses in host components are parsed rigorously, as specified by RFC 2732:
     * Each element of a dotted-quad address must contain no more than three decimal digits. Each element is further constrained to have a value no greater than 255.
     *
     *  - Hostnames in host components that comprise only a single domain label are permitted to start with an alphanum character.
     * This seems to be the intent of RFC 2396 section 3.2.2 although the grammar does not permit it.
     * The consequence of this deviation is that the authority component of a hierarchical URI such as s://123, will parse as a server-based authority.
     *
     *  - IPv6 addresses are permitted for the host component. An IPv6 address must be enclosed in square brackets ('[' and ']') as specified by RFC 2732.
     * The IPv6 address itself must parse according to RFC 2373. IPv6 addresses are further constrained to describe no more than sixteen bytes of address information,
     * a constraint implicit in RFC 2373 but not expressible in the grammar.
     *
     *  - Characters in the other category are permitted wherever RFC 2396 permits escaped octets, that is, in the user-information, path, query, and fragment components,
     * as well as in the authority component if the authority is registry-based. This allows URIs to contain Unicode characters beyond those in the US-ASCII character set.
     *
     * @throws \DomainException If the given string violates RFC 2396, as augmented by the above deviations.
     */
    protected function init()
    {
        $uri = $this->token;
        // check scheme
        if (($pos = strpos($uri, ':')) !== false) {
            $this->scheme = substr($uri, 0, $pos);
            $rest = substr($uri, $pos + 1);
        } else {
            $rest = $uri;
        }
        // check fragment
        if (($pos = strrpos($rest, '#')) !== false) {
            $this->fragment = substr($rest, $pos + 1);
            $rest = substr($rest, 0, $pos);
        }
        $this->schemeSpecificPart = $rest;

        // A hierarchical URI
        if (($this->isAbsolute() && $this->schemeSpecificPart{0} == '/') || !$this->isAbsolute()) {
            $parts = array();
            if (preg_match('/^(\/\/([^\/]*))?(\/?[^\?]+)?(\?(.*?))?$/S', $this->schemeSpecificPart, $parts)) {
                if (isset($parts[2])) {
                    $this->setAuthority($parts[2]);
                }
                if (isset($parts[3]) && $parts[3]) {
                    $this->path = $parts[3];
                }
                if (isset($parts[5]) && $parts[5]) {
                    $this->query = $parts[5];
                }
            } else {
                throw new \DomainException("Hierarchical URI scheme-specific part syntax error");
            }
        }

        $this->hash = null;
    }

    /**
     * Resolves the given URI against this URI.
     *
     * If the given URI is already absolute, or if this URI is opaque, then the given URI is returned.
     *
     * If the given URI's fragment component is defined, its path component is empty, and its scheme,
     * authority, and query components are undefined, then a URI with the given fragment but with all
     * other components equal to those of this URI is returned. This allows a URI representing
     * a standalone fragment reference, such as "#foo", to be usefully resolved against a base URI.
     *
     * Otherwise this method constructs a new hierarchical URI in a manner consistent with RFC 2396, section 5.2; that is:
     * - A new URI is constructed with this URI's scheme and the given URI's query and fragment components.
     *
     * - If the given URI has an authority component then the new URI's authority and path are taken from the given URI.
     *
     * - Otherwise the new URI's authority component is copied from this URI, and its path is computed as follows:
     *
     *   - If the given URI's path is absolute then the new URI's path is taken from the given URI.
     *
     *   - Otherwise the given URI's path is relative, and so the new URI's path is computed by resolving
     *     the path of the given URI against the path of this URI. This is done by concatenating all but
     *     the last segment of this URI's path, if any, with the given URI's path and then normalizing
     *     the result as if by invoking the normalize method.
     *
     *   - The result of this method is absolute if, and only if, either this URI is absolute or the given URI is absolute
     *
     * @param Uri $uri The URI to be resolved against this URI
     * @return Uri The resulting URI
     */
    public function resolve(Uri $uri)
    {
        static $resolve = array();

        return (isset($resolve[$hash = $this->hashCode() . $uri->hashCode()])) ? $resolve[$hash] : ($resolve[$hash] = $this->createResolve($uri));
    }

    private function createResolve(Uri $uri)
    {
        if ($uri->isAbsolute() || $this->isOpaque()) {
            return clone $uri;
        } elseif ($uri->scheme == null && $uri->authority == null && $uri->path == '' && $uri->fragment && !$uri->query) {
            $uri2 = clone $this;
            /** @var $uri2 Uri */
            $uri2->fragment = $uri->fragment;
            return $uri2;
        } else {
            $uri2 = clone $uri;
            /** @var $uri2 Uri */
            $uri2->scheme = $this->scheme;

            if (!$uri->authority) {
                $uri2->setAuthority($this->authority);

                if ($this->path && substr($uri->path, 0, 1) == '/') {
                    $uri2->path = $uri->path;
                } else {
                    $pos = strrpos($this->path, '/');
                    $s2 = ($pos !== false) ? substr($this->path, 0, $pos + 1) : '';
                    if (strlen($uri->path) != 0) {
                        $s2 .= $uri->path;
                    }
                    $uri2->path = self::normalizePath($s2);
                }
            }

            return $uri2;
        }
    }

    /**
     * Normalizes this URI's path.
     *
     * If this URI is opaque, or if its path is already in normal form, then this URI is returned.
     * Otherwise a new URI is constructed that is identical to this URI except that its path is computed
     * by normalizing this URI's path in a manner consistent with RFC 2396, section 5.2, step 6, sub-steps c through f; that is:
     *
     *  - All "." segments are removed
     *
     *  - If a ".." segment is preceded by a non-".." segment then both of these segments are removed. This step is repeated until it is no longer applicable.
     *
     *  - If the path is relative, and if its first segment contains a colon character (':'), then a "." segment is prepended.
     *  This prevents a relative URI with a path such as "a:b/c/d" from later being re-parsed as an opaque URI with a scheme of "a"
     *  and a scheme-specific part of "b/c/d". (Deviation from RFC 2396)
     *
     * A normalized path will begin with one or more ".." segments if there were insufficient non-".." segments preceding them to allow their removal.
     * A normalized path will begin with a "." segment if one was inserted by step 3 above. Otherwise, a normalized path will not contain any "." or ".." segments
     *
     * @return Uri A URI equivalent to this URI, but whose path is in normal form.
     */
    public function normalize()
    {
        if ($this->isOpaque() || !$this->path) {
            return clone $this;
        } else {
            $uri = clone $this;

            $uri->path = self::normalizePath($this->path);
            return $uri;
        }
    }

    /**
     * JRE1.5 Port
     *
     * @param string $s
     * @return string
     * @internal
     */
    private static function normalizePath($s)
    {
        $i = self::needsNormalization($s);
        if ($i !== false) {

            $ac = str_split($s, 1);
            $ai = array_fill(0, $i, 0);

            try {
                self::split($ac, $ai);
            } catch (\OutOfBoundsException $e) {
                // something strange here )
            }
            self::removeDots($ac, $ai);
            self::leadingDots($ac, $ai);

            $i = self::join($ac, $ai);
            return implode('', array_slice($ac, 0, $i));
        } else {
            return $s;
        }
    }

    /**
     * JRE1.5 Port
     *
     * @param string $s
     * @return int | false
     * @internal
     */
    private static function needsNormalization($s)
    {
        $j = strlen($s) - 1;

        for ($k = 0; $k <= $j; $k++) {
            if (substr($s, $k, 1) !== '/') break;
        }

        $flag = ($k > 1) ? false : true;
        $i = 0;
        while ($k <= $j) {
            $c = substr($s, $k, 1);
            $c2 = substr($s, $k + 1, 1);
            $c3 = substr($s, $k + 2, 1);

            if ($c === '.' && ($k == $j || $c2 === '/' || $c2 === '.' && ($k + 1 == $j || $c3 === '/'))
            ) {
                $flag = false;
            }
            $i++;
            do {
                if ($k > $j) {
                    continue 2;
                }
            } while (substr($s, $k++, 1) !== '/');
            while ($k <= $j && substr($s, $k, 1) === '/') {
                $flag = false;
                $k++;
            }
        }

        return $flag ? false : $i;
    }

    /**
     * JRE1.5 Port
     *
     * @param array $ac
     * @param array $ai
     * @internal
     * @return int
     */
    private static function join(&$ac, $ai)
    {
        $i = count($ai);
        $j = count($ac) - 1;
        $k = 0;
        if ($ac[$k] == '') {
            $ac[$k++] = '/';
        }
        for ($l = 0; $l < $i; $l++) {
            $i1 = $ai[$l];
            if ($i1 == -1) {
                continue;
            }
            if ($k == $i1) {
                for (; $k <= $j && $ac[$k] != ''; $k++) {
                    ;
                }
                if ($k <= $j) {
                    $ac[$k++] = '/';
                }
                continue;
            }
            if ($k < $i1) {
                for (; $i1 <= $j && $ac[$i1] != ''; $ac[$k++] = $ac[$i1++]) {
                    ;
                }
                if ($i1 <= $j) {
                    $ac[$k++] = '/';
                }
            } else {
                user_error('Internal error');
            }
        }
        return $k;
    }

    /**
     * JRE1.5 Port
     *
     * @throws \OutOfBoundsException
     * @param array $ac
     * @param array $ai
     * @internal
     */
    private static function split(&$ac, &$ai)
    {
        $i = count($ac) - 1;
        $j = 0;
        $k = 0;
        for (; $j <= $i && $ac[$j] == '/'; $j++) {
            $ac[$j] = '';
        }
        while ($j <= $i) {
            $ai[$k++] = $j++;
            do {
                if ($j > $i) {
                    continue 2;
                }
            } while ($ac[$j++] != '/');
            $ac[$j - 1] = '';
            while ($j <= $i && $ac[$j] == '/') {
                $ac[$j++] = '';
            }
        }

        if ($k != count($ai)) {
            throw new \OutOfBoundsException('Internal error');
        }
    }

    /**
     * JRE1.5 Port
     *
     *
     * @todo REFACTOR
     * @param array $ac
     * @param array $ai
     * @internal
     */
    private static function removeDots(&$ac, &$ai)
    {
        $i = count($ai);
        $j = count($ac) - 1;
        for ($k = 0; $k < $i; $k++) {
            $byte0 = 0;
            do {
                $l = $ai[$k];
                if ($ac[$l] === '.') {

                    if (($l == $j) || ($ac[$l + 1] === '')) {
                        $byte0 = 1;
                        break;
                    } else if ($ac[$l + 1] === '.' && $l + 1 == $j || $ac[$l + 2] === '') {
                        $byte0 = 2;
                        break;
                    }
                }
            } while (++$k < $i);

            if ($k > $i || $byte0 === 0) {
                break;
            } elseif ($byte0 === 1) {
                $ai[$k] = -1;
            } else {
                //$i1;
                for ($i1 = $k - 1; $i1 >= 0; $i1--) {
                    if ($ai[$i1] !== -1) break;
                }
                if ($i1 >= 0) {
                    $j1 = $ai[$i1];
                    if ($ac[$j1] !== '.' || $ac[$j1 + 1] !== '.' || $ac[$j1 + 2] !== '') {
                        $ai[$k] = -1;
                        $ai[$i1] = -1;
                    }
                }
            }
        }

    }

    private static function leadingDots(&$ac, &$ai)
    {
        if ($ac[0] !== '') {
            $i = count($ai);
            for ($j = 0; $j < $i && $ai[$j] < 0; $j++) {
                ;
            }
            if ($j >= $i || $j == 0) {
                return;
            }
            for ($k = $ai[$j]; $k < count($ac); $k++) {
                if ($ac[$k] === ':' || $ac[$k] === '') break;
            }
            if ($k < count($ac) && $ac[$k] !== '') {
                $ac[0] = '.';
                $ac[1] = '';
                $ai[0] = 0;
            }
        }
    }

    private function hashCode()
    {
        if ($this->hash === null) {
            $this->hash = ($this->scheme . $this->authority . $this->fragment . $this->path . $this->query);
        }

        return $this->hash;
    }

    /**
     * Returns the content of this URI as a string.
     *
     * This URI was created by normalization, resolution, or relativization,
     * and so a string is constructed from this URI's components
     * according to the rules specified in RFC 2396, section 5.2, step 7.
     *
     * @return string
     */
    public function __toString()
    {
        static $str = array();

        return (isset($str[$hash = $this->hashCode()])) ? $str[$hash] : ($str[$hash] = $this->buildStr());
    }


    public function __clone()
    {
        $this->hash = null;
    }

    /**
     * Returns the content of this URI as a string.
     *
     * This URI was created by normalization, resolution, or relativization,
     * and so a string is constructed from this URI's components
     * according to the rules specified in RFC 2396, section 5.2, step 7.
     *
     * @return string
     */
    protected function buildStr()
    {
        $uri = ($this->scheme != null) ? ($this->scheme . ':') : '';
        if ($this->isOpaque()) {
            $uri .= $this->schemeSpecificPart;
        } else {

            $uri .= $this->buildAuthorityStr();

            if ($this->path !== null) {
                $uri .= $this->path;
            }
            if ($this->query !== null) {
                $uri .= '?' . $this->query;
            }
        }
        if ($this->fragment !== null) {
            $uri .= '#' . $this->fragment;
        }
        return $uri;
    }

    /**
     * Returns the decoded authority component of this URI.
     *
     * @return string The decoded authority component of this URI, or null if the authority is undefined.
     */
    public function getAuthority()
    {
        return urldecode($this->authority);
    }

    /**
     * Set authority component of this URI.
     *
     * @param string $authority The decoded authority component of this URI, or null if the authority is undefined.
     * @throws \DomainException
     * @return Uri
     */
    protected function setAuthority($authority)
    {
        $aparts = [];
        if (preg_match('/^(([^\@]+)\@)?(.*?)(\:(.+))?$/S', $authority, $aparts)) {
            $this->authority = $authority;

            $this->hash = null;

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
            throw new \DomainException("Hierarchical URL authority part syntax error");
        }

        return $this;
    }

    /**
     * Returns the decoded fragment component of this URI.
     *
     * @return string The decoded fragment component of this URI, or null if the fragment is undefined
     */
    public function getFragment()
    {
        return urldecode($this->fragment);
    }


    /**
     * Returns the decoded path component of this URI
     *
     * @return string The decoded path component of this URI, or null if the path is undefined
     */
    public function getPath()
    {
        return urldecode($this->path);
    }


    /**
     * Returns the decoded query component of this URI.
     *
     * @return string The decoded query component of this URI, or null if the query is undefined
     */
    public function getQuery()
    {
        return urldecode($this->query);
    }


    /**
     * Returns the scheme component of this URI.
     *
     * The scheme component of a URI, if defined, only contains characters in the alphanum category and in the string "-.+". A scheme always starts with an alpha character.
     * The scheme component of a URI cannot contain escaped octets, hence this method does not perform any decoding.
     * @return string The scheme component of this URI, or null if the scheme is undefined
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * Returns the decoded scheme-specific part of this URI.
     *
     * @return string The decoded scheme-specific part of this URI (never null)
     */
    public function getSchemeSpecificPart()
    {
        return urldecode($this->schemeSpecificPart);
    }


    /**
     * Tells whether or not this URI is absolute.
     *
     * @return boolean true if, and only if, this URI is absolute
     */
    public function isAbsolute()
    {
        return ($this->scheme !== null);
    }

    /**
     * Tells whether or not this URI is opaque.
     *
     * A URI is opaque if, and only if, it is absolute and its scheme-specific part does not begin with a slash character ('/').
     * An opaque URI has a scheme, a scheme-specific part, and possibly a fragment; all other components are undefined.
     *
     * @return boolean true if, and only if, this URI is opaque
     */
    public function isOpaque()
    {
        return ($this->path === null);
    }


    /**
     * Get new uri instance related to provided base
     *
     * Current: http://user:password@example.com/path/path2?k=v#fragment
     * Base: /path/
     * Return: path2?k=v#fragment
     *
     * @param Uri $baseUri Base uri
     * @return $this
     */
    public function getRelated(Uri $baseUri)
    {
        $path = $baseUri->getPath();
        if (substr($this->getPath(), 0, strlen($path)) == $path) {
            $class = get_called_class();
            $uri = new $class('');
            $uri->fragment = $this->fragment;
            $uri->path = substr($this->getPath(), strlen($path));
            $uri->query = $this->query;
            return $uri;
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
     * Returns the port number of this URI.
     *
     * @return string The port component of this URI, or null if the port is undefined
     */
    public function getPort()
    {
        return $this->port;
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
            if ($this->scheme) {
                return '//' . $this->authority;
            } else {
                return $this->authority;
            }
        }
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
        $url->host = $host;

        $url->hash = null;

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
        $url->port = $port;
        $url->hash = null;

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
        $url->query = $query;
        $url->hash = null;

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