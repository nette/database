<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Table;


/**
 * Represents database row with support for relations.
 * ActiveRow is based on the great library NotORM http://www.notorm.com written by Jakub Vrana.
 *
 * Must stay an empty shell over RowBehavior: any state or behavior belongs to the trait,
 * so that row classes composing RowBehavior themselves behave identically to ActiveRow.
 *
 * @implements \IteratorAggregate<string, mixed>
 */
class ActiveRow implements Row, \IteratorAggregate, IRow
{
	use RowBehavior;
}
