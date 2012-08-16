<?php
/**
 * Codon PHP 5.4+ Profiler
 *
 * @author      Nabeel Shahzad <nshahzad@gmail.com>
 * @copyright   2012 Nabeel Shahzad
 * @link		http://nabeelio.com
 * @link        https://github.com/nshahzad/codon-profiler
 * @license     MIT
 * @package     Codon
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Codon;

class Profiler {

	/**
	 * Parameters that can be set through @see set()
	 */
	public $params = [
		'showOutput' => false,
		'tareRuns' => true,
		'formatMemoryUsage' => true
	];

	# Keep it all together
	protected $_tests = [];	# Hold all of the tests
	protected $_results = [];		# Hold all of the test results
	protected $_tare = 0;		# The tare value for a closure
	
	protected $_running = ''; 		# What test is running now (nr = now running)
	protected $_runtime = [		# Details about what what's running now
		'start_time' => 0,
		'end_time' => 0,
		'total_iterations' => 0
	];


	/**
	 * Initialize the benchmarking class
	 * @param array $params Pass in some initial settings
	 */
	public function __construct(array $params = []) {
		$this->params = array_merge($this->params, $params);
		$this->_runtime['start_time'] = time(); # if we only use checkpoints
	}


	/**
	 * Set options for this benchmarking class
	 * @param mixed $name Name of the setting from $this->params
	 * @param mixed $value Value of said setting
	 * @return Profiler
	 */
	public function set($name, $value) {

		if(is_array($name)) {
			foreach($name as $key => $value) {
				$this->params[$key] = $value;
			}
		} else {
			$this->params[$name] = $value;
		}

		return $this;
	}


	/**
	 * Add a benchmark to run; pass an array with the following syntax
	 * $test = [
	 *  'name' => 'Benchmark name',
	 *  'iterations' => # of times to run
	 *  'function' => closure of the test to run
	 * ]
	 *
	 * @param array $test An array with the benchmark to run
	 * @return Profiler
	 */
	public function add(array $test) {

		$this->_tests[$test['name']] = [
			'iterations' => (isset($test['iterations']) ? intval($test['iterations']) : 1),
			'function' => $test['function']
		];

		return $this;
	}


	/**
	 * Clear all previous data, including tests to run
	 * @return Profiler
	 */
	public function clearAll() {

		$this->_tests = [];
		$this->_results = [];

		return $this;
	}


	/**
	 * Run all of the tests and benchmarks
	 * @return Profiler
	 */
	public function run() {

		# Might be wrong thinking, but see how long it takes
		# to run an empty closure, and then subtract that time from
		# each run. Might not really be accurate on this assumption
		# But that's why you can disable it
		if ($this->params['tareRuns'] !== false) {

			$this->_tare = 0;
			$tare_func = function() { };

			for ($i = 0; $i < 100; $i++) {
				$start_time = microtime(true);
				$tare_func();
				$end_time = microtime(true);
				$this->_tare += ($end_time - $start_time);
			}

			$this->_tare = $this->_tare / 100;
		}

		foreach ($this->_tests as $name => $test) {

			# Figure out how many to run
			if (empty($test['iterations']))
				$test['iterations'] = 1;

			# Initialize any stats we use
			$this->_running = $name;
			$this->_runtime['start_time'] = time();
			$this->_runtime['total_iterations'] += $test['iterations'];
			$this->_results[$name] = [
				'timers' => [],
				'checkpoints' => [],
				'memory' => []
			];

			# Run the number of iterations specified
			for ($i = 0; $i < $test['iterations']; $i++) {
				# Start our timers and run the actual thing
				$this->startTimer('__total');
				$test['function']();
				$this->endTimer('__total');
			}

			$this->_runtime['end_time'] = time();

			# Average up the stats for all of the timers
			foreach ($this->_results[$name]['timers'] as $t_name => &$t_val) {
				$t_val['total'] = $t_val['total'] / $test['iterations'];
			}

			# Average the memory usage
			foreach ($this->_results[$name]['memory'] as $m_name => &$m_val) {
				$m_val['total'] = $m_val['total'] / $test['iterations'];
				$m_val['real'] = $m_val['real'] / $test['iterations'];
			}

			# Average the checkpoints
			foreach ($this->_results[$name]['checkpoints'] as $c_name => &$c_val) {
				$c_val = ($c_val / $test['iterations']);
			}

			# Place the total marker at the end
			$tmp = $this->_results[$name]['timers']['__total'];
			unset($this->_results[$name]['timers']['__total']);
			$this->_results[$name]['timers']['total'] = $tmp;

			# Average the marker run-time
			$this->_results[$name]['iterations'] = $test['iterations'];
		}

		return $this;
	}


	/**
	 * Start a timer and time it from within a closure
	 * @param string $name Name of the marker
	 * @return Profiler
	 */
	public function startTimer($name) {
		$this->_results[$this->_running]['timers'][$name]['start'] = microtime(true);

		if ($this->params['showOutput'] === false)
			ob_start();

		return $this;
	}


	/**
	 * End timing a marker with a given name
	 * @param string $n Name of the marker to end
	 * @return Profiler
	 */
	public function endTimer($n) {

		$r = $this->_running; # Just shorthand
		$this->_results[$r]['timers'][$n]['end'] = microtime(true);

		if (!isset($this->_results[$r][$n]['total'])) {
			$this->_results[$r]['timers'][$n]['total'] = 0;
		}

		# Get the total run time for this marker
		$this->_results[$r]['timers'][$n]['total'] += ($this->_results[$r]['timers'][$n]['end'] - $this->_results[$r]['timers'][$n]['start']);

		# Remove the tare'd amount if this is a total
		if ($n === '__total' && $this->params['tareRuns'] !== false) {
			$this->_results[$r]['timers'][$n]['total'] -= $this->_tare;
		}

		if ($this->params['showOutput'] === false)
			ob_end_clean();

		return $this;
	}


	/**
	 * Ad a checkpoint during a run, this shows up as time from the start
	 * @param $n
	 * @return Profiler
	 */
	public function checkpoint($n) {

		if(!isset($this->_results[$this->_running]['checkpoints'][$n])) {
			$this->_results[$this->_running]['checkpoints'][$n] = 0;
		}

		# Get the run-time from the start and add it to the total
		$delta = microtime(true) - $this->_results[$this->_running]['timers']['__total']['start'];
		$this->_results[$this->_running]['checkpoints'][$n] += $delta;

		return $this;
	}


	/**
	 * Get the memory usage at a certain point
	 * @param string $name Name of the point
	 * @return Profiler
	 */
	public function markMemoryUsage($name) {

		if(!isset($this->_results[$this->_running]['memory'][$name]['total'])) {
			$this->_results[$this->_running]['memory'][$name]['total'] = 0;
			$this->_results[$this->_running]['memory'][$name]['real'] = 0;
		}

		$this->_results[$this->_running]['memory'][$name]['total'] += memory_get_usage();
		$this->_results[$this->_running]['memory'][$name]['real'] += memory_get_usage(true);
		return $this;
	}


	/**
	 * Return the results of a run
	 * @return array
	 */
	public function getResults() {
		return $this->_results;
	}


	/**
	 * Show the results on the screen
	 * @param bool $html Enclose this in HTML tags?
	 * @param bool $return Whether to return the table, or output it
	 * @return Profiler|string
	 */
	public function showResults($html = false, $return = false) {

		$heading_fmt = "%-27s %s\n";
		$marker_fmt = "%20s  %20.12f\n";
		$checkpoint_fmt = "%20s  %20.12f\n";
		$memory_fmt = "%20s  %12s %12s %12s\n";

		$text = "Tests started at: " . date('c', $this->_runtime['start_time']) . "\n";
		$text .= "Tests run: " . count($this->_results) . ", ";
		$text .= "iterations: " . $this->_runtime['total_iterations'] . "\n";
		$text .= "PHP Version: " . phpversion() . "\n";

		foreach ($this->_results as $t_name => $res) {

			# Show test title
			$iterations = isset($res['iterations']) ? "(Iterations: {$res['iterations']})" : '';
			$text .= sprintf($heading_fmt, $t_name, "$iterations\n---------");

			# Show time usages
			$text .= sprintf($heading_fmt, "Timers:", '');
			foreach ($res['timers'] as $tmr_n => $tmr_d) {
				$text .= sprintf($marker_fmt, $tmr_n . ':', $tmr_d['total']);
			}

			$text .= "\n";

			# Show checkpoints
			if (isset($res['checkpoints']) && count($res['checkpoints']) > 0) {
				$text .= sprintf($heading_fmt, "Checkpoints:", '');
				foreach ($res['checkpoints'] as $cp_n => $cp_d) {
					$text .= sprintf($checkpoint_fmt, $cp_n . ':', $cp_d);
				}
				$text .= "\n";
			}

			# Show memory usages
			if (isset($res['memory']) && count($res['memory']) > 0) {
				$text .= sprintf($memory_fmt, "Memory Usage: ", "script", "peak", "total");
				foreach ($res['memory'] as $mem_pt_name => $usage) {
					$text .= sprintf(
						$memory_fmt,
						$mem_pt_name . ':',
						$this->formatMemory($usage['total']),
						$this->formatMemory(memory_get_peak_usage()),
						$this->formatMemory($usage['real'])
					);
				}
				$text .= "\n";
			}

			$text .= "\n\n";
		}

		if ($html === true) {
			$text = '<pre class="benchmarkResults">' . nl2br($text) . '</pre>';
		}

		if ($return === true) {
			return $text;
		}

		echo $text;

		return $this;
	}

	/**
	 * Format the memory used into a B/KB/MB format
	 * @param mixed $memory
	 * @return string
	 */
	public function formatMemory($memory) {

		if($this->params['formatMemoryUsage'] === false) {
			return $memory;
		}

		$memory = intval($memory);

		if ($memory < 1024)
			return $memory . "B";
		elseif ($memory < 1048576)
			return round($memory/1024)." KB";
		else
			return round($memory/1048576)." MB";
	}
}