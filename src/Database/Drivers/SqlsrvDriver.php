<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette;


/**
 * Supplemental SQL Server 2005 and later database driver.
 */
class SqlsrvDriver implements Nette\Database\Driver
{
	use Nette\SmartObject;

	/** @var Nette\Database\Connection */
	private $connection;

	/** @var string */
	private $version;


	public function initialize(Nette\Database\Connection $connection, array $options): void
	{
		$this->connection = $connection;
		$this->version = $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
	}


	public function convertException(\PDOException $e): Nette\Database\DriverException
	{
		return Nette\Database\DriverException::from($e);
	}


	/********************* SQL ****************d*g**/


	public function delimite(string $name): string
	{
		/** @see https://msdn.microsoft.com/en-us/library/ms176027.aspx */
		return '[' . str_replace(']', ']]', $name) . ']';
	}


	public function formatDateTime(\DateTimeInterface $value): string
	{
		/** @see https://msdn.microsoft.com/en-us/library/ms187819.aspx */
		return $value->format("'Y-m-d\\TH:i:s'");
	}


	public function formatDateInterval(\DateInterval $value): string
	{
		throw new Nette\NotSupportedException;
	}


	public function formatLike(string $value, int $pos): string
	{
		/** @see https://msdn.microsoft.com/en-us/library/ms179859.aspx */
		$value = strtr($value, ["'" => "''", '%' => '[%]', '_' => '[_]', '[' => '[[]']);
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}


	public function applyLimit(string &$sql, ?int $limit, ?int $offset): void
	{
		if ($limit < 0 || $offset < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');

		} elseif (version_compare($this->version, '11', '<')) { // 11 == SQL Server 2012
			if ($offset) {
				throw new Nette\NotSupportedException('Offset is not supported by this database.');

			} elseif ($limit !== null) {
				$sql = preg_replace('#^\s*(SELECT(\s+DISTINCT|\s+ALL)?|UPDATE|DELETE)#i', '$0 TOP ' . $limit, $sql, 1, $count);
				if (!$count) {
					throw new Nette\InvalidArgumentException('SQL query must begin with SELECT, UPDATE or DELETE command.');
				}
			}
		} elseif ($limit !== null || $offset) {
			// requires ORDER BY, see https://technet.microsoft.com/en-us/library/gg699618(v=sql.110).aspx
			$sql .= ' OFFSET ' . (int) $offset . ' ROWS '
				. 'FETCH NEXT ' . (int) $limit . ' ROWS ONLY';
		}
	}


	/********************* reflection ****************d*g**/


	public function getTables(): array
	{
		$tables = [];
		foreach ($this->connection->query("
			SELECT
				name,
				CASE type
					WHEN 'U' THEN 0
					WHEN 'V' THEN 1
				END AS [view]
			FROM
				sys.objects
			WHERE
				type IN ('U', 'V')
		") as $row) {
			$tables[] = [
				'name' => $row->name,
				'view' => (bool) $row->view,
			];
		}

		return $tables;
	}


	public function getColumns(string $table): array
	{
		$columns = [];
		foreach ($this->connection->query("
			SELECT
				c.name AS name,
				o.name AS [table],
				UPPER(t.name) AS nativetype,
				NULL AS size,
				c.is_nullable AS nullable,
				OBJECT_DEFINITION(c.default_object_id) AS [default],
				c.is_identity AS autoincrement,
				CASE WHEN i.index_id IS NULL
					THEN 0
					ELSE 1
				END AS [primary]
			FROM
				sys.columns c
				JOIN sys.objects o ON c.object_id = o.object_id
				LEFT JOIN sys.types t ON c.user_type_id = t.user_type_id
				LEFT JOIN sys.key_constraints k ON o.object_id = k.parent_object_id AND k.type = 'PK'
				LEFT JOIN sys.index_columns i ON k.parent_object_id = i.object_id AND i.index_id = k.unique_index_id AND i.column_id = c.column_id
			WHERE
				o.type IN ('U', 'V')
				AND o.name = {$this->connection->quote($table)}
		") as $row) {
			$row = (array) $row;
			$row['vendor'] = $row;
			$row['nullable'] = (bool) $row['nullable'];
			$row['autoincrement'] = (bool) $row['autoincrement'];
			$row['primary'] = (bool) $row['primary'];

			$columns[] = $row;
		}

		return $columns;
	}


	public function getIndexes(string $table): array
	{
		$indexes = [];
		foreach ($this->connection->query("
			SELECT
				i.name AS name,
				CASE WHEN i.is_unique = 1 OR i.is_unique_constraint = 1
					THEN 1
					ELSE 0
				END AS [unique],
				i.is_primary_key AS [primary],
				c.name AS [column]
			FROM
				sys.indexes i
				JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
				JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
				JOIN sys.tables t ON i.object_id = t.object_id
			WHERE
				t.name = {$this->connection->quote($table)}
			ORDER BY
				i.index_id,
				ic.index_column_id
		") as $row) {
			$indexes[$row->name]['name'] = $row->name;
			$indexes[$row->name]['unique'] = (bool) $row->unique;
			$indexes[$row->name]['primary'] = (bool) $row->primary;
			$indexes[$row->name]['columns'][] = $row->column;
		}

		return array_values($indexes);
	}


	public function getForeignKeys(string $table): array
	{
		// Does't work with multicolumn foreign keys
		$keys = [];
		foreach ($this->connection->query("
			SELECT
				fk.name AS name,
				cl.name AS local,
				tf.name AS [table],
				cf.name AS [column]
			FROM
				sys.foreign_keys fk
				JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
				JOIN sys.tables tl ON fkc.parent_object_id = tl.object_id
				JOIN sys.columns cl ON fkc.parent_object_id = cl.object_id AND fkc.parent_column_id = cl.column_id
				JOIN sys.tables tf ON fkc.referenced_object_id = tf.object_id
				JOIN sys.columns cf ON fkc.referenced_object_id = cf.object_id AND fkc.referenced_column_id = cf.column_id
			WHERE
				tl.name = {$this->connection->quote($table)}
		") as $row) {
			$keys[$row->name] = (array) $row;
		}

		return array_values($keys);
	}


	public function getColumnTypes(\PDOStatement $statement): array
	{
		$types = [];
		$count = $statement->columnCount();
		for ($col = 0; $col < $count; $col++) {
			$meta = $statement->getColumnMeta($col);
			if (
				isset($meta['sqlsrv:decl_type'])
				&& $meta['sqlsrv:decl_type'] !== 'timestamp'
			) { // timestamp does not mean time in sqlsrv
				$types[$meta['name']] = Nette\Database\Helpers::detectType($meta['sqlsrv:decl_type']);
			} elseif (isset($meta['native_type'])) {
				$types[$meta['name']] = Nette\Database\Helpers::detectType($meta['native_type']);
			}
		}

		return $types;
	}


	public function isSupported(string $item): bool
	{
		return $item === self::SUPPORT_SUBSELECT;
	}
}
