<?php
namespace Exedron\Routeller\Reflection;

use Exedron\Routeller\AnnotationsReader;

class Reflection
{
    protected $reflection;

    /**
     * Reflection constructor.
     * @param $routing
     */
    public function __construct($routing)
    {
        $this->reflection = new \ReflectionClass($routing);

        $this->reader = AnnotationsReader::create();
    }

    /**
     * @return \ReflectionMethod[]
     */
    public function getMethods()
    {
        return $this->reflection->getMethods();
    }

    /**
     * @return \Minime\Annotations\Interfaces\ReaderInterface|AnnotationsReader
     */
    public function getAnnotationReader()
    {
        return $this->reader;
    }
}