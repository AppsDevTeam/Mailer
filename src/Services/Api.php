<?php

namespace ADT\Mailer\Services;

class Api {

	protected $config;

	public function __construct(array $config) {
		$this->config = $config;
	}

	public function send(\Nette\Mail\Message $mail) {
		// TODO
	}
}