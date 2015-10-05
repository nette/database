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
	public $databaseDefaults = array(
		'dsn' => NULL,
		'user' => NULL,
		'password' => NULL,
		'options' => NULL,
		'debugger' => TRUE,
		'explain' => TRUE,
		'reflection' => NULL, // BC
		'conventions' => 'discovered', // Nette\Database\Conventions\DiscoveredConventions
		'autowired' => NULL,
	);

	/** @var bool */
	private $debugMode;


	public function __construct($debugMode = FALSE)
	{
		$this->debugMode = $debugMode;
	}


	public function loadConfiguration()
	{
		$configs = $this->getConfig();
		if (isset($configs['dsn'])) {
			$configs = array('default' => $configs);
		}

		$defaults = $this->databaseDefaults;
		$defaults['autowired'] = TRUE;
		foreach ((array) $configs as $name => $config) {
			if (!is_array($config)) {
				continue;
			}
			$config = $this->validateConfig($defaults, $config, $this->prefix($name));
			$defaults['autowired'] = FALSE;
			$this->setupDatabase($config, $name);
		}
	}


	private function setupDatabase($config, $name)
	{
		$container = $this->getContainerBuilder();

		foreach ((array) $config['options'] as $key => $value) {
			if (preg_match('#^PDO::\w+\z#', $key)) {
				unset($config['options'][$key]);
				$config['options'][constant($key)] = $value;
			}
		}

		$connection = $container->addDefinition($this->prefix("$name.connection"))
			->setClass('Nette\Database\Connection', array($config['dsn'], $config['user'], $config['password'], $config['options']))
			->setAutowired($config['autowired']);

		$structure = $container->addDefinition($this->prefix("$name.structure"))
			->setClass('Nette\Database\Structure')
			->setArguments(array($connection))
			->setAutowired($config['autowired']);

		if (!empty($config['reflection'])) {
			$conventionsServiceName = 'reflection';
			$config['conventions'] = $config['reflection'];
			if (strtolower($config['conventions']) === 'conventional') {
				$config['conventions'] = 'Static';
			}
		} else {
			$conventionsServiceName = 'conventions';
		}

		if (!$config['conventions']) {
			$conventions = NULL;

		} elseif (is_string($config['conventions'])) {
			$conventions = $container->addDefinition($this->prefix("$name.$conventionsServiceName"))
				->setClass(preg_match('#^[a-z]+\z#i', $config['conventions'])
					? 'Nette\Database\Conventions\\' . ucfirst($config['conventions']) . 'Conventions'
					: $config['conventions'])
				->setArguments(strtolower($config['conventions']) === 'discovered' ? array($structure) : array())
				->setAutowired($config['autowired']);

		} else {
			$tmp = Nette\DI\Compiler::filterArguments(array($config['conventions']));
			$conventions = reset($tmp);
		}

		$container->addDefinition($this->prefix("$name.context"))
			->setClass('Nette\Database\Context', array($connection, $structure, $conventions))
			->setAutowired($config['autowired']);

		if ($config['debugger']) {
			$connection->addSetup('@Tracy\BlueScreen::addPanel', array(
				'Nette\Bridges\DatabaseTracy\ConnectionPanel::renderException'
			));
			if ($this->debugMode) {
				$connection->addSetup('Nette\Database\Helpers::createDebugPanel', array($connection, !empty($config['explain']), $name));
			}
		}

		if ($this->name === 'database') {
			$container->addAlias($this->prefix($name), $this->prefix("$name.connection"));
			$container->addAlias("nette.database.$name", $this->prefix($name));
			$container->addAlias("nette.database.$name.context", $this->prefix("$name.context"));
		}
	}

}
