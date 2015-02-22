<?php
/**
 * Created by PhpStorm.
 * User: Стас
 * Date: 18.01.2015
 * Time: 16:05
 */

namespace wmlib\uri\Tests;

use wmlib\uri\Uri;

class UriTest extends \PHPUnit_Framework_TestCase
{

    public function testUrn()
    {
        $uri = new Uri('urn:oasis:names:specification:docbook:dtd:xml:4.1.2');
        $this->assertEquals('urn', $uri->getScheme());
        $this->assertEquals('oasis:names:specification:docbook:dtd:xml:4.1.2', $uri->getSchemeSpecificPart());
        $this->assertEquals(true, $uri->isAbsolute());
        $this->assertEquals(null, $uri->getAuthority());
    }

    public function testUrl()
    {
        $uri = new Uri('foo://username:password@example.com:8042/over/%D0%BF%D1%80%D0%BE%D0%B2%D0%B5%D1%80%D0%BA%D0%B0/index.dtb?type=animal&name=narwhal#nose');
        $this->assertEquals('username:password@example.com:8042', $uri->getAuthority());
        $this->assertEquals('/over/проверка/index.dtb', $uri->getPath());
    }

    public function testUrlUnicode()
    {
        $uri = new Uri('foo://username:password@example.com:8042/over/%D0%BF%D1%80%D0%BE%D0%B2%D0%B5%D1%80%D0%BA%D0%B0/index.dtb?type=animal&name=narwhal#nose');
        $this->assertEquals('username:password@example.com:8042', $uri->getAuthority());
        $this->assertEquals('/over/проверка/index.dtb', $uri->getPath());
    }

    public function testAbs()
    {
        $uri = new Uri('http://user:password@example.com/path/path2?k=v#fragment');
        $this->assertEquals(true, $uri->isAbsolute());

        $this->assertEquals('http', $uri->getScheme());
        $this->assertEquals('//user:password@example.com/path/path2?k=v', $uri->getSchemeSpecificPart());
        $this->assertEquals('example.com', $uri->getHost());
        $this->assertEquals('user:password', $uri->getUserInfo());
        $this->assertEquals('/path/path2', $uri->getPath());
        $this->assertEquals('k=v', $uri->getQuery());
        $this->assertEquals('fragment', $uri->getFragment());
    }

    public function testRelative()
    {
        $uri = new Uri('/path/path2?k=v#fragment');
        $this->assertEquals(false, $uri->isAbsolute());
        $this->assertEquals('/path/path2', $uri->getPath());
        $this->assertEquals('k=v', $uri->getQuery());
        $this->assertEquals('fragment', $uri->getFragment());
    }

    public function testGetRelated()
    {
        $uri = new Uri('http://user:password@example.com/path/path2?k=v#fragment');
        $related = $uri->getRelated(new Uri('/path/'));

        $this->assertEquals('path2?k=v#fragment', (string)$related);
    }

    public function testResolve()
    {
        $base = new Uri('http://user:password@example.com/path/path2?k=v#fragment');
        $uri = new Uri('/path2?k=v2#fragment2');
        $resolved = $base->resolve($uri);

        $this->assertEquals('http://user:password@example.com/path2?k=v2#fragment2', (string)$resolved);
    }

    public function testResolveRelated()
    {
        $base = new Uri('http://user:password@example.com/path/path2?k=v#fragment');
        $uri = new Uri('path2?k=v2#fragment2');
        $resolved = $base->resolve($uri);

        $this->assertEquals('http://user:password@example.com/path/path2?k=v2#fragment2', (string)$resolved);
    }

    public function testResolveLeadingDot()
    {
        $base = new Uri('http://user:password@example.com/path/path2/?k=v#fragment');
        $uri = new Uri('./path2?k=v2#fragment2');
        $resolved = $base->resolve($uri);

        $this->assertEquals('http://user:password@example.com/path/path2/path2?k=v2#fragment2', (string)$resolved);
    }

    public function testResolveFragment()
    {
        $base = new Uri('http://user:password@example.com/path/path2?k=v#fragment');
        $uri = new Uri('#fragment2');
        $resolved = $base->resolve($uri);

        $this->assertEquals('http://user:password@example.com/path/path2?k=v#fragment2', (string)$resolved);
    }

    public function testResolveBaseAbs()
    {
        $base = new Uri('/path/path2?k=v#fragment');
        $uri = new Uri('http://user2:password@example.com/path2?k=v2#fragment2');
        $resolved = $base->resolve($uri);

        $this->assertEquals('http://user2:password@example.com/path2?k=v2#fragment2', (string)$resolved);
    }

    public function testAbsString()
    {
        $base = new Uri('http://user:password@example.com/path/path2?k=v#fragment');

        $this->assertEquals('http://user:password@example.com/path/path2?k=v#fragment', $base->__toString());
    }

    public function testRelString()
    {
        $base = new Uri('/path/path2?k=v#fragment');

        $this->assertEquals('/path/path2?k=v#fragment', $base->__toString());
    }

    public function testNormalize()
    {
        $base = new Uri('http://user:password@example.com/./path/../path3/path2?k=v#fragment');
        $base = $base->normalize();

        $this->assertEquals('http://user:password@example.com/path3/path2?k=v#fragment', $base->__toString());
    }
}
