<?php
namespace Exedron\Routeller;

use Exedra\Contracts\Routing\GroupHandler;
use Exedra\Routing\Factory;
use Exedra\Routing\Route;
use Exedron\Routeller\Controller\Controller;
use Minime\Annotations\Cache\ArrayCache;

class Handler implements GroupHandler
{
    public function validate($pattern, Route $route = null)
    {
        if((is_object($pattern) && $pattern instanceof Controller))
            return true;

        return false;
    }

    public function resolve(Factory $factory, $routing, Route $parentRoute = null)
    {
        $reflection = new \ReflectionClass($routing);

        $reader = new AnnotationsReader(new AnnotationsParser(), new ArrayCache());

        $group = $factory->createGroup(array(), $parentRoute);

        /** @var Controller $routing */
        $routing->setUp($group);

        if($isRestful = $routing->isRestful())
        {
            $httpVerbs = array(
                'get' => 'get',
                'post' => 'post',
                'put' => 'put',
                'patch' => 'patch',
                'delete' => 'delete',
                'options' => 'options'
            );
        }

        foreach($reflection->getMethods() as $reflectionMethod)
        {
            $methodName = $reflectionMethod->getName();

            if(strpos($methodName, 'middleware') === 0)
            {
                $group->addMiddleware($reflectionMethod->getClosure($routing));
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
                foreach($httpVerbs as $verb)
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