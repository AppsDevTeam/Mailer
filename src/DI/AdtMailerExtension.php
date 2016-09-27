<?php

namespace ADT\Mailer\DI;

use ADT\Mailer\Services;

class AdtMailerExtension extends \Nette\DI\CompilerExtension {

	const ERROR_MODE_SILENT = 'silent';
	const ERROR_MODE_EXCEPTION = 'exception';
	const ERROR_MODES = [ self::ERROR_MODE_SILENT, self::ERROR_MODE_EXCEPTION ];

	public function loadConfiguration() {
		$config = $this->validateConfig(
			[
				'remote' => [
					'api' => '',
					'key' => '',
				],
				'error' => [
					'mode' => static::ERROR_MODE_SILENT,
					'logDir' => '',
				],
				'autowireMailer' => FALSE,
			],
			$this->config
		);

		$this->getContainerBuilder()
			->addDefinition($this->prefix('api'))
			->setClass(Services\Api::class)
			->setArguments([ $config ]);

		$this->getContainerBuilder()
			->addDefinition($this->prefix('mailer'))
			->setClass(Services\Mailer::class)
			->setAutowired($config['autowireMailer']);
	}

	public function validateConfig(array $expected, array $config = NULL, $name = NULL) {
		$config = parent::validateConfig($expected, $config, $name);

		if (empty($config['remote']['api'])) {
			throw new \Nette\UnexpectedValueException('Specify remote API endpoint.');
		}

		if (empty($config['remote']['key'])) {
			throw new \Nette\UnexpectedValueException('Specify authentication key.');
		}

		if (!in_array($config['error']['mode'], static::ERROR_MODES, TRUE)) {
			throw new \Nette\UnexpectedValueException(
				'Error mode can be either "' . static::ERROR_MODE_SILENT . '" or "' . static::ERROR_MODE_EXCEPTION . '".'
			);
		}

		if ($config['error']['mode'] === static::ERROR_MODE_SILENT && empty($config['error']['logDir'])) {
			throw new \Nette\UnexpectedValueException('Specify mail log directory.');
		}

		return $config;
	}


}