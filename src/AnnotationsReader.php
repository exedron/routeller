<?php
namespace Exedron\Routeller;

use Exedra\Support\DotArray;
use Minime\Annotations\Cache\ArrayCache;
use Minime\Annotations\Reader;

class AnnotationsReader extends Reader
{
    protected static $exceptions = array(
        'return' => 1,
        'param' => 1,
        'throws' => 1
    );

    /**
     * @param \Reflector $Reflection
     * @return array
     */
    public function getRouteProperties(\Reflector $Reflection)
    {
        $doc = $Reflection->getDocComment();
        if ($this->cache) {
            $key = $this->cache->getKey($doc);
            $ast = $this->cache->get($key);
            if (! $ast) {
                $ast = $this->parser->parse($doc);
                $this->cache->set($key, $ast);
            }
        } else {
            $ast = $this->parser->parse($doc);
        }

        $properties = array();

        foreach($ast as $key => $value)
        {
            if(isset(static::$exceptions[$key]))
                continue;

            DotArray::set($properties, $key, $value);
        }

        return $properties;
    }
}