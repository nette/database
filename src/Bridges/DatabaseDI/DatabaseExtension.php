<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\DatabaseDI;

use Nette;
use Nette\Schema\Expect;
use Tracy;


/**
 * Nette Framework Database services.
 */
class DatabaseExtension extends Nette\DI\CompilerExtension
{
	/** @var bool */
	private $debugMode;


	public function __construct(bool $debugMode = false)
	{
		$this->debugMode = $debugMode;
	}


	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::arrayOf(
			Expect::structure([
				'dsn' => Expect::string()->required()->dynamic(),
				'user' => Expect::string()->nullable()->dynamic(),
				'password' => Expect::string()->nullable()->dynamic(),
				'options' => Expect::array(),
				'debugger' => Expect::bool(),
				'explain' => Expect::bool(true),
				'reflection' => Expect::string(), // BC
				'conventions' => Expect::string('discovered'), // Nette\Database\Conventions\DiscoveredConventions
				'autowired' => Expect::bool(),
			])
		)->before(function ($val) {
			return is_array(reset($val)) || reset($val) === null
				? $val
				: ['default' => $val];
		});
	}


	public function loadConfiguration(): void
	{
		$autowired = true;
		foreach ($this->config as $name => $config) {
			$config->autowired = $config->autowired ?? $autowired;
			$autowired = false;
			$this->setupDatabase($config, $name);
		}
	}


	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		foreach ($this->config as $name => $config) {
			if ($config->debugger ?? $builder->getByType(Tracy\BlueScreen::class)) {
				$connection = $builder->getDefinition($this->prefix("$name.connection"));
				$connection->addSetup(
					[Nette\Bridges\DatabaseTracy\ConnectionPanel::class, 'initialize'],
					[$connection, $this->debugMode, $name, !empty($config->explain)]
				);
			}
		}
	}


	private function setupDatabase(\stdClass $config, string $name): void
	{
		$builder = $this->getContainerBuilder();

		foreach ($config->options as $key => $value) {
			if (is_string($value) && preg_match('#^PDO::\w+$#D', $value)) {
				$config->options[$key] = $value = constant($value);
			}

			if (preg_match('#^PDO::\w+$#D', $key)) {
				unset($config->options[$key]);
				$config->options[constant($key)] = $value;
			}
		}

		$connection = $builder->addDefinition($this->prefix("$name.connection"))
			->setFactory(Nette\Database\Connection::class, [$config->dsn, $config->user, $config->password, $config->options])
			->setAutowired($config->autowired);

		$structure = $builder->addDefinition($this->prefix("$name.structure"))
			->setFactory(Nette\Database\Structure::class)
			->setArguments([$connection])
			->setAutowired($config->autowired);

		if (!empty($config->reflection)) {
			$conventionsServiceName = 'reflection';
			$config->conventions = $config->reflection;
			if (is_string($config->conventions) && strtolower($config->conventions) === 'conventional') {
				$config->conventions = 'Static';
			}
		} else {
			$conventionsServiceName = 'conventions';
		}

		if (!$config->conventions) {
			$conventions = null;

		} elseif (is_string($config->conventions)) {
			$conventions = $builder->addDefinition($this->prefix("$name.$conventionsServiceName"))
				->setFactory(preg_match('#^[a-z]+$#Di', $config->conventions)
					? 'Nette\Database\Conventions\\' . ucfirst($config->conventions) . 'Conventions'
					: $config->conventions)
				->setArguments(strtolower($config->conventions) === 'discovered' ? [$structure] : [])
				->setAutowired($config->autowired);

		} else {
			$conventions = Nette\DI\Helpers::filterArguments([$config->conventions])[0];
		}

		$builder->addDefinition($this->prefix("$name.explorer"))
			->setFactory(Nette\Database\Explorer::class, [$connection, $structure, $conventions])
			->setAutowired($config->autowired);

		$builder->addAlias($this->prefix("$name.context"), $this->prefix("$name.explorer"));

		if ($this->name === 'database') {
			$builder->addAlias($this->prefix($name), $this->prefix("$name.connection"));
			$builder->addAlias("nette.database.$name", $this->prefix($name));
			$builder->addAlias("nette.database.$name.context", $this->prefix("$name.explorer"));
		}
	}
}
