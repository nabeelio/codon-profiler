# Codon/Profiler

A PHP 5.4+ profiling class, loosely based on the PEAR Benchmark package.

* **Author:** Nabeel Shahzad <nshahzad@gmail.com>
* **Homepage:** http://nshahzad.github.com/codon-profiler/
* **Github:** https://github.com/nshahzad/codon-profiler
* **Packagist:** http://packagist.org/packages/codon/profiler
* **License:** MIT
* **Version:** 1.0

Current to-do list:

* Console colors to show the fastest test runs
* Show percentage differences between runs
* Use the Vulcan Logic Dumper (http://derickrethans.nl/projects.html#vld) to allow you to see opcode differences

# Installation

Add to your composer.json file:

```
"require": {
    "codon/profiler": "*"
}
```

Then run the composer update

```bash
./composer.phar update
```

# Basic Usage

This class extensively uses closures, and all of the methods (except for getResults) are chainable.
Declare a new Profiler class:

```php
<?php
$data = [/* ... */];
$profiler = new \Codon\Profiler();
$profiler->set('showOutput', false)
    ->add([
        'name' => 'Count in loop', 'iterations' => 100,
        'function' => function() use ($data, &$profiler) {
            // This is the code to benchmark:
            for($i = 0; $i <= count($data); $i++) {
                echo $data[$i] . "\n";
            }
        }
    ])->run()->showResults();
```

Which will output something like:

```
Tests started at: 2012-08-15T12:05:37-04:00
Tests run: 2, iterations: 200
PHP Version: 5.4.5-1~dotdeb.0

Count in loop               (Iterations: 100)
---------
Timers:
              total:        0.000011045170
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

1. Add tests using ```add()```; (see test/benchmark.php)
2. Profile code inline using the ```startTimer()/endTimer()```; (see test/inline.php)


### add()

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

# You can chain these too
$profiler->add($test1)->add($test2);
```

### run()

To run all of the tests, call ```run()```:

```php
<?php
$profiler->run();
```

#### Passing parameters

Your test function can also have any parameters, and any parameters passed to ```run()``` will be passed to the closure.
I.e, if you're measuring run-times of methods in a class:

```php
<?php
class Test {

    public $_data = [];

    protected $_profiler;

    public function __construct() {
        $this->_profiler = new \Codon\Profiler();
    }

    public function runMethodProfiler() {
        $this->_profiler->add([
            'name' => 'testFunction', 'iterations' => 1,
            'function' => function($class) use (&$this->_profiler) {
                $class->testFunction($class->_data);
            }
        ])->run($this);
    }

    public function testFunction($data = '') {

        // Benchmark something in here

    }
}
```

### clear()

Use the ```clear()``` function to clear the results of previous tests.

```
<?php
$profiler->add(/*...*/)->run();
// something
$profiler->clear()->run(); # Re-run the tests
```

### clearAll()

Use the ```clearAll()``` function to reset the profiler to having no tests or runs.

```
<?php
$profiler->add(/*...*/)->run();
$profiler->clearAll();
$profiler->add(/*...*/)->run();
```


### startTimer($name)/endTimer($name)

To measure points within a running benchmark. Can also be used inline (see below)

* **$name** Name of the timer

```php
<?php
$profiler
 ->add([ 'name' => 'Count in loop', 'iterations' => 1,
     'function' => function() use ($data, &$profiler) {

        $profiler->startTimer('Inside loop');
        for($i = 0; $i < count($data); $i++) {
            echo $data[$i] . "\n";
        }
        $profiler->endTimer('Inside loop');

        // Or this way:

        $timer_outside = $profiler->startTimer('Outside loop');
        $count = count($data);
        for($i = 0; $i < $count; $i++) {
            echo $data[$i] . "\n";
        }
        $profiler->endTimer($timer_outside);
     })->run();
```

### checkpoint($name)

Mark a checkpoint time from the start of the current test

* **$name** Checkpoint name

### markMemoryUsage($name)

This will take note of a memory usage at the point that this is called. Gets the usage by the script, PHP engine, and peak usage.

* **$name** Name of the current point

```php
<?php
$profiler
->clearAll()
 ->add([
     'name' => 'Count in loop',
     'iterations' => 100,
     'function' => function() use ($data, &$profiler) {

        $profiler->markMemoryUsage('Before loop');
        for($i = 0; $i < count($data); $i++) {
            echo $data[$i] . "\n";
        }
        $profiler->markMemoryUsage('After loop');

     })->run();
```

### showResults($html = false, $return = false)

Shows a formatted table

* **$html** If not false, will return <br /> instead of newlines, and put it in a <pre> tag with the class name of what's passed
* **$return** To return the results as a string, or just echo it out right away

```
Tests started at: 2012-08-15T12:40:28-04:00
Tests run: 1, iterations: 300
PHP Version: 5.4.5-1~dotdeb.0
Count in loop               (Iterations: 100)
---------
Timers:
        Inside loop:        0.000016908646
       Outside loop:        0.000004930496
              total:        0.000022240901
```


### getResults()

Returns an array with the raw results of a run

## Using the profiler inline

You can call the ```startTimer()/endTimer()``` and ```markMemoryUsage()``` functions in any inline code.

```php
<?php
$data = [];
for($i = 0; $i < 1000; $i++) {
	$data[] = $i;
}

# You can pass options to the constructor
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
```

Which shows:

```
Tests started at: 2012-08-15T12:30:02-04:00
Tests run: 1, iterations: 0
PHP Version: 5.4.5-1~dotdeb.0

---------
Timers:
      Count in loop:        0.001808881760

      Memory Usage:         script         peak        total
              start:        435 KB       456 KB       512 KB
                end:        438 KB       456 KB       512 KB
```

# Examples

## Measuring two ways of doing a for() loop:

```php
<?php
$profiler
	->clearAll()
    ->add([
        'name' => 'Count in loop',
        'iterations' => 100,
        'function' => function() use ($data, &$profiler) {

			$profiler->startTimer('Inside loop');
            for($i = 0; $i < count($data); $i++) {
                echo $data[$i] . "\n";
            }
			$profiler->endTimer('Inside loop');


			$profiler->startTimer('Outside loop');
			$count = count($data);
			for($i = 0; $i < $count; $i++) {
				echo $data[$i] . "\n";
			}
			$profiler->endTimer('Outside loop');
        }
    ])
	->run()
	->showResults();
```

Showing:

```
Tests started at: 2012-08-15T12:40:28-04:00
Tests run: 1, iterations: 300
PHP Version: 5.4.5-1~dotdeb.0
Count in loop               (Iterations: 100)
---------
Timers:
        Inside loop:        0.000016908646
       Outside loop:        0.000004930496
              total:        0.000022240901
```

## Using Timers and checkpoints

```php
<?php
$profiler = new \Codon\Profiler();
$profiler
	->set('showOutput', false)
    ->add([
        'name' => 'Count in loop',
        'iterations' => 100,
        'function' => function() use ($data, &$profiler) {

            // This is the code we are profiling

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
	->run()
	->showResults();
```

Which will output:

```
Tests started at: 2012-08-15T12:30:07-04:00
Tests run: 1, iterations: 100
PHP Version: 5.4.5-1~dotdeb.0

Count out of loop           (Iterations: 100)
---------
Timers:
          Loop only:        0.000006530285
              total:        0.000006922960

Checkpoints:
            halfway:        0.000361402035

      Memory Usage:         script         peak        total
      Start of loop:        472 KB       491 KB       512 KB
            halfway:        488 KB       491 KB       512 KB
```