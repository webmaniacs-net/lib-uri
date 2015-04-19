<?php
namespace wmlib\uri;


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
    const OS_UNIX = 'unix';
    const OS_WINDOWS = 'windows';
    const OS_MAC = 'mac';


    /**
     * Get legacy file string name
     *
     * @return string
     */
    public function getName($os = null)
    {
        if ($os === null) {
            if (strstr(strtoupper(php_uname()), "WIN")) $os = self::OS_WINDOWS;
            elseif (strstr(strtoupper(php_uname()), "MACOS")) $os = self::OS_MAC;
            else $os = self::OS_UNIX;
        }
        $host = $this->getHost();

        $filename = $this->getPath();
        if ($filename{0} === '/' && $os == self::OS_WINDOWS) {
            $filename = substr($filename, 1);
        }
        if ($os == self::OS_WINDOWS) {
            $filename = str_replace('/', '\\', $filename);
        }

        if ($os == self::OS_WINDOWS) {
            if ($host && $host !== 'localhost') return sprintf('\\\\%s\%s', $host, $filename);
            else return $filename;
        } else {
            return $this->buildStr();
        }
    }

    /**
     * Get directory file uri
     *
     * return File
     */
    public function getDirectory()
    {
        $dir = clone $this;
        $dir->path = str_replace(DIRECTORY_SEPARATOR, '/', dirname($this->path));
        if (!$dir->path) $dir->path = '/';

        return $dir;
    }
}