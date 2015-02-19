<?php
/**
 * Created by PhpStorm.
 * User: Стас
 * Date: 18.01.2015
 * Time: 16:05
 */

namespace wmlib\uri\Tests;

use wmlib\uri\Url;

class UrlTest extends \PHPUnit_Framework_TestCase {

    public function testAbs()
    {
        $uri = new Url('http://user:password@example.com/path/path2?k=v#fragment');
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
        $uri = new Url('/path/path2?k=v#fragment');
        $this->assertEquals(false, $uri->isAbsolute());
        $this->assertEquals('/path/path2', $uri->getPath());
        $this->assertEquals('k=v', $uri->getQuery());
        $this->assertEquals('fragment', $uri->getFragment());
    }

    public function testGetRelated()
    {
        $uri = new Url('http://user:password@example.com/path/path2?k=v#fragment');
        $related = $uri->getRelated(new Url('/path/'));

        $this->assertEquals('path2?k=v#fragment', (string)$related);
    }

    public function testResolve()
    {
        $base = new Url('http://user:password@example.com/path/path2?k=v#fragment');
        $uri = new Url('/path2?k=v2#fragment2');
        $resolved = $base->resolve($uri);

        $this->assertEquals('http://user:password@example.com/path2?k=v2#fragment2', (string)$resolved);
    }
}
