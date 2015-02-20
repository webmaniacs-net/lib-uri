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
}
