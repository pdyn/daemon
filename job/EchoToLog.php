<?php
/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @copyright 2010 onwards James McQuillan (http://pdyn.net)
 * @author James McQuillan <james@pdyn.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pdyn\daemon\job;

/**
 * Write to the log file on every daemon loop.
 */
class EchoToLog implements \pdyn\daemon\JobInterface {
	/** @var array The params originally passed to the daemon. */
	protected $params;

	/** @var \Psr\Log\LoggerInterface The daemon's logger */
	protected $logger;

	/** @var int Counts how many times the job has run, and tests the state system */
	protected $runcount = 0;

	/**
	 * Get details about the job.
	 *
	 * @return array Array of details for configuration display.
	 */
	public static function details() {
		return [
			'name' => 'Echo To Log',
			'description' => 'Writes a message to the log on every loop of the daemon. Can be useful to debug operation.',
		];
	}

	/**
	 * Returns the minimum amount of time, in minutes, between runs.
	 *
	 * @return int The number of minutes between runs.
	 */
	public static function get_interval() {
		return 0;
	}

	/**
	 * Constructor.
	 *
	 * @param array $resources Array of resources received from daemon.
	 * @param Psr\Log\LoggerInterface $logger A logger.
	 */
	public function __construct(array $resources, \Psr\Log\LoggerInterface $logger) {
		$this->resources = $resources;
		$this->logger = $logger;
	}

	/**
	 * Run the job.
	 */
	public function run() {
		$this->logger->info('ECHO ('.$this->runcount.')');
		$this->runcount++;
	}

	/**
	 * Sets the state of the object.
	 *
	 * @param array An array representing the state of the object.
	 */
	public function set_state($state) {
		if (is_array($state) && isset($state['runcount'])) {
			$this->runcount = (int)$state['runcount'];
		}
	}

	/**
	 * Returns the state for the job.
	 *
	 * Returns an array containing 'runcount'.
	 *
	 * @return array An array representing the state of the object.
	 */
	public function get_state() {
		return ['runcount' => $this->runcount];
	}
}
