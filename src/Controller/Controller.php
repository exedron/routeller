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
     * @return $this
     */
    public function addMiddleware($middleware)
    {
        $this->middlewares[] = $middleware;

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