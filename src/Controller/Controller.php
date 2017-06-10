<?php
namespace Exedron\Routeller\Controller;

abstract class Controller
{
    protected static $instances = array();

    protected $middlewares = array();

    protected function __construct()
    {
    }

    /**
     * @return static
     */
    public static function instance()
    {
        $classname = static::class;

        if(!isset(static::$instances[$classname]))
            static::$instances[$classname] = new static();

        return static::$instances[$classname];
    }

    /**
     * Add a controller based middleware
     * @param $middleware
     * @param array $properties
     * @return $this
     */
    protected function addMiddleware($middleware, array $properties = array())
    {
        $this->middlewares[] = array($middleware, $properties);

        return $this;
    }

    /**
     * Get all controller added middleware
     * @return array
     */
    public function allMiddlewares()
    {
        return $this->middlewares;
    }
}