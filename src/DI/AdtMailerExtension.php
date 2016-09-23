<?php

namespace ADT\Mailer\DI;

use ADT\Mailer\Services;

class AdtMailerExtension extends \Nette\DI\CompilerExtension {

	public function loadConfiguration() {
		$config = $this->validateConfig([
			'remote' => [
				'api' => TRUE,
				'key' => TRUE,
			],
			'error' => [
				'mode' => TRUE,
				'logDir' => TRUE,
			],
		]);

		$this->getContainerBuilder()
			->addDefinition($this->prefix('api'))
			->setClass(Services\Api::class)
			->setArguments($config);
	}


}