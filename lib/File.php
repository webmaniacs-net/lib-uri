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
class File extends Uri
{

    /**
     * Public constructor
     *
     * @param string $uri
     */
    public function __construct($uri)
    {
        // check for file scheme root
        if (($pos = strpos($uri, ':')) === 1 && strstr(strtoupper(php_uname()), "WIN")) {
            $uri = 'file:///' . $uri{0} . '%3A' . str_replace(DIRECTORY_SEPARATOR, '/', substr($uri, 2));
        } else {
            $uri = 'file://' . $uri;
        }

        parent::__construct($uri);
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
        $filename = urldecode($this->getPath());
        if ($filename{0} === '/' && strstr(strtoupper(php_uname()), "WIN")) {
            $filename = substr($filename, 1);
        }
        return str_replace('/', DIRECTORY_SEPARATOR, $filename);
    }
}