<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\DatabaseDI;

use Nette;
use Nette\Schema\Expect;
use Tracy;
use function array_key_exists, is_array, is_string;


/**
 * Registers database Connection, Structure, and Explorer services in the DI container.
 */
class DatabaseExtension extends Nette\DI\CompilerExtension
{
	public function __construct(
		private readonly bool $debugMode = false,
	) {
	}


	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::arrayOf(
			Expect::structure([
				'dsn' => Expect::string()->required()->dynamic(),
				'user' => Expect::string()->nullable()->dynamic(),
				'username' => Expect::string()->nullable()->dynamic(),
				'password' => Expect::string()->nullable()->dynamic(),
				'options' => Expect::array(),
				'debugger' => Expect::bool(),
				'explain' => Expect::bool(true),
				'reflection' => Expect::string(), // BC
				'conventions' => Expect::string('discovered'), // Nette\Database\Conventions\DiscoveredConventions
				'autowired' => Expect::bool(),
				'mapping' => Expect::structure([
					'tables' => Expect::anyOf(
						Expect::string()->transform(fn(string $v) => ['*' => $v]),
						Expect::arrayOf('string', 'string'),
					)->default([]),
				]),
			]),
		)->before(fn($val) => is_array($val) && $val && !array_key_exists('dsn', $val)
			? $val // a set of named connections; a single connection always has the mandatory 'dsn' key
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
				assert($connection instanceof Nette\DI\Definitions\ServiceDefinition);
				$connection->addSetup(
					[Nette\Bridges\DatabaseTracy\ConnectionPanel::class, 'initialize'],
					[$connection, $this->debugMode, $name, !empty($config->explain)],
				);
			}
		}
	}


	private function setupDatabase(\stdClass $config, string $name): void
	{
		$builder = $this->getContainerBuilder();

		foreach ($config->options as $key => $value) {
			if (is_string($value) && preg_match('#^PDO(\\\\\w+)?::\w+$#Di', $value)) {
				$config->options[$key] = $value = $this->resolveConstant($value, "$name.options.$key");
			}

			if (preg_match('#^PDO(\\\\\w+)?::\w+$#Di', $key)) {
				unset($config->options[$key]);
				$config->options[$this->resolveConstant($key, "$name.options")] = $value;
			}
		}

		$connection = $builder->addDefinition($this->prefix("$name.connection"))
			->setCreator(
				Nette\Database\Connection::class,
				[$config->dsn, $config->username ?? $config->user, $config->password, $config->options],
			)
			->setAutowired($config->autowired);

		$structure = $builder->addDefinition($this->prefix("$name.structure"))
			->setCreator(Nette\Database\Structure::class)
			->setArguments([$connection])
			->setAutowired($config->autowired);

		if (!empty($config->reflection)) {
			$conventionsServiceName = 'reflection';
			$config->conventions = $config->reflection;
			if (strtolower($config->conventions) === 'conventional') {
				$config->conventions = 'Static';
			}
		} else {
			$conventionsServiceName = 'conventions';
		}

		$conventions = $config->conventions
			? $builder->addDefinition($this->prefix("$name.$conventionsServiceName"))
				->setCreator(preg_match('#^[a-z]+$#Di', $config->conventions)
					? 'Nette\Database\Conventions\\' . ucfirst($config->conventions) . 'Conventions'
					: $config->conventions)
				->setArguments(strtolower($config->conventions) === 'discovered' ? [$structure] : [])
				->setAutowired($config->autowired)
			: null;

		$entityMapping = $config->mapping?->tables
			? new Nette\DI\Definitions\Statement(Nette\Database\DefaultEntityMapping::class, [$config->mapping->tables])
			: null;

		$builder->addDefinition($this->prefix("$name.explorer"))
			->setCreator(Nette\Database\Explorer::class, [$connection, $structure, $conventions, null, $entityMapping])
			->setAutowired($config->autowired);

		$builder->addAlias($this->prefix("$name.context"), $this->prefix("$name.explorer"));

		if ($this->name === 'database') {
			$builder->addAlias($this->prefix($name), $this->prefix("$name.connection"));
			$builder->addAlias("nette.database.$name", $this->prefix($name));
			$builder->addAlias("nette.database.$name.context", $this->prefix("$name.explorer"));
		}
	}


	/** @throws Nette\DI\InvalidConfigurationException */
	private function resolveConstant(string $name, string $path): mixed
	{
		if (!defined($name)) {
			throw new Nette\DI\InvalidConfigurationException("Unknown constant '$name' in the '{$this->prefix($path)}' option.");
		}

		return constant($name);
	}
}
