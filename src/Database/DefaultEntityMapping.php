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
	 * @param array<string, string> $except explicit property-to-column pairs (e.g. ['address2' => 'address_2'])
	 *     that take precedence over the camelCase conversion in both directions; the escape hatch
	 *     for columns whose name has no camelCase form
	 */
	public function __construct(
		/**
		 * @var array<string, class-string<Table\ActiveRow>|string> table-to-class map; keys
		 * may contain a single '*' wildcard (e.g. 'forum_*'), and a bare '*' acts as a catch-all
		 * fallback. Class names may contain '*' which is replaced with PascalCase of the captured
		 * portion (or the full table name for exact keys). Exact keys take precedence; wildcard
		 * entries are tried in declaration order.
		 */
		private readonly array $tables = [],
		/** whether to convert snake_case column names to camelCase properties */
		private readonly bool $camelCase = false,
		array $except = [],
	) {
		foreach ($except as $property => $column) {
			$this->propertyCache[$column] = $property;
			$this->columnCache[$property] = $column;
		}
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
	 * and returns its camelCase property form (e.g. 'firstName'). Pairs from the
	 * except map take precedence.
	 */
	public function getPropertyName(string $name): string
	{
		return $this->propertyCache[$name] ??= $this->camelCase ? lcfirst(self::toPascalCase($name)) : $name;
	}


	/**
	 * With camelCase enabled, expects a camelCase property name (e.g. 'firstName')
	 * and returns its snake_case column form (e.g. 'first_name'). PascalCase
	 * input would produce a leading underscore (e.g. 'FirstName' → '_first_name'),
	 * so the first letter is expected to be lowercase. Digits have no letter case,
	 * so an underscore directly before a digit ('address_2') has no camelCase form;
	 * digit runs attach to the preceding word ('sha256_hash' → 'sha256Hash') and
	 * columns like 'address_2' must go through the except map. Pairs from the
	 * except map take precedence.
	 */
	public function getColumnName(string $name): string
	{
		return $this->columnCache[$name] ??= $this->camelCase ? strtolower((string) preg_replace('#[A-Z]#', '_$0', $name)) : $name;
	}


	private static function toPascalCase(string $name): string
	{
		$name = preg_replace('#^.*\.#', '', $name); // strip schema prefix
		return str_replace(' ', '', ucwords(strtr($name, '_', ' ')));
	}
}
