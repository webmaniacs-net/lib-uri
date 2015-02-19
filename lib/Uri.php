<?php
namespace wmlib\uri;

use wmlib\uri\Exception\SyntaxException;

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
class Uri
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
     * @throws SyntaxException If the given string violates RFC 2396, as augmented by the above deviations.
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
                throw new SyntaxException("Hierarchical URI scheme-specific part syntax error");
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
     * @param self $uri The URI to be resolved against this URI
     * @return self The resulting URI
     */
    public function resolve(self $uri)
    {
        static $resolve = array();

        return (isset($resolve[$hash = $this->hashCode() . $uri->hashCode()])) ? $resolve[$hash] : ($resolve[$hash] = $this->createResolve($uri));
    }

    private function createResolve(self $uri)
    {
        if ($uri->isAbsolute() || $this->isOpaque()) {
            return clone $uri;
        }
        if ($uri->scheme == null && $uri->authority == null && $uri->path == '' && $uri->fragment && !$uri->query) {
            if ($this->fragment != null && $uri->fragment == $this->fragment) {
                return clone $this;
            } else {
                $class = get_called_class();
                $uri2 = new $class('');
                /** @var $uri2 Uri */
                $uri2->scheme = $this->scheme;
                $uri2->schemeSpecificPart = $this->schemeSpecificPart;
                $uri2->setAuthority($this->authority);

                $uri2->path = $this->path;
                $uri2->fragment = $uri->fragment;
                $uri2->query = $this->query;
                $uri2->hash = null;
                return $uri2;
            }
        }
        if ($uri->scheme) {
            return clone $uri;
        }
        $class = get_called_class();
        $uri2 = new $class('');
        /** @var $uri2 Uri */
        $uri2->scheme = $this->scheme;
        $uri2->query = $uri->query;
        $uri2->fragment = $uri->fragment;
        if (!$uri->authority) {
            $uri2->setAuthority($this->authority);

            if (strlen($uri->path) > 0 && substr($uri->path, 0, 1) == '/') {
                $uri2->path = $uri->path;
            } else {
                $pos = strrpos($this->path, '/');
                $s2 = ($pos !== false) ? substr($this->path, 0, $pos + 1) : '';
                if (strlen($uri->path) != 0) {
                    $s2 .= $uri->path;
                }
                $uri2->path = self::normalizePath($s2);
            }
        } else {
            $uri2->setAuthority($uri->authority);
            $uri2->path = $uri->path;
        }
        $uri2->hash = null;
        return $uri2;
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
     * @return self A URI equivalent to this URI, but whose path is in normal form.
     */
    public function normalize()
    {
        if ($this->isOpaque() || !$this->path) {
            return clone $this;
        }
        $s = self::normalizePath($this->path);
        if ($s == $this->path) {
            return clone $this;
        } else {
            $class = get_called_class();
            $uri = new $class('');
            /** @var $uri Uri */
            $uri->scheme = $this->scheme;
            $uri->fragment = $this->fragment;
            $uri->setAuthority($this->authority);

            $uri->path = $s;
            $uri->query = $this->query;
            $uri->hash = null;
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
        if ($i === false) {
            return $s;
        }
        $ac = array();
        for ($c = 0, $l = strlen($s); $c < $l; $c++) {
            $ac[] = substr($s, $c, 1);
        }
        $ai = array();
        for ($c = 0; $c < $i; $c++) {
            $ai[] = 0;
        }
        self::split($ac, $ai);
        self::removeDots($ac, $ai);
        $i = self::join($ac, $ai);
        return implode('', array_slice($ac, 0, $i));
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
        for ($k = 0; $k <= $j && substr($s, $k, 1) === '/'; $k++) {
            ;
        }
        $flag = ($k > 1) ? false : true;
        $i = 0;
        do {
            if ($k > $j) {
                break;
            }
            if (substr($s, $k, 1) === '.' && ($k == $j || substr($s, $k + 1, 1) === '/' || substr($s, $k + 1,
                        1) === '.' && ($k + 1 == $j || substr($s, $k + 2, 1) === '/'))
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
        } while (true);
        return $flag ? false : $i;
    }

    /**
     * JRE1.5 Port
     *
     * @param array $ac
     * @param array $ai
     * @internal
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
        do {
            if ($j > $i) {
                break;
            }
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
        } while (true);
        if ($k != count($ai)) {
            user_error('Internal error');
        } else {
            return;
        }
    }

    /**
     * JRE1.5 Port
     *
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
                if ($ac[$l] !== '.') {
                    continue;
                }
                if ($l == $j) {
                    $byte0 = 1;
                    break;
                }
                if ($ac[$l + 1] === '') {
                    $byte0 = 1;
                    break;
                }
                if ($ac[$l + 1] !== '.' || $l + 1 != $j && $ac[$l + 2] !== '') {
                    continue;
                }
                $byte0 = 2;
                break;
            } while (++$k < $i);
            if ($k > $i || $byte0 === 0) {
                break;
            }
            if ($byte0 === 1) {
                $ai[$k] = -1;
                continue;
            }
            //$i1;
            for ($i1 = $k - 1; $i1 >= 0 && $ai[$i1] === -1; $i1--) {
                ;
            }
            if ($i1 >= 0) {
                $j1 = $ai[$i1];
                if ($ac[$j1] !== '.' || $ac[$j1 + 1] !== '.' || $ac[$j1 + 2] !== '') {
                    $ai[$k] = -1;
                    $ai[$i1] = -1;
                }
            }
        }
        // maybeAddLeadingDot() implantation
        if ($ac[0] !== '') {
            $i = count($ai);
            for ($j = 0; $j < $i && $ai[$j] < 0; $j++) {
                ;
            }
            if ($j >= $i || $j == 0) {
                return;
            }
            for ($k = $ai[$j]; $k < count($ac) && $ac[$k] !== ':' && $ac[$k] !== ''; $k++) {
                ;
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

            if ($this->scheme) {
                $uri .= '//' . $this->authority;
            } else {
                $uri .= $this->authority;
            }
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
        static $decoded;
        if ($decoded === null && $this->authority !== null) {
            $decoded = $this->decode($this->authority);
        }
        return $decoded;
    }

    /**
     * Set authority component of this URI.
     *
     * @param string $authority The decoded authority component of this URI, or null if the authority is undefined.
     * @return Uri
     */
    public function setAuthority($authority)
    {
        $this->authority = $authority;

        $this->hash = null;

        return $this;
    }

    /**
     * Returns the decoded fragment component of this URI.
     *
     * @return string The decoded fragment component of this URI, or null if the fragment is undefined
     */
    public function getFragment()
    {
        static $decoded;
        if ($decoded === null && $this->fragment !== null) {
            $decoded = $this->decode($this->fragment);
        }
        return $decoded;
    }


    /**
     * Returns the decoded path component of this URI
     *
     * @return string The decoded path component of this URI, or null if the path is undefined
     */
    public function getPath()
    {
        return $this->decode($this->path);
    }


    /**
     * Returns the decoded query component of this URI.
     *
     * @return string The decoded query component of this URI, or null if the query is undefined
     */
    public function getQuery()
    {
        return $this->decode($this->query);
    }


    /**
     * Sets the query component of this URI.
     *
     * @param string $query The query component of URI
     */
    public function setQuery($query)
    {
        $this->query = $query;

        $this->hash = null;

        return $this;
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
     * Set URI scheme part
     *
     * @param string $scheme
     * @return Uri Fluent API support
     */
    public function setScheme($scheme)
    {
        $this->scheme = $scheme;

        $this->hash = null;

        return $this;
    }

    /**
     * Returns the decoded scheme-specific part of this URI.
     *
     * @return string The decoded scheme-specific part of this URI (never null)
     */
    public function getSchemeSpecificPart()
    {
        return $this->decode($this->schemeSpecificPart);
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
     *
     *
     * @param string $strIn
     * @return string
     */
    protected function decode($strIn)
    {
        $strOut = '';
        $iPos = 0;
        $len = strlen($strIn);
        while ($iPos < $len) {
            $charAt = substr($strIn, $iPos, 1);
            if ($charAt == '%') {
                $iPos++;
                $charAt = substr($strIn, $iPos, 1);
                if ($charAt == 'u') {
                    // Unicode character
                    $iPos++;
                    $unicodeHexVal = substr($strIn, $iPos, 4);
                    $unicode = hexdec($unicodeHexVal);
                    $strOut .= self::code2utf($unicode);
                    $iPos += 4;
                } else {
                    // Escaped ascii character
                    $hexVal = substr($strIn, $iPos, 2);
                    if (hexdec($hexVal) > 127) {
                        // Convert to Unicode
                        $strOut .= self::code2utf(hexdec($hexVal));
                    } else {
                        $strOut .= chr(hexdec($hexVal));
                    }
                    $iPos += 2;
                }
            } else {
                $strOut .= $charAt;
                $iPos++;
            }
        }
        return $strOut;
    }

    /**
     * JRE1.5 Port
     *
     * @param number $num
     * @return string
     * @internal
     */
    private static function code2utf($num)
    {
        if ($num < 128) {
            return chr($num);
        }
        if ($num < 1024) {
            return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
        }
        if ($num < 32768) {
            return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        }
        if ($num < 2097152) {
            return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        }
        return '';
    }

    public function getRelated(Uri $baseUrl)
    {
        $path = $baseUrl->getPath();
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
}