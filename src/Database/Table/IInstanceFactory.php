<?php

namespace Nette\Database\Table;

use Nette\Database\Context;
use Nette\Database\IConventions;
use Nette\Caching\IStorage;

interface IInstanceFactory
{
	public function createActiveRow($tableName, array $data, Selection $table);

	public function createSelection(Context $context, IConventions $conventions, $tableName, IStorage $cacheStorage = NULL);

	public function createGroupedSelection(Context $context, IConventions $conventions, $tableName, $column, Selection $refTable, IStorage $cacheStorage = NULL);
}
