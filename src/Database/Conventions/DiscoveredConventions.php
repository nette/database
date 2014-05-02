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


	public function getHasManyReference($table, $key)
	{
		$candidates = $columnCandidates = array();
		$targets = $this->structure->getHasManyReference($table);

		foreach ($targets as $targetTable => $targetColumns) {
			if (stripos($targetTable, $key) === FALSE) {
				continue;
			}

			foreach ($targetColumns as $targetColumn) {
				if (stripos($targetColumn, $table) !== FALSE) {
					$columnCandidates[] = $candidate = array($targetTable, $targetColumn);
					if (strtolower($targetTable) === strtolower($key)) {
						return $candidate;
					}
				}

				$candidates[] = array($targetTable, $targetColumn);
			}
		}

		if (count($columnCandidates) === 1) {
			return reset($columnCandidates);
		} elseif (count($candidates) === 1) {
			return reset($candidates);
		}

		foreach ($candidates as $candidate) {
			if (strtolower($candidate[0]) === strtolower($key)) {
				return $candidate;
			}
		}

		if (!empty($candidates)) {
			throw new AmbiguousReferenceKeyException('Ambiguous joining column in related call.');
		}

		if ($this->structure->isRebuilt()) {
			return NULL;
		}

		$this->structure->rebuild();
		return $this->getHasManyReference($table, $key);
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
