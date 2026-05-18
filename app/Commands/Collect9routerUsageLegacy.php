<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;

class Collect9routerUsageLegacy extends BaseCommand
{
    protected $group = '9router';
    protected $name = 'router:collect-usage';
    protected $description = 'Alias lama untuk router:push-usage.';

    public function run(array $params)
    {
        (new Collect9routerUsage())->run($params);
    }
}
