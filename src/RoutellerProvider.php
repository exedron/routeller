<?php
namespace Exedron\Routeller;

use Exedra\Application;
use Exedra\Provider\ProviderInterface;
use Exedron\Routeller\Cache\ArrayCache;
use Exedron\Routeller\Cache\CacheInterface;
use Exedron\Routeller\Cache\EmptyCache;
use Exedron\Routeller\Cache\FileCache;

class RoutellerProvider implements ProviderInterface
{
    protected $options;

    protected $cache;

    public function __construct(CacheInterface $cache = null, array $options = array())
    {
        $this->cache = $cache ? $cache : new EmptyCache();

        $this->options = $options;
    }

    public function register(Application $app)
    {
        $app->map->factory->addGroupHandler(new Handler($this->cache, $this->options));

        $app->map->addExecuteHandler('routeller_execute', ExecuteHandler::class);
    }
}