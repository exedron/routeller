<?php
namespace Exedron\Routeller\Reflection;

class Reflection
{
    protected $reflection;

    public function __construct($routing)
    {
        $this->reflection = new \ReflectionClass($routing);

        $this->reader = \Minime\Annotations\Reader::createFromDefaults();
    }

    /**
     * @return \ReflectionMethod[]
     */
    public function getMethods()
    {
        return $this->reflection->getMethods();
    }

    public function getAnnotationReader()
    {
        return $this->reader;
    }
}