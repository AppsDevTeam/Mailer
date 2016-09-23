<?php

namespace ADT\Mailer\Services;

use Nette\Mail\Message;
use Nette\Mail\SendException;


class Mailer implements \Nette\Mail\IMailer {

	protected $apiService;

	public function __construct(Api $api) {
		$this->apiService = $api;
	}

	function send(Message $mail) {
		$this->apiService->send($mail);
	}

}