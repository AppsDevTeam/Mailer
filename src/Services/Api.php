<?php

namespace ADT\Mailer\Services;

use ADT\Mailer\DI\AdtMailerExtension;
use GuzzleHttp\Client;

class Api {

	/** @var array */
	protected $config;

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

		if ($mail->getHeader('Reply-To') !== null) {
			$result['reply-to'] = $mail->getHeader('Reply-To');
		}

		return $result;
	}

	/**
	 * @param \Nette\Mail\Message $mail
	 * @return bool
	 * @throws \Exception
	 */
	public function send(\Nette\Mail\Message $mail) {

		$postData = \GuzzleHttp\json_encode($this->serializeMessage($mail));
		$endPoint = rtrim($this->config['remote']['api'], '/');

		$client = new Client;

		try {
			$client->request("POST", $endPoint . '/mail/send?key=' . $this->getRemoteKey($mail), [
				'headers' => [
					'Cache-Control'=> 'no-cache',
					'Content-Type' => 'application/octet-stream',
					'Connection' => 'Keep-Alive',
					'Keep-Alive' => '300',
					'Expect' => NULL, // do not send Expect: 100-continue
				],
				"body" => $postData,
				"timeout" => 3,
			]);

			return TRUE;

		} catch (\Exception $e) {

			if ($this->config['error']['mode'] === AdtMailerExtension::ERROR_MODE_EXCEPTION) {
				throw $e;
			}

			$error = 'Could not transfer mail to remote server (' . $e->getMessage() . ').';

			// create log directory
			if (!file_exists($this->config['error']['logDir'])) {
				mkdir($this->config['error']['logDir'], 0777, TRUE);
			}

			// store mail
			$mailFile = (new \DateTime)->format('Y-m-d_H-i-s') . '_' . md5($postData);
			file_put_contents($this->config['error']['logDir'] . DIRECTORY_SEPARATOR . $mailFile, $postData);

			// send report
			$this->logger->log($error . PHP_EOL . PHP_EOL . $mailFile, \Tracy\ILogger::EXCEPTION);

			return FALSE;
		}
	}
}

class ApiException extends \Nette\IOException {
}
