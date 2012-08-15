#!/usr/bin/php
<?php
/**
 * Example of inline profiling
 */

ini_set('date.timezone', 'America/New_York');

include dirname(__FILE__) . '/../src/Codon/Profiler.php';

$data = [];
for($i = 0; $i < 1000; $i++) {
	$data[] = $i;
}

$profiler = new \Codon\Profiler([
	'showOutput' => false
]);


$profiler->markMemoryUsage('start');
$profiler->startTimer('Count in loop');

for($i = 0; $i < count($data); $i++) {
	echo $data[$i] . "\n";
}

$profiler->endTimer('Count in loop');
$profiler->markMemoryUsage('end');


$profiler->showResults();