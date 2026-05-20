<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;


/**
 * Resolves ActiveRow subclass for each table name using an explicit table map with wildcards.
 * @internal
 */
final class DefaultEntityMapping implements EntityMapping
{
	/** @var array<string, string> */
	private array $propertyCache = [];

	/** @var array<string, string> */
	private array $columnCache = [];


	/**
	 * @param array<string, class-string<Table\ActiveRow>|string> $tables table-to-class map; keys
	 *     may contain a single '*' wildcard (e.g. 'forum_*'), and a bare '*' acts as a catch-all
	 *     fallback. Class names may contain '*' which is replaced with PascalCase of the captured
	 *     portion (or the full table name for exact keys). Exact keys take precedence; wildcard
	 *     entries are tried in declaration order.
	 * @param bool $camelCase whether to convert snake_case column names to camelCase properties
	 */
	public function __construct(
		private readonly array $tables = [],
		private readonly bool $camelCase = false,
	) {
	}


	public function getClassName(string $table): ?string
	{
		if (isset($this->tables[$table])) {
			return $this->expandClass($this->tables[$table], $table);
		}

		foreach ($this->tables as $pattern => $class) {
			if (!str_contains($pattern, '*')) {
				continue;
			}
			$regex = '#^' . str_replace('\*', '(.*)', preg_quote($pattern, '#')) . '$#D';
			if (preg_match($regex, $table, $m)) {
				return $this->expandClass($class, $m[1]);
			}
		}

		return null;
	}


	/**
	 * Substitutes '*' in the class pattern with PascalCase of the captured name.
	 * @return class-string<Table\ActiveRow>
	 */
	private function expandClass(string $class, string $capture): string
	{
		/** @var class-string<Table\ActiveRow> $result */
		$result = str_contains($class, '*')
			? str_replace('*', self::toPascalCase($capture), $class)
			: $class;
		return $result;
	}


	/**
	 * With camelCase enabled, expects a snake_case column name (e.g. 'first_name')
	 * and returns its camelCase property form (e.g. 'firstName').
	 */
	public function getPropertyName(string $name): string
	{
		return $this->camelCase
			? $this->propertyCache[$name] ??= lcfirst(self::toPascalCase($name))
			: $name;
	}


	/**
	 * With camelCase enabled, expects a camelCase property name (e.g. 'firstName')
	 * and returns its snake_case column form (e.g. 'first_name'). PascalCase
	 * input would produce a leading underscore (e.g. 'FirstName' → '_first_name'),
	 * so the first letter is expected to be lowercase.
	 */
	public function getColumnName(string $name): string
	{
		return $this->camelCase
			? $this->columnCache[$name] ??= strtolower((string) preg_replace('#[A-Z]#', '_$0', $name))
			: $name;
	}


	private static function toPascalCase(string $name): string
	{
		$name = preg_replace('#^.*\.#', '', $name); // strip schema prefix
		return str_replace(' ', '', ucwords(strtr($name, '_', ' ')));
	}
}
