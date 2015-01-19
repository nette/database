<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Bridges\DatabaseDI;

use Nette;


/**
 * Nette Framework Database services.
 *
 * @author     David Grudl
 * @author     Jan Skrasek
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


	public function loadConfiguration()
	{
		$configs = $this->compiler->getConfig();
		if (isset($configs['nette']['database'])) { // back compatibility
			$configs = $configs['nette']['database'];
			$prefix = 'nette.';
		} else {
			$configs = isset($configs[$this->name]) ? $configs[$this->name] : array();
			$prefix = '';
		}

		if (isset($configs['dsn'])) {
			$configs = array('default' => $configs);
		}

		$autowired = TRUE;
		foreach ((array) $configs as $name => $config) {
			if (!is_array($config)) {
				continue;
			}
			$this->validate($config, $this->databaseDefaults, 'database');

			$config += array('autowired' => $autowired) + $this->databaseDefaults;
			$autowired = FALSE;
			$this->setupDatabase($config, $name, $prefix);
		}
	}


	private function setupDatabase($config, $name, $prefix)
	{
		$container = $this->getContainerBuilder();

		foreach ((array) $config['options'] as $key => $value) {
			if (preg_match('#^PDO::\w+\z#', $key)) {
				unset($config['options'][$key]);
				$config['options'][constant($key)] = $value;
			}
		}

		$hasBlueScreenService = $container->hasDefinition('nette.blueScreen');
		$connection = $container->addDefinition($prefix . $this->prefix($name))
			->setClass('Nette\Database\Connection', array($config['dsn'], $config['user'], $config['password'], $config['options']))
			->setAutowired($config['autowired'])
			->addSetup($hasBlueScreenService ? '@nette.blueScreen::addPanel' : 'Tracy\Debugger::getBlueScreen()->addPanel(?)', array(
				'Nette\Bridges\DatabaseTracy\ConnectionPanel::renderException'
			));

		$structure = $container->addDefinition($prefix . $this->prefix("$name.structure"))
			->setClass('Nette\Database\Structure')
			->setArguments(array($connection));

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
			$conventions = $container->addDefinition($prefix . $this->prefix("$name.$conventionsServiceName"))
				->setClass(preg_match('#^[a-z]+\z#', $config['conventions'])
					? 'Nette\Database\Conventions\\' . ucfirst($config['conventions']) . 'Conventions'
					: $config['conventions'])
				->setArguments(strtolower($config['conventions']) === 'discovered' ? array($structure) : array())
				->setAutowired($config['autowired']);

		} else {
			$tmp = Nette\DI\Compiler::filterArguments(array($config['conventions']));
			$conventions = reset($tmp);
		}

		$container->addDefinition($prefix . $this->prefix("$name.context"))
			->setClass('Nette\Database\Context', array($connection, $structure, $conventions))
			->setAutowired($config['autowired']);

		if ($container->parameters['debugMode'] && $config['debugger']) {
			$connection->addSetup('Nette\Database\Helpers::createDebugPanel', array($connection, !empty($config['explain']), $name));
		}
	}


	private function validate(array $config, array $expected, $name)
	{
		if ($extra = array_diff_key($config, $expected)) {
			$extra = implode(", $name.", array_keys($extra));
			throw new Nette\InvalidStateException("Unknown option $name.$extra.");
		}
	}

}
