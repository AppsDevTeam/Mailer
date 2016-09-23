<?php

namespace ADT\Mailer\Services;

use ADT\Mailer\DI\AdtMailerExtension;

class Api {

	/** @var array */
	protected $config;

	/** @var resource */
	protected $curl;

	/** @var \Tracy\ILogger */
	protected $logger;

	public function __construct(array $config, \Tracy\ILogger $logger) {
		$this->logger = $logger;
		$this->config = $config;
		$this->curl = curl_init();

		// set remote URL
		$endPoint = rtrim($this->config['remote']['api'], '/');
		curl_setopt(
			$this->curl,
			CURLOPT_URL,
			$endPoint . '/mail/send?key=' . $this->config['remote']['key']
		);

		// do not wait more than 3s
		curl_setopt($this->curl, CURLOPT_TIMEOUT_MS, 3000);

		// disable cache, set content type, keep alive
		curl_setopt(
			$this->curl,
			CURLOPT_HTTPHEADER,
			array(
				'Cache-Control: no-cache',
				'Content-Type: application/octet-stream',
				'Connection: Keep-Alive',
				'Keep-Alive: 300',
			)
		);

		// do not display result
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
	}

	public function send(\Nette\Mail\Message $mail) {
		// TODO serialize message
		$postData = serialize($mail);

		// set message
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postData);

		// send message
		$response = curl_exec($this->curl);
		$info = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

		// success check
		if (false !== $response && substr($info, 0, 1) === '2') {
			return;
		}

		$error = 'Could not transfer mail to remote server (ERROR ' . $info . ').';
		if ($this->config['error']['mode'] === AdtMailerExtension::ERROR_MODE_EXCEPTION) {
			throw new ApiException($error);
		} else {
			// create log directory
			if (!file_exists($this->config['error']['logDir'])) {
				mkdir($this->config['error']['logDir'], 0777, TRUE);
			}

			// store mail
			$mailFile = (new \DateTime)->format('Y-m-d_H-i-s') . '_' . md5($postData);
			file_put_contents($this->config['error']['logDir'] . DIRECTORY_SEPARATOR . $mailFile, $postData);

			// send report
			$this->logger->log($error . PHP_EOL . PHP_EOL . $mailFile, \Tracy\ILogger::EXCEPTION);
		}
	}
}

class ApiException extends \Nette\IOException {
}