<?php
namespace Exedron\Routeller;

use Exedra\Application;
use Exedra\Contracts\Routing\GroupHandler;
use Exedra\Exception\Exception;
use Exedra\Routing\Factory;
use Exedra\Routing\Route;
use Exedron\Routeller\Cache\CacheInterface;
use Exedron\Routeller\Cache\EmptyCache;
use Exedron\Routeller\Controller\Controller;
use Exedron\Routeller\Controller\Restful;
use Minime\Annotations\Cache\ArrayCache;

class Handler implements GroupHandler
{
    /**
     * @var array $httpVerbs
     */
    protected static $httpVerbs = array('get', 'post', 'put', 'patch', 'delete', 'options');

    /**
     * @var
     */
    protected $caches;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var array
     */
    protected $options;

    protected $isAutoReload;

    /**
     * @var Application
     */
    protected $app;

    public function __construct(Application $app, CacheInterface $cache = null, array $options = array())
    {
        $this->app = $app;

        $this->cache = $cache ? $cache : new EmptyCache;

        $this->options = $options;

        $this->isAutoReload = isset($this->options['auto_reload']) && $this->options['auto_reload'] === true ? true : false;
    }

    public function validate($pattern, Route $route = null)
    {
        if(is_string($pattern) && strpos($pattern, 'routeller=') === 0)
            return true;

        if(is_string($pattern) && class_exists($pattern))
            return true;

        if(is_object($pattern) && $pattern instanceof Controller)
            return true;

        return false;
    }

    protected function createReader()
    {
        return new AnnotationsReader(new AnnotationsParser(), new ArrayCache());
    }

    /**
     * @param Factory $factory
     * @param $routing
     * @param Route|null $parentRoute
     * @return \Exedra\Routing\Group
     * @throws Exception
     */
    public function resolve(Factory $factory, $controller, Route $parentRoute = null)
    {
        $group = $factory->createGroup(array(), $parentRoute);

        if(is_object($controller))
        {
            $classname = get_class($controller);
        }
        else
        {
            if(is_string($controller) && strpos($controller, 'routeller=') === 0)
            {
                list($classname, $method) = explode('@', str_replace('routeller=', '', $controller));

                $controller = $classname::instance()->{$method}($this->app);

                if(!$this->validate($controller))
                    throw new Exception('Unable to validate the routing group for [' . $classname . ':' . $method .'()]');

                return $this->resolve($factory, $controller, $parentRoute);
            }

            $classname = $controller;

            /** @var Controller $controller */
            $controller = $controller::instance();
        }

        $key = md5(get_class($controller));

        $entries = null;

        if($this->isAutoReload)
        {
            $reflection = new \ReflectionClass($controller);

            $lastModified = filemtime($reflection->getFileName());

            $cache = $this->cache->get($key);

            if($cache)
            {
                if($cache['last_modified'] != $lastModified)
                {
                    $this->cache->clear($key);
                }
                else
                {
                    $entries = $cache['entries'];
                }
            }
        }
        else
        {
            $cache = $this->cache->get($key);

            if($cache)
                $entries = $cache['entries'];
        }

        if($entries)
        {
            foreach($entries as $entry)
            {
                if(isset($entry['middleware']))
                {
                    $group->addMiddleware(function() use($controller, $entry) {
                        return call_user_func_array(array($controller, $entry['middleware']['handle']), func_get_args());
                    }, $entry['middleware']['properties']);
                }
                else if(isset($entry['route']))
                {
                    $properties = $entry['route']['properties'];

                    $group->addRoute($factory->createRoute($group, isset($properties['name']) ? $properties['name'] : $entry['route']['name'], $properties));
                }
                else if(isset($entry['setup']))
                {
                    $controller::instance()->{$entry['setup']['method']}($group);
                }
            }

            return $group;
        }

        if(!$this->isAutoReload)
            $reflection = new \ReflectionClass($controller);

        if(!$reflection->isSubclassOf(Controller::class))
            throw new Exception('[' . $classname . '] must be a type of [' . Controller::class .']');

        $reader = $this->createReader();

        $isRestful = true;

        $entries = array();

        // loop all the class's methods
        foreach($reflection->getMethods() as $reflectionMethod)
        {
            $methodName = $reflectionMethod->getName();

            if(strpos($methodName, 'middleware') === 0)
            {
                $properties = $reader->getRouteProperties($reflectionMethod);

                $entries[] = array(
                    'middleware' => array(
                        'properties' => $properties,
                        'handle' => $reflectionMethod->getName()
                    )
                );

                if(isset($properties['inject']))
                {
                    $properties['dependencies'] = $properties['inject'];
                    unset($properties['inject']);
                }

                if(isset($properties['dependencies']) && is_string($properties['dependencies']))
                    $properties['dependencies'] = array_map('trim', explode(',', trim($properties['dependencies'], ' []')));

                $group->addMiddleware($reflectionMethod->getClosure($controller), $properties);

                continue;
            }

            if(strpos($methodName, 'route') === 0 || strpos(strtolower($methodName), 'setup') === 0)
            {
                $controller->{$methodName}($group);

                $entries[] = array(
                    'setup' => array(
                        'method' => $methodName
                    )
                );

                continue;
            }

            $type = null;

            $method = null;

            if($routeName = $this->parseExecuteMethod($methodName))
            {
                $type = 'execute';
            }
            else if($routeName = $this->parseGroupMethod($methodName))
            {
                $type = 'subroutes';
            }
            else if($isRestful && $result = $this->parseRestfulMethod($methodName))
            {
                $type = 'execute';

                @list($routeName, $method) = $result;
            }
            else
            {
                continue;
            }

            $properties = $reader->getRouteProperties($reflectionMethod);

            if($method && !isset($properties['method']))
                $properties['method'] = $method;

            if(count($properties) == 0)
                continue;

            if($type == 'execute') // if it is, save the closure.
                $properties['execute'] = 'routeller=' . $classname .'@'. $reflectionMethod->getName();
            else  // else invoke the method to get the group handling pattern.
                $properties['subroutes'] = $controller->{$methodName}($this->app);
            
            if(isset($properties['name']))
                $properties['name'] = (string) $properties['name'];

            if(isset($properties['inject']) && is_string($properties['inject']))
                $properties['inject'] = array_map('trim', explode(',', trim($properties['inject'], ' []')));

            $group->addRoute($factory->createRoute($group, $routeName = (isset($properties['name']) ? $properties['name'] : $routeName), $properties));

            // cache purpose
            if(isset($properties['subroutes']))
                $properties['subroutes'] = 'routeller=' . $classname .'@'. $methodName;

            $entries[] = array(
                'route' => array(
                    'name' => $routeName,
                    'properties' => $properties
                )
            );
        }

        $this->cache->set($key, $entries, isset($lastModified) ? $lastModified : filemtime($reflection->getFileName()));

        return $group;
    }

    /**
     * get route name and the verb if it's prefixed with one of the http verbs.
     * @param $method
     * @return array|null
     */
    public function parseRestfulMethod($method)
    {
        foreach(static::$httpVerbs as $verb)
        {
            if(strpos($method, $verb) === 0)
            {
                $methodName = strtolower(substr($method, strlen($verb), strlen($method)));
                $routeName = $methodName ? $verb . '-' . $methodName : $verb;
                $method = $verb;

                return array($routeName, $method);
            }
        }

        return null;
    }

    /**
     * Get route name if it's prefixed with 'execute'
     * @return string|null
     */
    protected function parseExecuteMethod($method)
    {
        if(strpos($method, 'execute') !== 0)
            return null;

        return strtolower(substr($method, 7, strlen($method)));
    }

    /**
     * Get route name if it's prefixed with 'group'
     * @return string|null
     */
    protected function parseGroupMethod($method)
    {
        if(strpos($method, 'group') !== 0)
            return null;

        return strtolower(substr($method, 5, strlen($method)));
    }
}
