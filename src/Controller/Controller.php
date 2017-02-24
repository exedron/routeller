<?php
namespace Exedron\Routeller\Controller;

abstract class Controller
{
    protected $isRestful = false;

    protected static $instances = array();

    protected function __construct()
    {
    }

    public static function instance()
    {
        $classname = static::class;

        if(!isset(static::$instances[$classname]))
            static::$instances[$classname] = new static();

        return static::$instances[$classname];
    }

    public function isRestful()
    {
        return $this->isRestful;
    }
}