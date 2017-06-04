<?php
namespace Exedron\Routeller;

use Exedra\Application;
use Exedra\Provider\ProviderInterface;
use Exedron\Routeller\Cache\ArrayCache;
use Exedron\Routeller\Cache\CacheInterface;
use Exedron\Routeller\Cache\EmptyCache;
use Exedron\Routeller\Cache\FileCache;

class Provider implements ProviderInterface
{
    protected $options;

    protected $cache;

    public function __construct(array $options = array(), CacheInterface $cache = null)
    {
        $this->cache = $cache ? $cache : new EmptyCache();

        $this->options = $options;
    }

    public function register(Application $app)
    {
        $app->map->factory->addGroupHandler(new Handler($this->options, $this->cache));

        $app->map->addExecuteHandler('routeller_execute', ExecuteHandler::class);
    }
}