<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\PHP;

require_once __DIR__.'/../vendor/autoload.php';

$filter = new Filter();
$filter->includeDirectory(__DIR__.'/../src');
$filter->includeFile(__DIR__.'/../bin/stdinho');

$coverage = new CodeCoverage((new Selector())->forLineAndPathCoverage($filter), $filter);
$coverage->start('process');
include_once __DIR__.'/../bin/stdinho';
$coverage->stop();

$i=1;
while (file_exists($fileName = __DIR__."/$i.coverage.cov")) {
    ++$i;
}
(new PHP())->process($coverage, $fileName);
