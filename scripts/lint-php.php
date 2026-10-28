<?php

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
$files = Finder::create()->files()->name('*.php')->in(array_map(
    static fn ($directory) => $root.'/'.$directory,
    ['app', 'bootstrap', 'config', 'database', 'routes', 'scripts', 'tests']
))->exclude('cache')->sortByName();
$checked = 0;
$failed = false;

foreach ($files as $file) {
    $process = new Process([PHP_BINARY, '-l', $file->getRealPath()]);
    $process->run();
    $checked++;
    if (! $process->isSuccessful()) {
        $failed = true;
        fwrite(STDERR, $process->getOutput().$process->getErrorOutput());
    }
}

echo "Checked {$checked} PHP files. ".($failed ? 'FAILED' : 'PASS').PHP_EOL;
exit($failed ? 1 : 0);
