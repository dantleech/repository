<?php

/*
 * This file is part of the puli/repository package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Repository\Tests\Filesystem;

use Puli\Repository\Filesystem\FilesystemRepository;
use Puli\Repository\Filesystem\Resource\LocalDirectoryResource;
use Puli\Repository\Filesystem\Resource\LocalFileResource;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\Iterator\RecursiveResourceIteratorIterator;
use Puli\Repository\Resource\Iterator\ResourceCollectionIterator;
use Puli\Repository\ResourceRepository;
use Puli\Repository\Tests\AbstractRepositoryTest;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FilesystemRepositoryTest extends AbstractRepositoryTest
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    private $root;

    protected function setUp()
    {
        $this->filesystem = new Filesystem();

        while (false === mkdir($root = sys_get_temp_dir().'/puli/FilesystemRepositoryTest'.rand(10000, 99999), 0777, true)) {}

        $this->root = $root;

        parent::setUp();
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->filesystem->remove($this->root);
    }

    /**
     * @param DirectoryResource $root
     *
     * @return ResourceRepository
     */
    protected function createRepository(DirectoryResource $root)
    {
        $iterator = new RecursiveResourceIteratorIterator(
            new ResourceCollectionIterator($root->listEntries()),
            RecursiveResourceIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $resource) {
            if ($resource instanceof DirectoryResource) {
                $this->filesystem->mkdir($this->root.$resource->getPath());
            } else {
                file_put_contents($this->root.$resource->getPath(), $resource->getContents());
            }
        }

        return new FilesystemRepository($this->root);
    }

    protected function assertSameResource($expected, $actual)
    {
        // Don't use assertSame(), because FilesystemRepository always creates
        // new resources without caching them
        $this->assertEquals($expected, $actual);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPassNonExistingRootDirectory()
    {
        new FilesystemRepository($this->root.'/foo');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPassFileAsRootDirectory()
    {
        touch($this->root.'/file');

        new FilesystemRepository($this->root.'/file');
    }

    public function testGetFile()
    {
        touch($this->root.'/file');

        $repo = new FilesystemRepository($this->root);

        $expected = new LocalFileResource($this->root.'/file', '/file');
        $expected->attachTo($repo);

        $this->assertEquals($expected, $repo->get('/file'));
    }

    public function testGetDirectory()
    {
        mkdir($this->root.'/dir');

        $repo = new FilesystemRepository($this->root);

        $expected = new LocalDirectoryResource($this->root.'/dir', '/dir');
        $expected->attachTo($repo);

        $this->assertEquals($expected, $repo->get('/dir'));
    }

    public function testGetFileLink()
    {
        touch($this->root.'/file');
        symlink($this->root.'/file', $this->root.'/link');

        $repo = new FilesystemRepository($this->root);

        $expected = new LocalFileResource($this->root.'/link', '/link');
        $expected->attachTo($repo);

        $this->assertEquals($expected, $repo->get('/link'));
    }

    public function testGetDirectoryLink()
    {
        mkdir($this->root.'/dir');
        symlink($this->root.'/dir', $this->root.'/link');

        $repo = new FilesystemRepository($this->root);

        $expected = new LocalDirectoryResource($this->root.'/link', '/link');
        $expected->attachTo($repo);

        $this->assertEquals($expected, $repo->get('/link'));
    }

    public function testGetOverriddenFile()
    {
        // Not supported
        $this->pass();
    }

    public function testGetOverriddenDirectory()
    {
        // Not supported
        $this->pass();
    }
}
