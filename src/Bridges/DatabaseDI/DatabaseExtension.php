<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\DatabaseDI;

use Nette;


/**
 * Nette Framework Database services.
 */
class DatabaseExtension extends Nette\DI\CompilerExtension
{
	public $databaseDefaults = [
		'dsn' => null,
		'user' => null,
		'password' => null,
		'options' => null,
		'debugger' => true,
		'explain' => true,
		'reflection' => null, // BC
		'conventions' => 'discovered', // Nette\Database\Conventions\DiscoveredConventions
		'autowired' => null,
	];

	/** @var bool */
	private $debugMode;


	public function __construct($debugMode = false)
	{
		$this->debugMode = $debugMode;
	}


	public function loadConfiguration()
	{
		$configs = $this->getConfig();
		foreach ($configs as $k => $v) {
			if (is_scalar($v)) {
				$configs = ['default' => $configs];
				break;
			}
		}

		$defaults = $this->databaseDefaults;
		$defaults['autowired'] = true;
		foreach ((array) $configs as $name => $config) {
			if (!is_array($config)) {
				continue;
			}
			$config = $this->validateConfig($defaults, $config, $this->prefix($name));
			$defaults['autowired'] = false;
			$this->setupDatabase($config, $name);
		}
	}


	private function setupDatabase($config, $name)
	{
		$builder = $this->getContainerBuilder();

		foreach ((array) $config['options'] as $key => $value) {
			if (is_string($value) && preg_match('#^PDO::\w+\z#', $value)) {
				$config['options'][$key] = $value = constant($value);
			}
			if (preg_match('#^PDO::\w+\z#', $key)) {
				unset($config['options'][$key]);
				$config['options'][constant($key)] = $value;
			}
		}

		$connection = $builder->addDefinition($this->prefix("$name.connection"))
			->setClass(Nette\Database\Connection::class, [$config['dsn'], $config['user'], $config['password'], $config['options']])
			->setAutowired($config['autowired']);

		$structure = $builder->addDefinition($this->prefix("$name.structure"))
			->setClass(Nette\Database\Structure::class)
			->setArguments([$connection])
			->setAutowired($config['autowired']);

		if (!empty($config['reflection'])) {
			$conventionsServiceName = 'reflection';
			$config['conventions'] = $config['reflection'];
			if (is_string($config['conventions']) && strtolower($config['conventions']) === 'conventional') {
				$config['conventions'] = 'Static';
			}
		} else {
			$conventionsServiceName = 'conventions';
		}

		if (!$config['conventions']) {
			$conventions = null;

		} elseif (is_string($config['conventions'])) {
			$conventions = $builder->addDefinition($this->prefix("$name.$conventionsServiceName"))
				->setClass(preg_match('#^[a-z]+\z#i', $config['conventions'])
					? 'Nette\Database\Conventions\\' . ucfirst($config['conventions']) . 'Conventions'
					: $config['conventions'])
				->setArguments(strtolower($config['conventions']) === 'discovered' ? [$structure] : [])
				->setAutowired($config['autowired']);

		} else {
			$class = method_exists(Nette\DI\Helpers::class, 'filterArguments') ? Nette\DI\Helpers::class : Nette\DI\Compiler::class;
			$conventions = $class::filterArguments([$config['conventions']])[0];
		}

		$builder->addDefinition($this->prefix("$name.context"))
			->setClass(Nette\Database\Context::class, [$connection, $structure, $conventions])
			->setAutowired($config['autowired']);

		if ($config['debugger']) {
			$connection->addSetup('@Tracy\BlueScreen::addPanel', [
				'Nette\Bridges\DatabaseTracy\ConnectionPanel::renderException',
			]);
			if ($this->debugMode) {
				$connection->addSetup('Nette\Database\Helpers::createDebugPanel', [$connection, !empty($config['explain']), $name]);
			}
		}

		if ($this->name === 'database') {
			$builder->addAlias($this->prefix($name), $this->prefix("$name.connection"));
			$builder->addAlias("nette.database.$name", $this->prefix($name));
			$builder->addAlias("nette.database.$name.context", $this->prefix("$name.context"));
		}
	}
}
