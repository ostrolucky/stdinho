<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\PHP;

require_once __DIR__.'/../vendor/autoload.php';

$coverage = new CodeCoverage();

$filter = $coverage->filter();
$filter->addDirectoryToWhitelist(__DIR__.'/../src');
$filter->addFileToWhitelist(__DIR__.'/../bin/stdinho');

$coverage->start('process');
include_once __DIR__.'/../bin/stdinho';
$coverage->stop();

$i=1;
while (file_exists($fileName = __DIR__."/$i.coverage.cov")) {
    ++$i;
}
(new PHP())->process($coverage, $fileName);
