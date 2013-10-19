<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Selenium;

use Kdyby;
use Nette;
use Nette\Http\UrlScript;
use Symfony\Component\Process\PhpExecutableFinder;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class HttpServer
{

	/**
	 * @var array
	 */
	private $pipes = array();

	/**
	 * @var resource
	 */
	private $process;

	/**
	 * @var UrlScript
	 */
	private $url;

	/**
	 * @var array
	 */
	private static $spec = array(
		0 => array("pipe", "r"), // stdin is a pipe that the child will read from
		1 => array("pipe", "w"), // stdout is a pipe that the child will write to
		2 => array("pipe", "w"), // errors
	);



	public function __construct()
	{
		register_shutdown_function(function () {
			$this->slaughter();
		});
	}



	public function __destruct()
	{
		$this->slaughter();
	}



	/**
	 * @return UrlScript
	 */
	public function getUrl()
	{
		return clone $this->url;
	}



	/**
	 * @param string $router
	 * @param array $env
	 * @return UrlScript
	 * @throws \RuntimeException
	 */
	public function start($router, $env = array())
	{
		$this->slaughter();

		static $port;

		if ($port === NULL) {
			do {
				$port = rand(8000, 10000);
				if (isset($lock))
					@fclose($lock);
				$lock = fopen(dirname(TEMP_DIR) . '/http-server-' . $port . '.lock', 'w');
			} while (!flock($lock, LOCK_EX | LOCK_NB, $wouldBlock) || $wouldBlock);
		}

		$executable = new PhpExecutableFinder();
		$cmd = sprintf('%s -t %s -S %s:%d %s', escapeshellcmd($executable->find()), escapeshellarg(dirname($router)), $ip = '127.0.0.1', $port, escapeshellarg($router));
		if (!is_resource($this->process = proc_open($cmd, self::$spec, $this->pipes, dirname($router), $env + $_ENV))) {
			throw new HttpServerException("Could not execute: `$cmd`");
		}

		sleep(1); // give him some time to boot up

		$status = proc_get_status($this->process);
		if (!$status['running']) {
			throw new HttpServerException("Failed to start php server: " . stream_get_contents($this->pipes[2]));
		}

		$this->url = new UrlScript('http://' . $ip . ':' . $port);

		return $this->getUrl();
	}



	public function slaughter()
	{
		if (!is_resource($this->process)) {
			return;
		}

		$status = proc_get_status($this->process);
		if ($status['running'] == TRUE) {
			fclose($this->pipes[1]); //stdout
			fclose($this->pipes[2]); //stderr

			//get the parent pid of the process we want to kill
			$pPid = $status['pid'];

			//use ps to get all the children of this process, and kill them
			$cmd = PHP_OS === 'Darwin' ? "ps -o pid,ppid | grep -e ' $pPid$'" : "ps -o pid --no-heading --ppid $pPid";
			foreach (array_filter(preg_split('/\s+/', `$cmd`)) as $pid) {
				if (is_numeric($pid)) {
					posix_kill($pid, 9); // SIGKILL signal
				}
			}
		}

		fclose($this->pipes[0]);
		if ($status['running']) proc_terminate($this->process, 9); // SIGKILL
		proc_close($this->process);

		$this->process = NULL;
	}

}
