<?php
namespace Exedron\Routeller\Controller;

use Exedra\Routing\Group;

abstract class Controller
{
    protected $isRestful = false;

    public function isRestful()
    {
        return $this->isRestful;
    }

    public function setUp(Group $group)
    {
    }
}