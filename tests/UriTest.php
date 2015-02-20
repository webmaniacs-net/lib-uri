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
    }


}
