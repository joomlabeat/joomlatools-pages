<?php
/**
 * Joomlatools Pages
 *
 * @copyright   Copyright (C) 2018 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-pages for the canonical source repository
 */

class ComPagesPageRegistry extends KObject implements KObjectSingleton
{
    const PAGES_TREE = \RecursiveIteratorIterator::SELF_FIRST;
    const PAGES_ONLY = \RecursiveIteratorIterator::CHILD_FIRST;

    private  $__locator = null;

    private $__pages  = array();
    private $__data   = null;
    private $__collections = array();
    private $__redirects   = array();

    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        //Create the locator
        $this->__locator = $this->getObject('com:pages.page.locator');

        //Load the cache and do not refresh it
        $basedir = $this->getLocator()->getBasePath().'/pages';
        $this->__data = $this->loadCache($basedir, false);

        //Set the collection
        $this->__collections = array_merge(KObjectConfig::unbox($config->collections), $this->__data['collections']);

        //Set the redirects
        $this->__redirects = array_merge(KObjectConfig::unbox($config->redirects), $this->__data['redirects']);
    }

    protected function _initialize(KObjectConfig $config)
    {
        $config->append([
            'cache'       => JDEBUG ? false : true,
            'cache_path'  => Koowa::getInstance()->getRootPath().'/joomlatools-pages/cache',
            'cache_time'  => 60*60*24, //1 day
            'collections' => array(),
            'redirects'   => array(),
        ]);

        parent::_initialize($config);
    }

    public function getLocator()
    {
        return $this->__locator;
    }

    public function getCollection($source)
    {
        $result = false;

        if(isset($this->__collections[$source]))
        {
            $result = new KObjectConfig($this->__collections[$source]);

            if(!isset($result->source)) {
                $result->source = 'com:pages.model.pages';
            }
        }

        return $result;
    }

    public function getRedirects()
    {
        return $this->__redirects;
    }

    public function getPages($path = '', $mode = self::PAGES_ONLY, $depth = -1)
    {
        $result = array();
        $files  = $this->__data['files'];

        if($path = trim($path, '.'))
        {
            $segments = array();
            foreach(explode('/', $path) as $segment)
            {
                $segments[] = $segment;
                if(!isset($files[implode('/', $segments)]))
                {
                    $files = false;
                    break;
                }
                else $files = $files[implode('/', $segments)];
            }
        }

        if(is_array($files))
        {
            $iterator = new RecursiveArrayIterator($files);
            $iterator = new RecursiveIteratorIterator($iterator, $mode);

            //Set the max dept, -1 for full depth
            $iterator->setMaxDepth($depth);

            foreach ($iterator as $page => $file)
            {
                if(!is_string($file))
                {
                    //Do not include a directory without an index file (no existing page)
                    if(!$file = $this->getLocator()->locate('page://pages/'. $page)) {
                        continue;
                    }
                }

                //Get the relative file path
                $basedir = $this->getLocator()->getBasePath().'/pages';
                $file    = trim(str_replace($basedir, '', $file), '/');

                $result[$page] = $this->__data['pages'][$file];
            }
        }

        return $result;
    }

    public function getPage($path)
    {
        $page = false;

        $path = ltrim($path, './');

        if(!isset($this->__pages[$path]))
        {
            if($file = $this->getLocator()->locate('page://pages/'. $path))
            {
                //Get the relative file path
                $basedir = $this->getLocator()->getBasePath().'/pages';
                $file    = trim(str_replace($basedir, '', $file), '/');

                //Load the page
                $page = new ComPagesPageObject($this->__data['pages'][$file]);

                //Set page default properties from parent collection
                if(!$page->isCollection() && $page->path && $parent = $this->getPage($page->path))
                {
                    if(($collection = $parent->isCollection()) && isset($collection['page']))
                    {
                        foreach($collection['page'] as $property => $value)
                        {
                            if(!$page->has($property)) {
                                $page->set($property, $value);
                            }
                        }
                    }
                }

                //Set the layout (if not set yet)
                if($page->has('layout') && is_string($page->layout)) {
                    $page->layout = array('path' => $page->layout);
                }

                $this->__pages[$path] = $page;
            }
            else $this->__pages[$path] = false;
        }

        if (isset($this->__pages[$path])) {
            $page = $this->__pages[$path];
        }

        return $page;
    }

    public function getRoutes($path = null)
    {
        if(!is_null($path)) {
            $result = $this->__data['routes'][$path];
        } else {
            $result = $this->__data['routes'];
        }

        return $result;
    }

    public function isPage($path)
    {
        if(!isset($this->__pages[$path])) {
           $result = (bool) $this->getLocator()->locate('page://pages/'. $path);
        } else {
            $result = ($this->__pages[$path] === false) ? false : true;
        }

        return $result;
    }

    public function buildCache()
    {
        if($this->getConfig()->cache)
        {
            $basedir = $this->getLocator()->getBasePath().'/pages';
            $this->loadCache($basedir, true);
        }

        return false;
    }

    public function loadCache($basedir, $refresh = true)
    {
        if ($refresh || (!$cache = $this->isCached($basedir)))
        {
            $pages       = array();
            $routes      = array();
            $collections = array();
            $redirects   = array();

            //Create the data
            $iterate = function ($dir) use (&$iterate, $basedir, &$pages, &$routes, &$collections, &$redirects)
            {
                $order = false;
                $nodes = array();
                $files = array();

                //Only include pages
                if(is_dir($dir) && !file_exists($dir.'/.ignore'))
                {
                    //List
                    foreach (new DirectoryIterator($dir) as $node)
                    {
                        if (strpos($node->getFilename(), '.order.') !== false && !is_array($order)) {
                            $order = $this->getObject('object.config.factory')->fromFile((string)$node->getFileInfo(), false);
                        } else {
                            $nodes[] = $node->getFilename();
                        }
                    }

                    if(is_array($order)) {
                        //Remove files that don't exist from ordering (to prevent loops)
                        $nodes = array_merge(array_intersect($order, $nodes), $nodes);
                    } else {
                        //Order the files alphabetically
                        natsort($nodes);
                    }

                    //Prevent duplicates
                    if ($nodes = array_unique($nodes))
                    {
                        foreach ($nodes as $node)
                        {
                            //Exclude files or folder that start with '.' or '_'
                            if (!in_array($node[0], array('.', '_')))
                            {
                                $info = pathinfo($node);

                                $file = $dir . '/' . $node;
                                $path = trim(str_replace($basedir, '', $dir . '/' . $info['filename']), '/');

                                if (isset($info['extension']))
                                {
                                    /**
                                     * Variables
                                     *
                                     * Calculate the path specific variables
                                     */
                                    $format = pathinfo($path, PATHINFO_EXTENSION) ?: $info['extension'];
                                    $slug   = pathinfo($path, PATHINFO_FILENAME);
                                    $path   = trim(dirname($path), '.');

                                    //Handle index pages
                                    if($slug == 'index')
                                    {
                                        $slug = pathinfo($path, PATHINFO_FILENAME);
                                        $path = trim(dirname($path), '.');
                                    }

                                    //Setup the route
                                    $route = $path ? $path . '/' . $slug : $slug;
                                    if($format !== 'html') {
                                        $route .= '.'.$format;
                                    }

                                    /**
                                     * Page
                                     *
                                     * Load and initialise the page object
                                     */
                                    $page = (new ComPagesPageObject())->fromFile($file);

                                    //Set the path
                                    $page->path = $path;

                                    //Set the slug
                                    $page->slug = $slug;

                                    //Set the format
                                    $page->format = $format;

                                    //Set the process
                                    if (!$page->process) {
                                        $page->process = array();
                                    }

                                    //Set the route
                                    if (!$page->route && $page->route !== false) {
                                        $page->route = $route;
                                    }

                                    //Set the published state (if not set yet)
                                    if (!isset($page->published)) {
                                        $page->published = true;
                                    }

                                    //Set the date (if not set yet)
                                    if (!isset($page->date)) {
                                        $page->date = filemtime($file);
                                    }

                                    //Handle dynamic data
                                    array_walk_recursive ($page, function(&$value, $key)
                                    {
                                        if(is_string($value) && strpos($value, 'data://') === 0)
                                        {
                                            $matches = array();
                                            preg_match('#data\:\/\/([^\[]+)(?:\[(.*)\])*#si', $value, $matches);

                                            if(!empty($matches[0]))
                                            {
                                                $data = $this->getObject('data.registry')->getData($matches[1]);

                                                if($data && !empty($matches[2])) {
                                                    $data = $data->get($matches[2]);
                                                }

                                                $value = $data;
                                            }
                                        }
                                    });

                                    /**
                                     * Cache
                                     *
                                     * Inject data into the cache
                                     */
                                    $file = trim(str_replace($basedir, '', $file), '/');

                                    //Page
                                    $pages[$file] = $page->toArray();


                                    //Route
                                    if($page->route !== false) {
                                        $routes[$route] = (array) KObjectConfig::unbox($page->route);
                                    }

                                    //File (do not include index pages)
                                    if(strpos($file, '/index') === false) {
                                        $files[$route] = $file;
                                    }

                                    //Collection
                                    if($collection = $page->isCollection()) {
                                        $collections[$route] = KObjectConfig::unbox($collection);
                                    }

                                    //Redirects
                                    if($page->redirect) {
                                        $redirects[$route] = $page->redirect;
                                    }
                                }
                                else
                                {
                                    //Iterate over path
                                    if($result = $iterate($file)) {
                                        $files[$path] = $result;
                                    }
                                }
                            }
                        }
                    }

                    return $files;
                }
                else return false;
            };

            Closure::bind($iterate, $this, get_class());

            $result['files']       = $iterate($basedir);
            $result['pages']       = $pages;
            $result['routes']      = $routes;
            $result['collections'] = $collections;
            $result['redirects']   = array_flip($redirects);

            $this->storeCache($basedir, $result);

        }
        else
        {
            if (!$result = require($cache)) {
                throw new RuntimeException(sprintf('The page registry "%s" cannot be loaded from cache.', $cache));
            }
        }

        return $result;

    }

    public function storeCache($file, $data)
    {
        if($this->getConfig()->cache)
        {
            $path = $this->getConfig()->cache_path;

            if(!is_dir($path) && (false === @mkdir($path, 0755, true) && !is_dir($path))) {
                throw new RuntimeException(sprintf('The page registry cache path "%s" does not exist', $path));
            }

            if(!is_writable($path)) {
                throw new RuntimeException(sprintf('The page registry cache path "%s" is not writable', $path));
            }

            if(!is_string($data))
            {
                $result = '<?php /*//path:'.$file.'*/'."\n";
                $result .= 'return '.var_export($data, true).';';
            }

            $hash = crc32($file.PHP_VERSION);
            $file  = $this->getConfig()->cache_path.'/page_'.$hash.'.php';

            if(@file_put_contents($file, $result) === false) {
                throw new RuntimeException(sprintf('The page registry cannot be cached in "%s"', $file));
            }

            //Override default permissions for cache files
            @chmod($file, 0666 & ~umask());

            return $file;
        }

        return false;
    }

    public function isCached($file)
    {
        $result = false;

        if($this->getConfig()->cache)
        {
            $hash   = crc32($file.PHP_VERSION);
            $cache  = $this->getConfig()->cache_path.'/page_'.$hash.'.php';
            $result = is_file($cache) ? $cache : false;

            if($result && file_exists($file))
            {
                if((filemtime($cache) < filemtime($file)) || ((time() - filemtime($cache)) > $this->getConfig()->cache_time)) {
                    $result = false;
                }
            }
        }

        return $result;
    }
}