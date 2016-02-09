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
 * A PHP Daemon.
 */
abstract class Daemon {
	/** @var string The location of the PID file to write the process ID of the forked process. */
	protected $pidfile = '';

	/** @var int The number of minutes to sleep between job runs. */
	protected $sleepmins = 5;

	/** @var array Array of state for jobs */
	protected $state = [];

	protected $resources = [];

	/**
	 * Constructor.
	 */
	public function __construct($daemondir, \Psr\Log\LoggerInterface $logger) {
		if (!file_exists($daemondir)) {
			mkdir($daemondir);
		}
		$this->pidfile = $daemondir.'/daemon.pid';
		$this->logger = $logger;
	}

	/**
	 * Perform any additional work you need before running tasks.
	 *
	 * @param array $params Array of parameters from the ->run() command.
	 */
	abstract protected function bootstrap(array $params);

	/**
	 * Return an array of jobs to process.
	 *
	 * @return array Array of jobs to process.
	 *                   Each item in the array should (at least) have keys:
	 *                   	job: A constructed instance of JobInstance.
	 *                   	lastrun: A timestamp of when the job was last run.
	 */
	abstract protected function get_jobs();

	/**
	 * Run when a job completes.
	 *
	 * @param array $job The array item from get_jobs that was completed. i.e. will include keys 'job', and 'lastrun'.
	 */
	abstract protected function job_finished($job);

	/**
	 * Run the daemon.
	 *
	 * This handles both the forking and running of the forked process.
	 *
	 * @param array $params An general-purpose array of data you might need.
	 */
	public function run(array $params = array()) {
		if ($this->is_running() === true) {
			$this->logger->warning('Daemon did not run, already running.');
			return;
		}

		$pid = $this->fork();
		if ($pid == -1) {
			$this->logger->error('Daemon could not fork.');
			return;
		} elseif ($pid) {
			// We are the parent.
			$this->logger->info('Daemon spawned at pid '.$pid);
			file_put_contents($this->pidfile, $pid);
			return;
		} else {
			declare(ticks = 1);
			// We are the child.
			$this->logger->info('Daemon Running.');
			if (posix_setsid() == -1) {
				$this->logger->error('Daemon could not detach.');
				die();
			}
			$this->register_sig_handler();
			$this->bootstrap($params);
			$this->runtasks();
		}
	}

	/**
	 * Stop the Daemon.
	 *
	 * @return bool Success/Failure.
	 */
	public function stop() {
		$this->logger->info('Stopping daemon...');
		if ($this->is_running() === false) {
			$this->logger->notice('Daemon already stopped.');
			return true;
		}
		$pid = file_get_contents($this->pidfile);
		$result = posix_kill(intval($pid), 15); //SIGTERM
		if ($result === true) {
			$this->logger->info('Stopped daemon.');
			if (file_exists($this->pidfile)) {
				@unlink($this->pidfile);
			}
		} else {
			$this->logger->critical('Could not stop daemon.');
		}
		return $result;
	}

	/**
	 * Determine if the daemon is running.
	 *
	 * @return bool Running/Not Running.
	 */
	public function is_running() {
		if (!file_exists($this->pidfile)) {
			return false;
		}
		$pid = file_get_contents($this->pidfile);
		if (empty($pid)) {
			return false;
		}

		if (!posix_kill(intval($pid), 0)) {
			// Not responding so unlink pidfile
			$this->logger->error('Daemon crashed');
			if (file_exists($this->pidfile)) {
				@unlink($this->pidfile);
			}
			return false;
		}

		return true;
	}

	/**
	 * Fork the process.
	 *
	 * @return int This returns one of three options:
	 *                 If we're the parent process, returns the process ID of the forked process.
	 *                 If we're the forked process, returns 0.
	 *                 If there was a problem forking the process, returns -1.
	 */
	protected function fork() {
		$pid = pcntl_fork();
		return $pid;
	}

	/**
	 * Run the tasks.
	 *
	 * This loops forever and runs each of the defined jobs.
	 *
	 * @param array $params An general-purpose array of data you might need.
	 */
	protected function runtasks() {
		gc_enable();
		$runcount = 0;
		while (1) {
			$this->logger->debug('Starting loop '.$runcount);
			$jobs = $this->get_jobs();
			foreach ($jobs as $job) {
				$runinterval = $job['job']->get_interval();
				$jobinfo = $job['job']->details();
				if ((time() - $job['lastrun']) > ($runinterval * 60)) {
					$this->logger->debug('Running '.$jobinfo['name'].' ('.get_class($job['job']).')...');
					$job['job']->run();
					$this->job_finished($job);
					$this->logger->debug('Finished '.$jobinfo['name']);
				} else {
					$this->logger->debug('Skipping '.$jobinfo['name'].' (too soon)...');
				}
			}
			$this->logger->debug('Completed loop '.$runcount);
			$runcount++;
			sleep(($this->sleepmins * 60));

			clearstatcache();
			gc_collect_cycles();
		}
	}

	/**
	 * Register the signal handler.
	 */
	protected function register_sig_handler() {
		pcntl_signal(SIGINT, [$this, 'sig_handler']);
		pcntl_signal(SIGTERM, [$this, 'sig_handler']);
		pcntl_signal(SIGQUIT, [$this, 'sig_handler']);
		pcntl_signal(SIGHUP, [$this, 'sig_handler']);
	}

	/**
	 * Handles received signals.
	 *
	 * @param int $signo The received signal.
	 */
	public function sig_handler($signo) {
		switch ($signo) {
			// handle shutdown tasks
			case SIGINT:
			case SIGTERM:
			case SIGKILL:
			case SIGQUIT:
				$this->logger->debug('Daemon shut down.');
				$this->shutdown();
				break;
			// handle restart tasks
			case SIGHUP:
				$this->logger->debug('Daemon wants to restart.');
				break;
			// handle all other signals
			default:
				$this->logger->debug('Daemon received unknown signal.');
		}
	}

	/**
	 * Shut down the daemon (from the forked process).
	 */
	public function shutdown() {
		if (file_exists($this->pidfile)) {
			@unlink($this->pidfile);
		}
		gc_disable();
		die();
	}
}
