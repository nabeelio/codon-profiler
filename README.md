# Codon/Profiler

A PHP 5.4+ profiling class, loosely based on the PEAR Benchmark package.

* **Author:** Nabeel Shahzad <nshahzad@gmail.com>
* **Homepage:** https://github.com/nshahzad/codon-profiler
* **License:** MIT

# Installation

Add to your composer.json file:

```json
"require": {
    "codon/profiler": "@dev"
}
```

# Usage

This class extensively uses closures, and all of the methods (except for getResults) are chainable.
Declare a new Profiler class:

```php
<?php
$profiler = new \Codon\Profiler();
```

## Settings

To change profiler settings, use the ```set()``` function:

```php
<?php
$profiler->set($name, $value);
```

Some settings:

* **showOutput** - bool (def: false)- supress output from the test runs
* **tareRuns** - bool (def: true) - There is some penalty for running a closure. This will average the time to run an empty closure, and subtract it from the average of all the runs (only from the total, not checkpoints or timers that are called within)


## Methods

To add a test, you use the ```add()``` method, which accepts an array of:

```php
<?php

$test = [
    'name' => 'Benchmark name',
    'iterations' => # of times to run
    'function' => closure of the test to run
];

$profiler->add($test);
```

This method can be chained

Tip: To use checkpoints/timers and other functionality within your test run, pass the Profiler object via ```use``` (as a reference). You can also pass any other data/variables needed for your tests via the ```use```

```php
<?php
$test = [
    'name' => 'Sample',
    'iterations' => 100
    'function' => function() use (&$profiler) {

        $profiler->checkpoint('Started!');

        // some code

        $profiler->startTimer('Subsection');
        // Subsection of code
        $profiler->endTimer('Subsection');
    }
];

$profiler->add($test);
```

To run all of the tests, call ```run()```:

```php
<?php
$profiler->run();
```

And to show the results:

```php
<?php
$profiler->showResults([bool $html = false (wrap PRE with BR's)], [bool $return = false (ouput directly?)]]
```

Example:

```php
<?php
$data = [ /* some data here */ ];
$profiler = new \Codon\Profiler();
$profiler
	->set('showOutput', false)
    ->add([
        'name' => 'Count in loop',
        'iterations' => 100,
        'function' => function() use ($data, &profiler) {
            for($i = 0; $i < count($data); $i++) {
                echo $data[$i] . "\n";
            }
        }
    ])
    ->add([
        'name' => 'Count out of loop',
        'iterations' => 100,
        'function' => function() use ($data, &profiler) {
			$count = count($data);
            for($i = 0; $i < $count; $i++) {
                echo $data[$i] . "\n";
            }
        }
    ])
	->run()
	->showResults();
```

This will output:

```
Tests started at: 2012-08-15T10:43:47-04:00
PHP Version: 5.4.5-1~dotdeb.0

       Count in loop       Iterations: 100
              Total        0.000012369728

   Count out of loop       Iterations: 100
              Total        0.000004649734
```

Additionally, calling ```getResults()``` will return an array of:

```
Array
(
    [Count in loop] => Array
        (
            [markers] => Array
                (
                    [Total] => Array
                        (
                            [start] => 1345041827.7588
                            [end] => 1345041827.7601
                            [total] => 1.2369728088379E-5
                        )

                )

            [memory] => Array
                (
                )

            [iterations] => 100
        )

    [Count out of loop] => Array
        (
            [markers] => Array
                (
                    [Total] => Array
                        (
                            [start] => 1345041827.8116
                            [end] => 1345041827.8121
                            [total] => 4.6497344970703E-6
                        )

                )

            [memory] => Array
                (
                )

            [iterations] => 100
        )

)

```