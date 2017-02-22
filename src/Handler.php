<?php
namespace Exedron\Routeller;

use Exedra\Contracts\Routing\GroupHandler;
use Exedra\Exception\Exception;
use Exedra\Routing\Factory;
use Exedra\Routing\Route;
use Exedron\Routeller\Controller\Controller;
use Minime\Annotations\Cache\ArrayCache;

class Handler implements GroupHandler
{
    /**
     * @var array $httpVerbs
     */
    protected static $httpVerbs = array('get', 'post', 'put', 'patch', 'delete', 'options');

    public function validate($pattern, Route $route = null)
    {
        if((is_object($pattern) && $pattern instanceof Controller))
            return true;

        if(is_string($pattern) && class_exists($pattern))
            return true;

        return false;
    }

    protected function createReader()
    {
        return new AnnotationsReader(new AnnotationsParser(), new ArrayCache());
    }

    public function resolve(Factory $factory, $routing, Route $parentRoute = null)
    {
        if(is_string($routing))
        {
            $classname = $routing;

            $routing = new $routing;

            if(! ($routing instanceof Controller))
                throw new Exception('[' . $classname . '] must be a type of [' . Controller::class .']');
        }

        $reflection = new \ReflectionClass($routing);

        $reader = $this->createReader();

        $group = $factory->createGroup(array(), $parentRoute);

        /** @var Controller $routing */
        $routing->setUp($group);

        $isRestful = $routing->isRestful();

        foreach($reflection->getMethods() as $reflectionMethod)
        {
            $methodName = $reflectionMethod->getName();

            if(strpos($methodName, 'middleware') === 0)
            {
                $properties = $reader->getRouteProperties($reflectionMethod);

                $name = isset($properties['name']) ? $properties['name'] : null;

                $group->addMiddleware($reflectionMethod->getClosure($routing), $name);

                continue;
            }

            if(strpos($methodName, 'route') === 0)
            {
                $routing->{$methodName}($group);
                continue;
            }

            $type = null;

            $method = null;

            if(strpos($methodName, 'execute') === 0)
            {
                $type = 'execute';
                $methodName = strtolower(substr($methodName, 7, strlen($methodName)));
            }
            else if(strpos($methodName, 'group') === 0)
            {
                $type = 'subroutes';
                $methodName = strtolower(substr($methodName, 5, strlen($methodName)));
            }
            else if($isRestful)
            {
                foreach(static::$httpVerbs as $verb)
                {
                    if(strpos($methodName, $verb) === 0)
                    {
                        $type = 'execute';
                        $methodName = strtolower(substr($methodName, strlen($verb), strlen($methodName)));
                        $methodName = $methodName ? $verb . '-' . $methodName : $verb;
                        $method = $verb;

                        break;
                    }
                }

                if(!$type)
                    continue;
            }
            else
                continue;

            $properties = $reader->getRouteProperties($reflectionMethod);

            if($method)
                $properties['method'] = $method;

            if(count($properties) == 0)
                continue;

            if($type == 'execute')
                $properties['execute'] = $reflectionMethod->getClosure($routing);
            else
                $properties['subroutes'] = $reflectionMethod->invoke($routing);

            $name = isset($properties['name']) ? $properties['name'] : $methodName;

            $group->addRoute($factory->createRoute($group, $name, $properties));
        }

        return $group;
    }
}