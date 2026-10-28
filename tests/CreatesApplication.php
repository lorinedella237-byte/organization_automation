<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        if ($app->configurationIsCached()) {
            throw new \LogicException('Tests require uncached configuration. Run php artisan config:clear in this checkout first.');
        }

        $app->make(Kernel::class)->bootstrap();

        if (! $app->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('Default tests must use the isolated in-memory SQLite database.');
        }

        return $app;
    }
}
