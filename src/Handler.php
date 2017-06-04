<?php
namespace Exedron\Routeller;

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

    protected $caches;

    /**
     * @var CacheInterface
     */
    protected $cache;

    protected $options;

    protected $isAutoReload;

    public function __construct(CacheInterface $cache = null, array $options = array())
    {
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

                $controller = $classname::instance()->{$method}();

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

        foreach($controller->getMiddlewares() as $middleware)
            $group->addMiddleware($middleware);

        if($entries)
        {
            foreach($entries as $entry)
            {
                if(isset($entry['middleware']))
                {
                    $group->addMiddleware(array($controller, $entry['middleware']['handle']), $entry['middleware']['name']);
                }
                else if(isset($entry['route']))
                {
                    $properties = $entry['route']['properties'];

                    $group->addRoute($factory->createRoute($group, isset($properties['name']) ? $properties['name'] : $entry['route']['name'], $properties));
                }
                else if(isset($entry['setup']))
                {
                    $controller::instance()->{$entry['method']}($group);
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
                $entries[] = array(
                    'middleware' => array(
                        'name' => isset($properties['name']) ? $properties['name'] : null,
                        'handle' => $reflectionMethod->getName()
                    )
                );

                $properties = $reader->getRouteProperties($reflectionMethod);

                $group->addMiddleware($reflectionMethod->getClosure($controller), isset($properties['name']) ? $properties['name'] : null);

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

            if($method)
                $properties['method'] = $method;

            if(count($properties) == 0)
                continue;

            if($type == 'execute') // if it is, save the closure.
                $properties['execute'] = 'routeller=' . $classname .'@'. $reflectionMethod->getName();
            else  // else invoke the method to get the group handling pattern.
                $properties['subroutes'] = $controller->{$methodName}();
            
            if(isset($properties['name']))
                $properties['name'] = (string) $properties['name'];

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
