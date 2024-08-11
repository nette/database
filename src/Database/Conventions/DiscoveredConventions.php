<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Conventions;

use Nette\Database\Conventions;
use Nette\Database\IStructure;


/**
 * Conventions based on database structure.
 */
class DiscoveredConventions implements Conventions
{
	public function __construct(
		protected readonly IStructure $structure,
	) {
	}


	public function getPrimary(string $table): string|array|null
	{
		return $this->structure->getPrimaryKey($table);
	}


	public function getHasManyReference(string $nsTable, string $key): ?array
	{
		$candidates = $columnCandidates = [];
		$targets = $this->structure->getHasManyReference($nsTable);
		$table = preg_replace('#^(.*\.)?(.*)$#', '$2', $nsTable);

		foreach ($targets as $targetNsTable => $targetColumns) {
			$targetTable = preg_replace('#^(.*\.)?(.*)$#', '$2', $targetNsTable);
			if (stripos($targetNsTable, $key) === false) {
				continue;
			}

			foreach ($targetColumns as $targetColumn) {
				if (stripos($targetColumn, $table) !== false) {
					$columnCandidates[] = $candidate = [$targetNsTable, $targetColumn];
					if (strcmp($targetTable, $key) === 0 || strcmp($targetNsTable, $key) === 0) {
						return $candidate;
					}
				}

				$candidates[] = [$targetTable, [$targetNsTable, $targetColumn]];
			}
		}

		if (count($columnCandidates) === 1) {
			return $columnCandidates[0];
		} elseif (count($candidates) === 1) {
			return $candidates[0][1];
		}

		foreach ($candidates as $candidate) {
			if (strtolower($candidate[0]) === strtolower($key)) {
				return $candidate[1];
			}
		}

		if (!empty($candidates)) {
			throw new AmbiguousReferenceKeyException('Ambiguous joining column in related call.');
		}

		if ($this->structure->isRebuilt()) {
			return null;
		}

		$this->structure->rebuild();
		return $this->getHasManyReference($nsTable, $key);
	}


	public function getBelongsToReference(string $table, string $key): ?array
	{
		$tableColumns = $this->structure->getBelongsToReference($table);

		foreach ($tableColumns as $column => $targetTable) {
			if (stripos($column, $key) !== false) {
				return [$targetTable, $column];
			}
		}

		if ($this->structure->isRebuilt()) {
			return null;
		}

		$this->structure->rebuild();
		return $this->getBelongsToReference($table, $key);
	}
}
