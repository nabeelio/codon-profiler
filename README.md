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

Some settings (type and (default)):

* **showOutput** - bool (false)- supress output from the test runs
* **tareRuns** - bool (true) - There is some penalty for running a closure. This will average the time to run an empty closure, and subtract it from the average of all the runs (only from the total, not checkpoints or timers that are called within)
* **formatMemoryUsage** - bool (true) - Show the memory usage in a digestable format


## Using the Profiler

There are two ways you can use the profile:

1. Add tests using ```add()```
2. Profile code inline using the ```startTimer()/endTimer()```


### Using add()

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

### Using the profiler inline



# Examples

```php
<?php

# Generate some sample data
$data = [];
for($i = 1; $i <= 1000; $i++) {
	$data[] = rand(1, $i * $i);
}

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
Tests started at: 2012-08-15T12:05:37-04:00
Tests run: 2, iterations: 200
PHP Version: 5.4.5-1~dotdeb.0

Count in loop               (Iterations: 100)
---------
Timers:
              total:        0.000011045170



Count out of loop           (Iterations: 100)
---------
Timers:
              total:        0.000004445744
```

Here's an example using the memory usage and timers and checkpoints (same $data as above)

```php
<?php
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
```

Which will output:

```
Tests started at: 2012-08-15T12:12:45-04:00
Tests run: 2, iterations: 200
PHP Version: 5.4.5-1~dotdeb.0

Count in loop               (Iterations: 100)
---------
Timers:
          Loop only:        0.000020401478
              total:        0.000020751405

Checkpoints:
            halfway:        0.000860433578

Memory Usage:               (script, total)
      Start of loop:        654 KB       837 KB
            halfway:        656 KB       842 KB



Count out of loop           (Iterations: 100)
---------
Timers:
          Loop only:        0.000006358624
              total:        0.000006601262

Checkpoints:
            halfway:        0.000318896770

Memory Usage:               (script, total)
      Start of loop:        658 KB       837 KB
            halfway:        660 KB       842 KB
```

### Calling getResults
Additionally, calling ```getResults()``` will return an array of:

```
Array
(
    [<TEST_NAME>] => Array
    (
        [timers] => Array
        (
            [<TIMER_NAME>] => Array
                (
                    [start] => 1345047230.6797
                    [end] => 1345047230.6813
                    [total] => 1.6541504859924E-5
                )

            ...

        )

        [checkpoints] => Array
        (
            [<CHECKPOINTS] => 0.00094666719436646
            ...
        )

        [memory] => Array
        (
            [<NAME>] => Array
                (
                    [total] => 669742.48
                    [real] => 857210.88
                )
            ...

        )

        [iterations] => 100
    )
```

Example:

```
Array
(
    [Count in loop] => Array
    (
        [timers] => Array
        (
            [Loop only] => Array
                (
                    [start] => 1345047400.6883
                    [end] => 1345047400.6904
                    [total] => 2.0921230316162E-5
                )

            [total] => Array
                (
                    [start] => 1345047400.6883
                    [end] => 1345047400.6904
                    [total] => 2.1232867240906E-5
                )

        )

    [checkpoints] => Array
        (
            [halfway] => 0.00087648630142212
        )

    [memory] => Array
        (
            [Start of loop] => Array
                (
                    [total] => 669854.48
                    [real] => 857210.88
                )

            [halfway] => Array
                (
                    [total] => 671882.48
                    [real] => 862453.76
                )

        )

        [iterations] => 100
    )

    [Count out of loop] => Array
    (
        [timers] => Array
        (
            [Loop only] => Array
                (
                    [start] => 1345047400.7563
                    [end] => 1345047400.757
                    [total] => 7.5697898864746E-6
                )

            [total] => Array
                (
                    [start] => 1345047400.7562
                    [end] => 1345047400.757
                    [total] => 7.8718900680542E-6
                )

        )

    [checkpoints] => Array
        (
            [halfway] => 0.00033202171325684
        )

    [memory] => Array
        (
            [Start of loop] => Array
                (
                    [total] => 674298.08
                    [real] => 857210.88
                )

            [halfway] => Array
                (
                    [total] => 676372.48
                    [real] => 862453.76
                )

        )

        [iterations] => 100
    )
)
```