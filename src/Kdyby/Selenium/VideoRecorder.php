<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Selenium;

use Nette;



/**
 * Records video of the session
 *
 * @author Jan Dolecek <juzna.cz@gmail.com>
 */
class VideoRecorder
{

	const OPTION_FFMPEG_PATH = 'ffpmeg';
	const OPTION_SCREEN_SIZE = 'screenSize';
	const OPTION_DISPLAY = 'display';
	const OPTION_QUALITY = 'quality';
	const OPTION_FORMAT = 'format';

	/** @var string Directory to store the videos */
	public $outPath;

	/** @var array */
	private $options = array(
		self::OPTION_FFMPEG_PATH => "ffmpeg",
		self::OPTION_SCREEN_SIZE => "1280x768",
		self::OPTION_DISPLAY => NULL, // will be detected from DISPLAY by default
		self::OPTION_QUALITY => 1, // between 1 (excellent quality) and 31 (worst quality)
		self::OPTION_FORMAT => 'avi', // file extension, codec is detected by ffmpeg
	);

	/** @var resource ffmpeg process */
	private $process;

	/** @var array pipes created for $process */
	private $pipes;



	public function __construct($dstDir, array $options = array())
	{
		$this->outPath = $dstDir . '/' . time() . '.' . $this->options[self::OPTION_FORMAT];
		$this->options = array_merge($this->options, array_filter($options));

		if ($this->options[self::OPTION_DISPLAY] === NULL) {
			$this->options[self::OPTION_DISPLAY] = getenv('DISPLAY');
		}
	}



	public function __destruct()
	{
		$this->stop();
	}



	public function start()
	{
		$spec = array(
			0 => array("pipe", "r"), // stdin is a pipe that the child will read from
			1 => array("pipe", "w"), // stdout is a pipe that the child will write to
			2 => array("pipe", "w"), // errors
		);
		$cmd = "{$this->options[self::OPTION_FFMPEG_PATH]} -f x11grab -r 24 -qscale {$this->options[self::OPTION_QUALITY]} -s {$this->options[self::OPTION_SCREEN_SIZE]} -i {$this->options[self::OPTION_DISPLAY]} $this->outPath";
		$this->process = proc_open($cmd, $spec, $this->pipes);

		usleep(1e4); // give him some time to boot up

		$status = proc_get_status($this->process);
		if (!$status['running']) {
			throw new \RuntimeException("Failed to start php server: " . stream_get_contents($this->pipes[2]));
		}

		register_shutdown_function(function() { // in case we crash
			$this->stop();
		});
	}



	public function stop()
	{
		if ( ! $this->process) return;

		$status = proc_get_status($this->process);
		if ($status['running']) {
			fclose($this->pipes[1]); //stdout
			fclose($this->pipes[2]); //stderr

			//get the parent pid of the process we want to kill
			$pPid = $status['pid'];

			//use ps to get all the children of this process, and kill them
			foreach (array_filter(preg_split('/\s+/', `ps -o pid --no-heading --ppid $pPid`)) as $pid) {
				if (is_numeric($pid)) {
					posix_kill($pid, 9); // SIGKILL signal
				}
			}
		}

		fclose($this->pipes[0]);
		proc_terminate($this->process);
		$this->process = NULL;
	}

}
