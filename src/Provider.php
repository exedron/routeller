<?php
namespace Exedron\Routeller;

use Exedra\Application;
use Exedra\Provider\ProviderInterface;

class Provider implements ProviderInterface
{
    public function register(Application $app)
    {
        $app->map->factory->addGroupHandler(new Handler());
    }
}