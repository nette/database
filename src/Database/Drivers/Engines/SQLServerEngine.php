<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\Engines;

use Nette;
use Nette\Database\Drivers\Connection;
use Nette\Database\Drivers\Engine;
use Nette\Database\TypeConverter;
use function array_values, str_replace;


/**
 * Microsoft SQL Server database platform.
 */
class SQLServerEngine implements Engine
{
	public function __construct(
		private readonly Connection $connection,
	) {
	}


	public function isSupported(string $feature): bool
	{
		return false;
	}


	public function classifyException(Nette\Database\DriverException $e): ?string
	{
		return null;
	}


	/********************* SQL ****************d*g**/


	public function delimit(string $name): string
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


	public function applyLimit(string $sql, ?int $limit, ?int $offset): string
	{
		if ($limit < 0 || $offset < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');

		} elseif ($limit !== null || $offset) {
			// requires ORDER BY, see https://technet.microsoft.com/en-us/library/gg699618(v=sql.110).aspx
			$sql .= ' OFFSET ' . (int) $offset . ' ROWS '
				. 'FETCH NEXT ' . (int) $limit . ' ROWS ONLY';
		}

		return $sql;
	}


	/********************* reflection ****************d*g**/


	public function getTables(): array
	{
		$tables = [];
		$rows = $this->connection->query(<<<'X'
			SELECT
				o.name,
				CASE o.type
					WHEN 'U' THEN 0
					WHEN 'V' THEN 1
				END AS [view],
				CAST(p.value AS VARCHAR(255)) AS comment
			FROM
				sys.objects o
			LEFT JOIN
				sys.extended_properties p ON p.major_id = o.object_id
				AND p.minor_id = 0
				AND p.name = 'MS_Description'
			WHERE
				o.type IN ('U', 'V')
			X);

		while ($row = $rows->fetch()) {
			$tables[] = [
				'name' => $row['name'],
				'view' => (bool) $row['view'],
				'comment' => $row['comment'] ?? '',
			];
		}

		return $tables;
	}


	public function getColumns(string $table): array
	{
		$columns = [];
		$rows = $this->connection->query(<<<'X'
			SELECT
				c.name AS name,
				o.name AS [table],
				UPPER(t.name) AS nativeType,
				CASE
					WHEN c.precision <> 0 THEN c.precision
					WHEN c.max_length <> -1 THEN c.max_length
					ELSE NULL
				END AS size,
				c.scale AS scale,
				c.is_nullable AS nullable,
				OBJECT_DEFINITION(c.default_object_id) AS [default],
				c.is_identity AS autoIncrement,
				CASE WHEN i.index_id IS NULL
					THEN 0
					ELSE 1
				END AS [primary],
				CAST(ep.value AS VARCHAR(255)) AS comment
			FROM
				sys.columns c
				JOIN sys.objects o ON c.object_id = o.object_id
				LEFT JOIN sys.types t ON c.user_type_id = t.user_type_id
				LEFT JOIN sys.key_constraints k ON o.object_id = k.parent_object_id AND k.type = 'PK'
				LEFT JOIN sys.index_columns i ON k.parent_object_id = i.object_id AND i.index_id = k.unique_index_id AND i.column_id = c.column_id
				LEFT JOIN sys.extended_properties ep ON
					ep.major_id = c.object_id AND
					ep.minor_id = c.column_id AND
					ep.name = 'MS_Description'
			WHERE
				o.type IN ('U', 'V')
				AND o.name = ?
			X, [$table]);

		while ($row = $rows->fetch()) {
			$row['vendor'] = $row;
			$row['size'] = $row['size'] ? (int) $row['size'] : null;
			$row['scale'] = $row['scale'] ? (int) $row['scale'] : null;
			$row['nullable'] = (bool) $row['nullable'];
			$row['autoIncrement'] = (bool) $row['autoIncrement'];
			$row['primary'] = (bool) $row['primary'];
			$row['comment'] ??= '';

			$columns[] = $row;
		}

		return $columns;
	}


	public function getIndexes(string $table): array
	{
		$indexes = [];
		$rows = $this->connection->query(<<<'X'
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
				t.name = ?
			ORDER BY
				i.index_id,
				ic.index_column_id
			X, [$table]);

		while ($row = $rows->fetch()) {
			$id = $row['name'];
			$indexes[$id]['name'] = $id;
			$indexes[$id]['unique'] = (bool) $row['unique'];
			$indexes[$id]['primary'] = (bool) $row['primary'];
			$indexes[$id]['columns'][] = $row['column'];
		}

		return array_values($indexes);
	}


	public function getForeignKeys(string $table): array
	{
		// Does't work with multicolumn foreign keys
		$keys = [];
		$rows = $this->connection->query(<<<'X'
			SELECT
				fk.name AS name,
				cl.name AS local,
				tf.name AS [table],
				cf.name AS [foreign]
			FROM
				sys.foreign_keys fk
				JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
				JOIN sys.tables tl ON fkc.parent_object_id = tl.object_id
				JOIN sys.columns cl ON fkc.parent_object_id = cl.object_id AND fkc.parent_column_id = cl.column_id
				JOIN sys.tables tf ON fkc.referenced_object_id = tf.object_id
				JOIN sys.columns cf ON fkc.referenced_object_id = cf.object_id AND fkc.referenced_column_id = cf.column_id
			WHERE
				tl.name = ?
			X, [$table]);

		while ($row = $rows->fetch()) {
			$id = $row['name'];
			$keys[$id]['name'] = $id;
			$keys[$id]['local'][] = $row['local'];
			$keys[$id]['table'] = $row['table'];
			$keys[$id]['foreign'][] = $row['foreign'];
		}

		return array_values($keys);
	}


	public function convertToPhp(mixed $value, array $meta, TypeConverter $converter): mixed
	{
		return match ($meta['nativeType']) {
			'timestamp' => $value, // timestamp does not mean time in sqlsrv
			'bit' => $converter->convertBoolean ? $converter->toBool($value) : $converter->toInt($value),
			default => $converter->convertToPhp($value, $meta),
		};
	}
}
