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
		'tareRuns' => true
	];

	# Keep it all together
	protected $_callees = [];
	protected $_stats = [];
	protected $_tare = 0;
	protected $_runtime = [
		'start_time' => 0,
		'end_time' => 0,
		'name' => ''
	];


	/**
	 * Initialize the benchmarking class
	 * @param array $params Pass in some initial settings
	 */
	public function __construct(array $params = []) {
		$this->params = array_merge($this->params, $params);
	}


	/**
	 * Set options for this benchmarking class
	 * @param string $name Name of the setting from $this->params
	 * @param mixed $value Value of said setting
	 * @return Profiler
	 */
	public function set($name, $value) {
		$this->params[$name] = $value;
		return $this;
	}


	/**
	 * Add a benchmark to run; pass an array with the following syntax
	 * $callee = [
	 *  'name' => 'Benchmark name',
	 *  'iterations' => # of times to run
	 *  'function' => closure of the test to run
	 * ]
	 *
	 * @param array $callee An array with the benchmark to run
	 * @return Profiler
	 */
	public function add(array $callee) {

		$this->_callees[$callee['name']] = [
			'iterations' => (isset($callee['iterations']) ? intval($callee['iterations']) : 1),
			'function' => $callee['function']
		];

		return $this;
	}


	/**
	 * Clear all previous data, including tests to run
	 * @return Profiler
	 */
	public function clearAll() {

		$this->_callees = [];
		$this->_stats = [];

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

		foreach ($this->_callees as $name => $callee) {

			# SILENCE! I KILL YOU!
			if ($this->params['showOutput'] === false)
				ob_start();

			# Figure out how many to run
			if (empty($callee['iterations']))
				$callee['iterations'] = 1;

			# Initialize any stats we use
			$this->_runtime['name'] = $name;
			$this->_runtime['start_time'] = time();
			$this->_stats[$name] = [
				'timers' => [],
				'checkpoints' => [],
				'memory' => []
			];

			# Run the number of iterations specified
			for ($i = 0; $i < $callee['iterations']; $i++) {
				# Start our timers and run the actual thing
				$this->startTimer('__total');
				$callee['function']();
				$this->endTimer('__total');
			}

			$this->_runtime['end_time'] = time();

			if ($this->params['showOutput'] === false)
				ob_end_clean();

			# Average up the stats for all of the timers
			foreach ($this->_stats[$name]['timers'] as $m_name => $m_val) {
				$average = $m_val['total'] / $callee['iterations'];
				$this->_stats[$name]['timers'][$m_name]['total'] = $average;
			}

			# @TODO Average memory usage numbers


			# @TODO: Calculate checkpoint time average

			# Average the marker run-time
			$this->_stats[$name]['iterations'] = $callee['iterations'];
		}

		return $this;
	}


	/**
	 * Start a timer and time it from within a closure
	 * @param string $name Name of the marker
	 * @return Profiler
	 */
	public function startTimer($name) {
		$this->_stats[$this->_runtime['name']]['timers'][$name]['start'] = microtime(true);
		return $this;
	}


	/**
	 * End timing a marker with a given name
	 * @param string $n Name of the marker to end
	 * @return Profiler
	 */
	public function endTimer($n) {

		$r = $this->_runtime['name']; # Just shorthand
		$this->_stats[$r]['timers'][$n]['end'] = microtime(true);

		if (!isset($this->_stats[$r][$n]['total'])) {
			$this->_stats[$r]['timers'][$n]['total'] = 0;
		}

		# Get the total run time for this marker
		$this->_stats[$r]['timers'][$n]['total'] += ($this->_stats[$r]['timers'][$n]['end'] - $this->_stats[$r]['timers'][$n]['start']);

		# Remove the tare'd amount if this is a total
		if ($n === '__total' && $this->params['tareRuns'] !== false) {
			$this->_stats[$r]['timers'][$n]['total'] -= $this->_tare;
		}

		return $this;
	}


	/**
	 * Ad a checkpoint during a run, this shows up as time from the start
	 * @param $n
	 * @return Profiler
	 */
	public function checkpoint($n) {

		if(!$this->_stats[$this->_runtime['name']]['checkpoint'][$n]) {
			$this->_stats[$this->_runtime['name']]['checkpoint'][$n] = 0;
		}

		$this->_stats[$this->_runtime['name']]['checkpoint'][$n] += microtime(true);
		return $this;
	}


	/**
	 * Get the memory usage at a certain point
	 * @param string $name Name of the point
	 * @return Profiler
	 */
	public function markMemoryUsage($name) {
		//@TODO: Aggregate and average
		$this->_stats[$this->_runtime['name']]['memory'][$name]['total'] = memory_get_usage();
		$this->_stats[$this->_runtime['name']]['memory'][$name]['real'] = memory_get_usage(true);
		return $this;
	}


	/**
	 * Return the results of a run
	 * @return array
	 */
	public function getResults() {
		return $this->_stats;
	}


	/**
	 * Show the results on the screen
	 * @param bool $html Enclose this in HTML tags?
	 * @param bool $return Whether to return the table, or output it
	 * @return Profiler|string
	 */
	public function showResults($html = false, $return = false) {

		$heading_fmt = "%20s  %20s  \n";
		$marker_fmt = "%19s  %20.12f\n";
		$memory_fmt = "%19s  %12d %10s\n";

		$text = "Tests started at: " . date('c', $this->_runtime['start_time']) . "\n";
		$text .= "PHP Version: " . phpversion() . "\n\n";

		foreach ($this->_stats as $name => $details) {

			# Place the total marker at the end
			$tmp = $this->_stats[$name]['timers']['__total'];
			unset($this->_stats[$name]['timers']['__total']);
			$this->_stats[$name]['timers']['Total'] = $tmp;

			# Show test title
			$text .= sprintf($heading_fmt, $name, 'Iterations: ' . $details['iterations']);

			# Show time usages
			foreach ($details['timers'] as $tmr_n => $tmr_d) {
				$text .= sprintf($marker_fmt, $tmr_n, $tmr_d['total']);
			}

			# Show checkpoints

			# Show memory usages
			if (count($details['memory']) > 0) {
				$text .= "\n" . sprintf($memory_fmt, "Memory Usage:", '');
				foreach ($details['memory'] as $mem_pt_name => $usage) {
					$text .= sprintf($memory_fmt, $mem_pt_name, $usage['total'], $usage['real']);
				}
			}

			$text .= "\n";
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
}