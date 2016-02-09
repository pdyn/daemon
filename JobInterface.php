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

namespace pdyn\daemon;

/**
 * Interface for daemon jobs.
 */
interface JobInterface {
	/**
	 * Constructor.
	 *
	 * @param array $resources Array of resources received from daemon.
	 * @param \Psr\Log\LoggerInterface $logger A logger.
	 */
	public function __construct(array $resources, \Psr\Log\LoggerInterface $logger);

	/**
	 * Returns the minimum amount of time, in minutes, between runs.
	 *
	 * @return int The number of minutes between runs.
	 */
	public static function get_interval();

	/**
	 * Run the job.
	 */
	public function run();

	/**
	 * Set the state of the object.
	 *
	 * @param mixed $state A value from a get_state() call.
	 */
	public function set_state($state);

	/**
	 * Get the current state of the object.
	 *
	 * This is called and saved after every run of a job, and fed to set_state before the next run.
	 *
	 * @return mixed Whatever state you want to save.
	 */
	public function get_state();

	/**
	 * Get details about the job.
	 *
	 * @return array Array of details for configuration display.
	 *               Available details:
	 *                   name: The name of the job
	 *                   description: A description of what the job does.
	 */
	public static function details();
}
