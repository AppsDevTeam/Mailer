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
	}

	protected function getSuppressionControlAddress(\Nette\Mail\Message $mail) {
		return $this->processCallableOption($this->config['suppressionControlAddress'], $mail);
	}

	protected function getRemoteKey(\Nette\Mail\Message $mail) {
		return $this->processCallableOption($this->config['remote']['key'], $mail);
	}

	protected function processCallableOption($value, \Nette\Mail\Message $mail) {
		if (is_callable($value, FALSE)) {
			return $value($mail);
		} else {
			return $value;
		}
	}

	/**
	 * @param \Nette\Mail\Message $mail
	 * @return array
	 */
	protected function serializeMessage(\Nette\Mail\Message $mail) {
		$result = [
			'from' => $mail->getFrom(),
			'subject' => $mail->getSubject(),
			'message' => $mail->generateMessage(),
			'suppressionControlAddress' => $this->getSuppressionControlAddress($mail),
		];

		foreach ([ 'to', 'cc', 'bcc' ] as $header) {
			$result[$header] = $mail->getHeader(ucfirst($header));
		}

		return $result;
	}

	public function send(\Nette\Mail\Message $mail) {
		$this->curl = curl_init();

		// set remote URL
		$endPoint = rtrim($this->config['remote']['api'], '/');
		curl_setopt(
			$this->curl,
			CURLOPT_URL,
			$endPoint . '/mail/send?key=' . $this->getRemoteKey($mail)
		);

		// do not wait more than 3s
		curl_setopt($this->curl, CURLOPT_TIMEOUT_MS, 3000);

		if (PHP_VERSION_ID >= 50600) {
			// follow redirects (throws 'CURLOPT_FOLLOWLOCATION cannot be activated when safe_mode is enabled or an open_basedir is set' on 5.5)
			curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, TRUE);
		}

		// disable cache, set content type, keep alive
		curl_setopt(
			$this->curl,
			CURLOPT_HTTPHEADER,
			[
				'Cache-Control: no-cache',
				'Content-Type: application/octet-stream',
				'Connection: Keep-Alive',
				'Keep-Alive: 300',
				'Expect:', // do not send Expect: 100-continue
			]
		);

		// do not display result
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);

		$postData = \Nette\Utils\Json::encode($this->serializeMessage($mail));

		// set message
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postData);

		// send message
		$response = curl_exec($this->curl);
		$httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

		try {
			$status = \Nette\Utils\Json::decode($response);

			// success check
			if (substr($httpCode, 0, 1) === '2' && $status->status === 'ok') {
				return;
			}
		} catch (\Nette\Utils\JsonException $e) {
			// error
		}

		$error = 'Could not transfer mail to remote server (' . (
			!empty($status)
				? $status->error
				: $httpCode . ' ' . curl_error($this->curl)
			) . ').';

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