<?php
namespace Exedron\Routeller;

use Exedra\Contracts\Routing\GroupHandler;
use Exedra\Exception\Exception;
use Exedra\Routing\Factory;
use Exedra\Routing\Route;
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

    public function validate($pattern, Route $route = null)
    {
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
    public function resolve(Factory $factory, $classname, Route $parentRoute = null)
    {
        $reflection = new \ReflectionClass($classname);

        if(!$reflection->isSubclassOf(Controller::class))
            throw new Exception('[' . $classname . '] must be a type of [' . Controller::class .']');

        /** @var Controller $controller */
        $controller = $classname::instance();

        $reader = $this->createReader();

        $group = $factory->createGroup(array(), $parentRoute);

        foreach($controller->getMiddlewares() as $middleware)
            $group->addMiddleware($middleware);

        $isRestful = $reflection->isSubclassOf(Restful::class);

        // loop all the class's methods
        foreach($reflection->getMethods() as $reflectionMethod)
        {
            $methodName = $reflectionMethod->getName();

            if(strpos($methodName, 'middleware') === 0)
            {
                $properties = $reader->getRouteProperties($reflectionMethod);

                $group->addMiddleware($reflectionMethod->getClosure($controller), isset($properties['name']) ? $properties['name'] : null);

                continue;
            }

            if(strpos($methodName, 'route') === 0)
            {
                $controller->{$methodName}($group);

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
                $properties['execute'] = $reflectionMethod->getClosure($controller);
            else  // else invoke the method to get the group handling pattern.
                $properties['subroutes'] = $controller->{$methodName}();
            
            if(isset($properties['name']))
                $properties['name'] = (string) $properties['name'];

            $group->addRoute($factory->createRoute($group, isset($properties['name']) ? $properties['name'] : $routeName, $properties));
        }

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
