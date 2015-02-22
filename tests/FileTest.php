<?php
/**
 * Created by PhpStorm.
 * User: Стас
 * Date: 18.01.2015
 * Time: 16:05
 */

namespace wmlib\uri\Tests;

use wmlib\uri\File;

class FileTest extends \PHPUnit_Framework_TestCase
{

    public function testWinShare()
    {
        $file = new File('file://server/share/My%20Documents%20100%2520/foo.txt');
        $this->assertEquals('\\\\server\share\My Documents 100%20\foo.txt', $file->getName(File::OS_WINDOWS));
    }

    public function testWinFile()
    {
        $file = new File('file:///c:/windows/My%20Documents%20100%2520/foo.txt');
        $this->assertEquals('c:\windows\My Documents 100%20\foo.txt', $file->getName(File::OS_WINDOWS));
    }

    public function testDirectoryWin()
    {
        $file = new File('file:///c:/windows/My%20Documents%20100%2520/foo.txt');
        $this->assertEquals('c:\windows\My Documents 100%20', $file->getDirectory()->getName(File::OS_WINDOWS));
    }

    public function testDirectory()
    {
        $file = new File('file://localhost/var/log/system.log');
        $this->assertEquals('file://localhost/var/log', (string)$file->getDirectory());
    }

    public function testDirectoryRoot()
    {
        $file = new File('file://localhost/var/log/system.log');
        $this->assertEquals('file://localhost/', (string)$file->getDirectory()->getDirectory()->getDirectory()->getDirectory());
    }
}
