<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\DatabaseDI;

use Nette;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Tracy;
use function is_array, is_string;


/**
 * Nette Framework Database services.
 */
class DatabaseExtension extends Nette\DI\CompilerExtension
{
	private bool $debugMode;


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
			]),
		)->before(fn($val) => is_array(reset($val)) || reset($val) === null
				? $val
				: ['default' => $val]);
	}


	public function loadConfiguration(): void
	{
		$autowired = true;
		foreach ($this->config as $name => $config) {
			$config->autowired ??= $autowired;
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
					[$connection, $this->debugMode, $name, !empty($config->explain)],
				);
			}
		}
	}


	private function setupDatabase(\stdClass $config, string $name): void
	{
		if (!empty($config->reflection)) {
			throw new Nette\DeprecatedException('The "reflection" option is deprecated, use "conventions" instead.');
		}

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

		$cacheId = 'Nette.Database.' . hash('xxh128', $name . $config->dsn);
		$cache = new Statement(Nette\Caching\Cache::class, [1 => $cacheId]);

		$explorer = $builder->addDefinition($this->prefix($name))
			->setFactory(Nette\Database\Explorer::class, [$config->dsn, $config->user, $config->password, $config->options])
			->addSetup('setCache', [$cache])
			->setAutowired($config->autowired);

		if (!$config->conventions || $config->conventions === 'discovered') {

		} elseif (is_string($config->conventions)) {
			$conventions = $builder->addDefinition($this->prefix("$name.conventions"))
				->setFactory(preg_match('#^[a-z]+$#Di', $config->conventions)
					? 'Nette\Database\Conventions\\' . ucfirst($config->conventions) . 'Conventions'
					: $config->conventions)
				->setAutowired($config->autowired);
			$explorer->addSetup('setConventions', [$conventions]);

		} else {
			$explorer->addSetup('setConventions', [Nette\DI\Helpers::filterArguments([$config->conventions])[0]]);
		}

		$builder->addAlias($this->prefix("$name.connection"), $this->prefix($name));
		$builder->addAlias($this->prefix("$name.context"), $this->prefix($name));
		$builder->addAlias($this->prefix("$name.explorer"), $this->prefix($name));

		if ($this->name === 'database') {
			$builder->addAlias("nette.database.$name", $this->prefix($name));
			$builder->addAlias("nette.database.$name.context", $this->prefix("$name.explorer"));
		}
	}
}
