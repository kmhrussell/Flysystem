<?php

namespace Flysystem;

use LogicException;
use InvalidArgumentException;

class Filesystem implements FilesystemInterface
{
    /**
     * @var  AdapterInterface  $adapter
     */
    protected $adapter;

    /**
     * @var  CacheInterface  $cache
     */
    protected $cache;

    /**
     * @var  string  $visibility
     */
    protected $visibility;

    /**
     * @var  array  $plugins
     */
    protected $plugins = array();

    /**
     * Constructor
     *
     * @param AdapterInterface $adapter
     * @param CacheInterface   $cache
     * @param string           $visibility
     */
    public function __construct(AdapterInterface $adapter, CacheInterface $cache = null, $visibility = AdapterInterface::VISIBILITY_PUBLIC)
    {
        $this->adapter = $adapter;
        $this->cache = $cache ?: new Cache\Memory;
        $this->cache->load();
        $this->visibility = $visibility;
    }

    /**
     * Get the Adapter
     *
     * @return  AdapterInterface  adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Get the Cache
     *
     * @return  CacheInterface  adapter
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Check whether a path exists
     *
     * @param  string  $path path to check
     * @return boolean whether the path exists
     */
    public function has($path)
    {
        if ($this->cache->has($path)) {
            return true;
        }

        if ($this->cache->isComplete(Util::dirname($path), false) or ($object = $this->adapter->has($path)) === false) {
            return false;
        }

        $this->cache->updateObject($path, $object === true ? array() : $object, true);

        return true;
    }

    /**
     * Write a file
     *
     * @param  string              $path     path to file
     * @param  string              $contents file contents
     * @param  string              $visibility
     * @throws FileExistsException
     * @return boolean             success boolean
     */
    public function write($path, $contents, $visibility = null)
    {
        $this->assertAbsent($path);

        if ( ! $object = $this->adapter->write($path, $contents, $visibility ?: $this->visibility)) {
            return false;
        }

        $this->cache->updateObject($path, $object, true);
        $this->cache->ensureParentDirectories($path);

        return true;
    }

    public function writeStream($path, $resource, $visibility = null)
    {
        $this->assertAbsent($path);

        if ( ! is_resource($resource)) {
            throw new InvalidArgumentException(__METHOD__.' expects argument #2 to be a valid resource.');
        }

        if ( ! $object = $this->adapter->writeStream($path, $resource, $visibility ?: $this->visibility)) {
            return false;
        }

        $this->cache->updateObject($path, $object, true);
        $this->cache->ensureParentDirectories($path);

        return true;
    }

    /**
     * Update a file with the contents of a stream
     *
     * @param   string    $path
     * @param   resource  $resource
     * @return  bool      success boolean
     * @throws  InvalidArgumentException
     */
    public function updateStream($path, $resource)
    {
        $this->assertPresent($path);

        if ( ! is_resource($resource)) {
            throw new InvalidArgumentException(__METHOD__.' expects argument #2 to be a valid resource.');
        }

        if ( ! $object = $this->adapter->updateStream($path, $resource)) {
            return false;
        }

        $this->cache->updateObject($path, $object, true);
        $this->cache->ensureParentDirectories($path);

        return true;
    }

    /**
     * Retrieves a read-stream for a path
     *
     * @param   string  $path
     * @return  resource|false  path resource or false when on failure
     */
    public function readStream($path)
    {
        $this->assertPresent($path);

        if ( ! $object = $this->adapter->readStream($path)) {
            return false;
        }

        return $object['stream'];
    }

    /**
     * Create a file or update if exists
     *
     * @param  string              $path     path to file
     * @param  string              $contents file contents
     * @param  string              $visibility
     * @throws FileExistsException
     * @return boolean             success boolean
     */
    public function put($path, $contents, $visibility = null)
    {
        if ($this->has($path)) {
            if (($object = $this->adapter->update($path, $contents)) === false) {
                return false;
            }

            $this->cache->updateObject($path, $object, true);
        } else {
            if ( ! $object = $this->adapter->write($path, $contents, $visibility ?: $this->visibility)) {
                return false;
            }

            $this->cache->updateObject($path, $object, true);
            $this->cache->ensureParentDirectories($path);
        }

        return true;
    }

    /**
     * Update a file
     *
     * @param  string                $path     path to file
     * @param  string                $contents file contents
     * @throws FileNotFoundException
     * @return boolean               success boolean
     */
    public function update($path, $contents)
    {
        $this->assertPresent($path);
        $object = $this->adapter->update($path, $contents);

        if ($object === false) {
            return false;
        }

        $this->cache->updateObject($path, $object, true);

        return true;
    }

    /**
     * Read a file
     *
     * @param  string                $path path to file
     * @throws FileNotFoundException
     * @return string                file contents
     */
    public function read($path)
    {
        $this->assertPresent($path);

        if ($contents = $this->cache->read($path)) {
            return $contents;
        }

        if ( ! $object = $this->adapter->read($path)) {
            return false;
        }

        $this->cache->updateObject($path, $object, true);

        return $object['contents'];
    }

    /**
     * Rename a file
     *
     * @param  string                $path    path to file
     * @param  string                $newpath new path
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @return boolean               success boolean
     */
    public function rename($path, $newpath)
    {
        $this->assertPresent($path);
        $this->assertAbsent($newpath);

        if ($this->adapter->rename($path, $newpath) === false) {
            return false;
        }

        $this->cache->rename($path, $newpath);

        return true;
    }

    /**
     * Delete a file
     *
     * @param  string                $path path to file
     * @throws FileNotFoundException
     * @return boolean               success boolean
     */
    public function delete($path)
    {
        $this->assertPresent($path);

        if ($this->adapter->delete($path) === false) {
            return false;
        }

        $this->cache->delete($path);

        return true;
    }

    /**
     * Delete a directory
     *
     * @param  string  $dirname path to directory
     * @return boolean success boolean
     */
    public function deleteDir($dirname)
    {
        if ($this->adapter->deleteDir($dirname) === false) {
            return false;
        }

        $this->cache->deleteDir($dirname);

        return true;
    }

    /**
     * Create a directory
     *
     * @param   string  $dirname  directory name
     * @return  void
     */
    public function createDir($dirname)
    {
        $object = $this->adapter->createDir($dirname);

        $this->cache->updateObject($dirname, $object, true);
    }

    /**
     * List the filesystem contents
     *
     * @param  string   $directory
     * @param  boolean  $recursive
     * @return array    contents
     */
    public function listContents($directory = '', $recursive = false)
    {
        if ($this->cache->isComplete($directory, $recursive)) {
            return $this->cache->listContents($directory, $recursive);
        }

        $contents = $this->adapter->listContents($directory, $recursive);

        return $this->cache->storeContents($directory, $contents, $recursive);
    }

    /**
     * List all paths
     *
     * @return  array  paths
     */
    public function listPaths($directory = '', $recursive = false)
    {
        $result = array();
        $contents = $this->listContents($directory, $recursive);

        foreach ($contents as $object) {
            $result[] = $object['path'];
        }

        return $result;
    }

    /**
     * List contents with metadata
     *
     * @param   array  $key  metadata key
     * @return  array            listing with metadata
     */
    public function listWith(array $keys = array(), $directory = '', $recursive = false)
    {
        $contents = $this->listContents($directory, $recursive);

        foreach ($contents as $index => $object) {
            if ($object['type'] === 'file') {
                $contents[$index] = array_merge($object, $this->getWithMetadata($object['path'], $keys));
            }
        }

        return $contents;
    }

    /**
     * Get metadata for an object with required metadata
     *
     * @param   string  $path      path to file
     * @param   array   $metadata  metadata keys
     * @throws InvalidArgumentException
     * @return  array   metadata
     */
    public function getWithMetadata($path, array $metadata)
    {
        $object = $this->getMetadata($path);

        foreach ($metadata as $key) {
            if ( ! method_exists($this, $method = 'get'.ucfirst($key))) {
                throw new InvalidArgumentException('Could not fetch metadata: '.$key);
            }

            $object[$key] = $this->{$method}($path);
        }

        return $object;
    }

    /**
     * Get a file's mimetype
     *
     * @param  string                $path path to file
     * @throws FileNotFoundException
     * @return string                file mimetype
     */
    public function getMimetype($path)
    {
        $this->assertPresent($path);

        if ($mimetype = $this->cache->getMimetype($path)) {
            return $mimetype;
        }

        if ( ! $object = $this->adapter->getMimetype($path)) {
            return false;
        }

        $object = $this->cache->updateObject($path, $object, true);

        return $object['mimetype'];
    }

     /**
     * Get a file's timestamp
     *
     * @param  string                $path path to file
     * @throws FileNotFoundException
     * @return string                file mimetype
     */
    public function getTimestamp($path)
    {
        $this->assertPresent($path);

        if ($mimetype = $this->cache->getTimestamp($path)) {
            return $mimetype;
        }

        if ( ! $object = $this->adapter->getTimestamp($path)) {
            return false;
        }

        $object = $this->cache->updateObject($path, $object, true);

        return $object['timestamp'];
    }

    /**
     * Get a file's visibility
     *
     * @param   string  $path  path to file
     * @return  string  visibility (public|private)
     */
    public function getVisibility($path)
    {
        $this->assertPresent($path);

        if ($visibility = $this->cache->getVisibility($path)) {
            return $visibility;
        }

        if (($object = $this->adapter->getVisibility($path)) === false) {
            return false;
        }

        $this->cache->updateObject($path, $object, true);

        return $object['visibility'];
    }

    /**
     * Get a file's size
     *
     * @param   string  $path  path to file
     * @return  int     file size
     */
    public function getSize($path)
    {
        if ($visibility = $this->cache->getSize($path)) {
            return $visibility;
        }

        if (($object = $this->adapter->getSize($path)) === false) {
            return false;
        }

        $this->cache->updateObject($path, $object, true);

        return $object['size'];
    }

    /**
     * Get a file's size
     *
     * @param   string   $path        path to file
     * @param   string   $visibility  visibility
     * @return  boolean  success boolean
     */
    public function setVisibility($path, $visibility)
    {
        if ( ! $object = $this->adapter->setVisibility($path, $visibility)) {
            return false;
        }

        $this->cache->updateObject($path, $object, true);

        return $object['visibility'];
    }

    /**
     * Get a file's metadata
     *
     * @param  string                $path path to file
     * @throws FileNotFoundException
     * @return array                 file metadata
     */
    public function getMetadata($path)
    {
        $this->assertPresent($path);

        if ($metadata = $this->cache->getMetadata($path)) {
            return $metadata;
        }

        if ( ! $metadata = $this->adapter->getMetadata($path)) {
            return false;
        }

        return $this->cache->updateObject($path, $metadata, true);
    }

    /**
     * Get a file/directory handler
     *
     * @param   string   $path
     * @param   Handler  $handler
     * @return  Handler  file or directory handler
     */
    public function get($path, Handler $handler = null)
    {
        if ( ! $handler) {
            $metadata = $this->getMetadata($path);

            $handler = $metadata['type'] === 'file' ? new File($this, $path) : new Directory($this, $path);
        }

        $handler->setPath($path);
        $handler->setFilesystem($this);

        return $handler;
    }

    /**
     * Flush the cache
     *
     * @return  $this
     */
    public function flushCache()
    {
        $this->cache->flush();

        return $this;
    }

    /**
     * Assert a file is present
     *
     * @param  string                $path path to file
     * @throws FileNotFoundException
     */
    public function assertPresent($path)
    {
        if ( ! $this->has($path)) {
            throw new FileNotFoundException($path);
        }
    }

    /**
     * Assert a file is absent
     *
     * @param  string              $path path to file
     * @throws FileExistsException
     */
    public function assertAbsent($path)
    {
        if ($this->has($path)) {
            throw new FileExistsException($path);
        }
    }

    /**
     * Register a plugin
     *
     * @param   PluginInterface  $plugin
     * @return  $this
     */
    public function addPlugin(PluginInterface $plugin)
    {
        $plugin->setFilesystem($this);
        $method = $plugin->getMethod();

        $this->plugins[$method] = $plugin;

        return $this;
    }

    /**
     * Register a plugin
     *
     * @param   string           $method
     * @return  PluginInterface  $plugin
     * @throws  LogicException
     */
    protected function findPlugin($method)
    {
        if ( ! isset($this->plugins[$method])) {
            throw new LogicException('Plugin not found for method: '.$method);
        }

        return $this->plugins[$method];
    }

    /**
     * Plugins passthrough
     *
     * @param   string  $method
     * @param   array   $arguments
     * @return  mixed
     */
    public function __call($method, array $arguments)
    {
        $plugin = $this->findPlugin($method);
        $callback = array($plugin, 'handle');

        return call_user_func_array($callback, $arguments);
    }
}
