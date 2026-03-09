<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\DatabaseDI;

use Nette;
use Nette\Schema\Expect;
use Tracy;
use function is_array, is_string;


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
				'password' => Expect::string()->nullable()->dynamic(),
				'options' => Expect::array(),
				'debugger' => Expect::bool(),
				'explain' => Expect::bool(true),
				'reflection' => Expect::string(), // BC
				'conventions' => Expect::string('discovered'), // Nette\Database\Conventions\DiscoveredConventions
				'mapping' => Expect::structure([
					'convention' => Expect::string(''),
					'tables' => Expect::arrayOf('string', 'string'),
				])->before(fn($v) => is_string($v) ? ['convention' => $v] : $v),
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

		$rowMapping = ($config->mapping->convention || $config->mapping->tables)
			? new Nette\DI\Definitions\Statement([self::class, 'createRowMapping'], [
				$config->mapping->convention,
				(array) $config->mapping->tables,
			])
			: null;

		$builder->addDefinition($this->prefix("$name.explorer"))
			->setFactory(Nette\Database\Explorer::class, [$connection, $structure, $conventions, null, $rowMapping])
			->setAutowired($config->autowired);

		$builder->addAlias($this->prefix("$name.context"), $this->prefix("$name.explorer"));

		if ($this->name === 'database') {
			$builder->addAlias($this->prefix($name), $this->prefix("$name.connection"));
			$builder->addAlias("nette.database.$name", $this->prefix($name));
			$builder->addAlias("nette.database.$name.context", $this->prefix("$name.explorer"));
		}
	}


	/**
	 * Creates a row mapping closure that resolves an ActiveRow subclass for each table name.
	 * @param array<string, string> $tables
	 * @return \Closure(string): string
	 */
	public static function createRowMapping(string $convention, array $tables): \Closure
	{
		return static function (string $table) use ($convention, $tables): string {
			if (isset($tables[$table])) {
				return $tables[$table];
			}

			if ($convention !== '') {
				$class = str_replace('*', str_replace(' ', '', ucwords(strtr($table, '_', ' '))), $convention);
				if (class_exists($class)) {
					return $class;
				}
			}

			return Nette\Database\Table\ActiveRow::class;
		};
	}
}
