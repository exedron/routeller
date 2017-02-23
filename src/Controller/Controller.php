<?php
namespace Exedron\Routeller\Controller;

use Exedra\Routing\Group;

abstract class Controller
{
    protected $isRestful = false;

    protected static $instance;

    protected function __construct()
    {
    }

    public static function instance()
    {
        if(!static::$instance)
            static::$instance = new static();

        return static::$instance;
    }

    public function isRestful()
    {
        return $this->isRestful;
    }
}