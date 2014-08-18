<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Database\Conventions;

use Nette\Database\IConventions;
use Nette\Database\IStructure;


/**
 * Conventions based on database structure.
 *
 * @author     Jan Skrasek
 */
class DiscoveredConventions implements IConventions
{
	/** @var IStructure */
	protected $structure;


	public function __construct(IStructure $structure)
	{
		$this->structure = $structure;
	}


	public function getPrimary($table)
	{
		return $this->structure->getPrimaryKey($table);
	}


	public function getHasManyReference($nsTable, $key)
	{
		$candidates = $columnCandidates = array();
		$targets = $this->structure->getHasManyReference($nsTable);
		$table = preg_replace('#^(.*\.)?(.*)$#', '$2', $nsTable);

		foreach ($targets as $targetNsTable => $targetColumns) {
			$targetTable = preg_replace('#^(.*\.)?(.*)$#', '$2', $targetNsTable);
			if (stripos($targetNsTable, $key) === FALSE) {
				continue;
			}

			foreach ($targetColumns as $targetColumn) {
				if (stripos($targetColumn, $table) !== FALSE) {
					$columnCandidates[] = $candidate = array($targetNsTable, $targetColumn);
					if (strcmp($targetTable, $key) === 0 || strcmp($targetNsTable, $key) === 0) {
						return $candidate;
					}
				}

				$candidates[] = array($targetTable, array($targetNsTable, $targetColumn));
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
			return NULL;
		}

		$this->structure->rebuild();
		return $this->getHasManyReference($nsTable, $key);
	}


	public function getBelongsToReference($table, $key)
	{
		$tableColumns = $this->structure->getBelongsToReference($table);

		foreach ($tableColumns as $column => $targetTable) {
			if (stripos($column, $key) !== FALSE) {
				return array($targetTable, $column);
			}
		}

		if ($this->structure->isRebuilt()) {
			return NULL;
		}

		$this->structure->rebuild();
		return $this->getBelongsToReference($table, $key);
	}

}
