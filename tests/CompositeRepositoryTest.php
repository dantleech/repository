<?php

/*
 * This file is part of the puli/repository package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Repository\Tests;

use PHPUnit_Framework_TestCase;
use Puli\Repository\CompositeRepository;
use Puli\Repository\Resource\Collection\ArrayResourceCollection;
use Puli\Repository\Tests\Resource\TestFile;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompositeRepositoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var CompositeRepository
     */
    private $repo;

    protected function setUp()
    {
        $this->repo = new CompositeRepository();
    }

    public function provideValidMountPoints()
    {
        return array(
            array('/webmozart'),
            array('/webmozart/'),
        );
    }

    public function provideInvalidMountPoints()
    {
        return array(
            array(''),
            array(null),
            array(123),
            array('\\'),
            array(new \stdClass()),
        );
    }

    /**
     * @dataProvider provideValidMountPoints
     */
    public function testMountRepository($mountPoint)
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $resource = new TestFile('/path/to/resource');
        $resource->attachTo($repo);
        $mountedPath = rtrim($mountPoint, '/').'/path/to/resource';

        $this->repo->mount($mountPoint, $repo);

        $repo->expects($this->once())
            ->method('get')
            ->with('/path/to/resource')
            ->will($this->returnValue($resource));

        $expected = $resource->createReference($mountedPath);

        $this->assertEquals($expected, $this->repo->get($mountedPath));
    }

    public function testGetPrefersMostSpecificMountPoint()
    {
        $repo1 = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $repo2 = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $resource1 = new TestFile('/resource1');
        $resource1->attachTo($repo1);
        $resource2 = new TestFile('/resource2');
        $resource2->attachTo($repo2);

        $this->repo->mount('/', $repo1);
        $this->repo->mount('/app', $repo2);

        $repo1->expects($this->once())
            ->method('get')
            ->with('/resource1')
            ->will($this->returnValue($resource1));
        $repo2->expects($this->once())
            ->method('get')
            ->with('/resource2')
            ->will($this->returnValue($resource2));

        $result1 = $this->repo->get('/resource1');

        $this->assertSame($resource1, $result1);

        $result2 = $this->repo->get('/app/resource2');

        $this->assertEquals('/app/resource2', $result2->getPath());
        $this->assertEquals('/resource2', $result2->getRepositoryPath());
        $this->assertSame($repo2, $result2->getRepository());
    }

    public function testMountRepositoryFactory()
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $resource = new TestFile('/path/to/resource');
        $resource->attachTo($repo);

        $this->repo->mount('/webmozart', function () use ($repo) {
            return $repo;
        });

        $repo->expects($this->once())
            ->method('get')
            ->with('/path/to/resource')
            ->will($this->returnValue($resource));

        $expected = $resource->createReference('/webmozart/path/to/resource');

        $this->assertEquals($expected, $this->repo->get('/webmozart/path/to/resource'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testMountExpectsValidRepositoryFactory()
    {
        $this->repo->mount('/webmozart', 'foo');
    }

    /**
     * @dataProvider provideInvalidMountPoints
     * @expectedException \InvalidArgumentException
     */
    public function testMountExpectsValidMountPoint($mountPoint)
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');

        $this->repo->mount($mountPoint, $repo);
    }

    /**
     * @expectedException \Puli\Repository\RepositoryFactoryException
     */
    public function testRepositoryFactoryMustReturnRepository()
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');

        $this->repo->mount('/webmozart', function () use ($repo) {
            return 'foo';
        });

        $this->repo->get('/webmozart/path/to/resource');
    }

    /**
     * @expectedException \Puli\Repository\Api\ResourceNotFoundException
     */
    public function testGetExpectsValidMountPoint()
    {
        $this->repo->get('/webmozart/path/to/resource');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetExpectsAbsolutePath()
    {
        $this->repo->get('webmozart');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetExpectsNonEmptyPath()
    {
        $this->repo->get('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetExpectsStringPath()
    {
        $this->repo->get(new \stdClass());
    }

    /**
     * @expectedException \Puli\Repository\Api\ResourceNotFoundException
     */
    public function testUnmount()
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');

        $this->repo->mount('/webmozart', $repo);
        $this->repo->unmount('/webmozart');

        $this->repo->get('/webmozart/path/to/resource');
    }

    /**
     * @expectedException \Puli\Repository\Api\ResourceNotFoundException
     */
    public function testUnmountWithTrailingSlash()
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');

        $this->repo->mount('/webmozart', $repo);
        $this->repo->unmount('/webmozart/');

        $this->repo->get('/webmozart/path/to/resource');
    }

    public function testUnmountDoesNothingIfMountPointNotFound()
    {
        $this->repo->unmount('/webmozart');
        $this->assertTrue(true);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUnmountExpectsAbsolutePath()
    {
        $this->repo->unmount('webmozart');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUnmountExpectsNonEmptyPath()
    {
        $this->repo->unmount('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUnmountExpectsStringPath()
    {
        $this->repo->unmount(new \stdClass());
    }

    public function testContains()
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');

        $this->repo->mount('/webmozart', $repo);

        $repo->expects($this->at(0))
            ->method('contains')
            ->with('/path/to/resource-1', 'glob')
            ->will($this->returnValue(true));
        $repo->expects($this->at(1))
            ->method('contains')
            ->with('/path/to/resource-2', 'glob')
            ->will($this->returnValue(false));

        $this->assertTrue($this->repo->contains('/webmozart/path/to/resource-1'));
        $this->assertFalse($this->repo->contains('/webmozart/path/to/resource-2'));
    }

    public function testContainsPrefersMostSpecificMountPoint()
    {
        $repo1 = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $repo2 = $this->getMock('Puli\Repository\Api\ResourceRepository');

        $this->repo->mount('/', $repo1);
        $this->repo->mount('/app', $repo2);

        $repo1->expects($this->once())
            ->method('contains')
            ->with('/resource-1', 'glob')
            ->will($this->returnValue(true));
        $repo2->expects($this->once())
            ->method('contains')
            ->with('/resource-2', 'glob')
            ->will($this->returnValue(false));

        $this->assertTrue($this->repo->contains('/resource-1'));
        $this->assertFalse($this->repo->contains('/app/resource-2'));
    }

    public function testContainsReturnsFalseIfMountPointNotFound()
    {
        $this->assertFalse($this->repo->contains('/webmozart'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testContainsExpectsAbsolutePath()
    {
        $this->repo->contains('webmozart');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testContainsExpectsNonEmptyPath()
    {
        $this->repo->contains('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testContainsExpectsStringPath()
    {
        $this->repo->contains(new \stdClass());
    }

    public function testFind()
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $resource1 = new TestFile('/path/to/res1');
        $resource2 = new TestFile('/path/to/res2');

        $this->repo->mount('/webmozart', $repo);

        $repo->expects($this->once())
            ->method('find')
            ->with('/path/to/res*', 'glob')
            ->will($this->returnValue(new ArrayResourceCollection(array(
                $resource1,
                $resource2,
            ))));

        $expected = new ArrayResourceCollection(array(
            $resource1->createReference('/webmozart/path/to/res1'),
            $resource2->createReference('/webmozart/path/to/res2'),
        ));

        $this->assertEquals($expected, $this->repo->find('/webmozart/path/to/res*'));
    }

    public function testFindPrefersMostSpecificMountPoint()
    {
        $repo1 = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $repo2 = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $resource1 = new TestFile('/res1');
        $resource2 = new TestFile('/res2');

        $this->repo->mount('/', $repo1);
        $this->repo->mount('/app', $repo2);

        $repo1->expects($this->once())
            ->method('find')
            ->with('/res1*', 'glob')
            ->will($this->returnValue(new ArrayResourceCollection(array($resource1))));
        $repo2->expects($this->once())
            ->method('find')
            ->with('/res2*', 'glob')
            ->will($this->returnValue(new ArrayResourceCollection(array($resource2))));

        $expected1 = new ArrayResourceCollection(array($resource1));
        $expected2 = new ArrayResourceCollection(array($resource2->createReference('/app/res2')));

        $this->assertEquals($expected1, $this->repo->find('/res1*'));
        $this->assertEquals($expected2, $this->repo->find('/app/res2*'));
    }

    public function testFindReturnsEmptyCollectionIfMountPointNotFound()
    {
        $resources = $this->repo->find('/webmozart/path/to/res*');

        $this->assertInstanceOf('Puli\Repository\Api\ResourceCollection', $resources);
        $this->assertCount(0, $resources);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFindExpectsAbsolutePath()
    {
        $this->repo->find('webmozart');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFindExpectsNonEmptyPath()
    {
        $this->repo->find('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFindExpectsStringPath()
    {
        $this->repo->find(new \stdClass());
    }

    public function testHasChildren()
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');

        $this->repo->mount('/webmozart', $repo);

        $repo->expects($this->once())
            ->method('hasChildren')
            ->with('/path/to/dir')
            ->will($this->returnValue('true_or_false'));

        $this->assertSame('true_or_false', $this->repo->hasChildren('/webmozart/path/to/dir'));
    }

    /**
     * @expectedException \Puli\Repository\Api\ResourceNotFoundException
     */
    public function testHasChildrenExpectsValidMountPoint()
    {
        $this->repo->hasChildren('/webmozart/path/to/dir');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testHasChildrenExpectsAbsolutePath()
    {
        $this->repo->hasChildren('webmozart');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testHasChildrenExpectsNonEmptyPath()
    {
        $this->repo->hasChildren('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testHasChildrenExpectsStringPath()
    {
        $this->repo->hasChildren(new \stdClass());
    }

    public function testListChildren()
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $resource1 = new TestFile('/path/to/dir/file1');
        $resource2 = new TestFile('/path/to/dir/file2');

        $this->repo->mount('/webmozart', $repo);

        $repo->expects($this->once())
            ->method('listChildren')
            ->with('/path/to/dir')
            ->will($this->returnValue(new ArrayResourceCollection(array(
                $resource1,
                $resource2,
            ))));

        $expected = new ArrayResourceCollection(array(
            $resource1->createReference('/webmozart/path/to/dir/file1'),
            $resource2->createReference('/webmozart/path/to/dir/file2'),
        ));

        $this->assertEquals($expected, $this->repo->listChildren('/webmozart/path/to/dir'));
    }

    public function testListChildrenWithMountPoints()
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $resource1 = new TestFile('/');

        $this->repo->mount('/foobar', $repo);
        $repo->expects($this->once())
            ->method('get')
            ->with('/')
            ->will($this->returnValue($resource1));

        $expected = new ArrayResourceCollection(array(
            $resource1->createReference('/foobar'),
        ));

        $this->assertEquals($expected, $this->repo->listChildren('/'));
    }

    public function testListRootDirectory()
    {
        $repo = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $resource1 = new TestFile('/path/to/dir/file1');
        $resource2 = new TestFile('/path/to/dir/file2');

        $this->repo->mount('/', $repo);

        $repo->expects($this->once())
            ->method('listChildren')
            ->with('/path/to/dir')
            ->will($this->returnValue(new ArrayResourceCollection(array(
                $resource1,
                $resource2,
            ))));

        $expected = new ArrayResourceCollection(array(
            $resource1,
            $resource2,
        ));

        $this->assertEquals($expected, $this->repo->listChildren('/path/to/dir'));
    }

    /**
     * @expectedException \Puli\Repository\Api\ResourceNotFoundException
     */
    public function testListChildrenExpectsValidMountPoint()
    {
        $this->repo->listChildren('/webmozart/path/to/dir');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testListChildrenExpectsAbsolutePath()
    {
        $this->repo->listChildren('webmozart');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testListChildrenExpectsNonEmptyPath()
    {
        $this->repo->listChildren('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testListChildrenExpectsStringPath()
    {
        $this->repo->listChildren(new \stdClass());
    }
}
