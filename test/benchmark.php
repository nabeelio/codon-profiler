#!/usr/bin/php
<?php
/**
 * Example of benchmarking distinct stuff
 */

ini_set('date.timezone', 'America/New_York');
include dirname(__FILE__) . '/../src/Codon/Profiler.php';

$data = [];
for($i = 0; $i < 1000; $i++) {
	$data[] = $i;
}

$profiler = new \Codon\Profiler();
$profiler
	->set('showOutput', false)
    ->add([
        'name' => 'Count in loop',
        'iterations' => 100,
        'function' => function() use ($data, &$profiler) {

			$profiler->markMemoryUsage('Start of loop');
			$profiler->startTimer('Loop only');
            for($i = 0; $i < count($data); $i++) {

				if($i === 500) {
					$profiler->checkpoint('halfway');
					$profiler->markMemoryUsage('halfway');
				}

                echo $data[$i] . "\n";
            }
			$profiler->endTimer('Loop only');
        }
    ])
    ->add([
        'name' => 'Count out of loop',
        'iterations' => 100,
        'function' => function() use ($data, &$profiler) {

			$profiler->markMemoryUsage('Start of loop');
			$count = count($data);

			$profiler->startTimer('Loop only');
            for($i = 0; $i < $count; $i++) {

				if($i === 500) {
					$profiler->checkpoint('halfway');
					$profiler->markMemoryUsage('halfway');
				}

                echo $data[$i] . "\n";
            }
			$profiler->endTimer('Loop only');

        }
    ])
	->run()
	->showResults();
