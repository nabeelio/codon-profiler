<?php

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
			$this->_stats[$name] = ['markers' => [], 'memory' => []];

			# Run the number of iterations specified
			for ($i = 0; $i < $callee['iterations']; $i++) {
				# Start our timers and run the actual thing
				$this->startMarker('Total');
				$callee['function']();
				$this->endMarker('Total');
			}

			$this->_runtime['end_time'] = time();

			if ($this->params['showOutput'] === false)
				ob_end_clean();

			# Average up the stats for all of the markers
			foreach ($this->_stats[$name]['markers'] as $m_name => $m_val) {
				$average = $m_val['total'] / $callee['iterations'];
				$this->_stats[$name]['markers'][$m_name]['total'] = $average;
			}

			# @TODO Average memory usage numbers


			# @TODO: Calculate checkpoint time average

			# Average the marker run-time
			$this->_stats[$name]['iterations'] = $callee['iterations'];
		}

		return $this;
	}


	/**
	 * Start a marker and time it from within a closure
	 * @param string $name Name of the marker
	 * @return Profiler
	 */
	public function startMarker($name) {
		$this->_stats[$this->_runtime['name']]['markers'][$name]['start'] = microtime(true);
		return $this;
	}


	/**
	 * End timing a marker with a given name
	 * @param string $n Name of the marker to end
	 * @return Profiler
	 */
	public function endMarker($n) {

		$r = $this->_runtime['name']; # Just shorthand
		$this->_stats[$r]['markers'][$n]['end'] = microtime(true);

		if (!isset($this->_stats[$r][$n]['total'])) {
			$this->_stats[$r]['markers'][$n]['total'] = 0;
		}

		# Get the total run time for this marker
		$this->_stats[$r]['markers'][$n]['total'] += ($this->_stats[$r]['markers'][$n]['end'] - $this->_stats[$r]['markers'][$n]['start']);

		# Remove the tare'd amount if this is a total
		if ($n === 'Total' && $this->params['tareRuns'] !== false) {
			$this->_stats[$r]['markers'][$n]['total'] -= $this->_tare;
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
			$tmp = $this->_stats[$name]['markers']['Total'];
			unset($this->_stats[$name]['markers']['Total']);
			$this->_stats[$name]['markers']['Total'] = $tmp;

			# Show test title
			$text .= sprintf($heading_fmt, $name, 'Iterations: ' . $details['iterations']);

			# Show time usages
			foreach ($details['markers'] as $mkr_name => $mkr) {
				$text .= sprintf($marker_fmt, $mkr_name, $mkr['total']);
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