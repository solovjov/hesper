<?php
/**
 * @project    Hesper Framework
 * @author     Alex Gorbylev
 * @originally onPHP Framework
 * @originator Konstantin V. Arkhipov
 */
namespace Hesper\Core\OSQL;

use Hesper\Core\Base\Aliased;
use Hesper\Core\Base\Assert;
use Hesper\Core\DB\Dialect;
use Hesper\Core\DB\ImaginaryDialect;
use Hesper\Core\Exception\UnimplementedFeatureException;
use Hesper\Core\Exception\WrongArgumentException;
use Hesper\Core\Logic\LogicalObject;

/**
 * Class QuerySkeleton
 * @package Hesper\Core\OSQL
 */
abstract class QuerySkeleton extends QueryIdentification {

	protected $where      = [];    // where clauses
	protected $whereLogic = [];    // logic between where's
	protected $aliases    = [];
	protected $returning  = [];

	public function getWhere() {
		return $this->where;
	}

	public function getWhereLogic() {
		return $this->whereLogic;
	}

	/**
	 * @throws WrongArgumentException
	 * @return QuerySkeleton
	 **/
	public function where(LogicalObject $exp, $logic = null) {
		if ($this->where && !$logic) {
			throw new WrongArgumentException('you have to specify expression logic');
		} else {
			if (!$this->where && $logic) {
				$logic = null;
			}

			$this->whereLogic[] = $logic;
			$this->where[] = $exp;
		}

		return $this;
	}

	/**
	 * @return QuerySkeleton
	 **/
	public function andWhere(LogicalObject $exp) {
		return $this->where($exp, 'AND');
	}

	/**
	 * @return QuerySkeleton
	 **/
	public function orWhere(LogicalObject $exp) {
		return $this->where($exp, 'OR');
	}

	/**
	 * @return QuerySkeleton
	 **/
	public function returning($field, $alias = null) {
		$this->returning[] = $this->resolveSelectField($field, $alias, $this->table);

		if ($alias = $this->resolveAliasByField($field, $alias)) {
			$this->aliases[$alias] = true;
		}

		return $this;
	}

	/**
	 * @return QuerySkeleton
	 **/
	public function dropReturning() {
		$this->returning = [];

		return $this;
	}

	public function toDialectString(Dialect $dialect) {
		if ($this->where) {
			$clause = ' WHERE';
			$outputLogic = false;

			for ($i = 0, $size = count($this->where); $i < $size; ++$i) {

				if ($exp = $this->where[$i]->toDialectString($dialect)) {

					$clause .= "{$this->whereLogic[$i]} {$exp} ";
					$outputLogic = true;

				} elseif (!$outputLogic && isset($this->whereLogic[$i + 1])) {
					$this->whereLogic[$i + 1] = null;
				}

			}

			return rtrim($clause, ' ');
		}

		return null;
	}

	/**
	 * @return QuerySkeleton
	 */
	public function spawn() {
		return clone $this;
	}

	protected function resolveSelectField($field, $alias, $table) {
		if (is_object($field)) {
			if (($field instanceof DBField) && ($field->getTable() === null)) {
				$result = new SelectField($field->setTable($table), $alias);
			} elseif ($field instanceof SelectQuery) {
				$result = $field;
			} elseif ($field instanceof DialectString) {
				$result = new SelectField($field, $alias);
			} else {
				throw new WrongArgumentException('unknown field type');
			}

			return $result;
		} elseif (false !== strpos($field, '*')) {
			throw new WrongArgumentException('do not fsck with us: specify fields explicitly');
		} elseif (false !== strpos($field, '.')) {
			throw new WrongArgumentException('forget about dot: use DBField');
		} else {
			$fieldName = $field;
		}

		$result = new SelectField(new DBField($fieldName, $table), $alias);

		return $result;
	}

	protected function resolveAliasByField($field, $alias) {
		if (is_object($field)) {
			if (($field instanceof DBField) && ($field->getTable() === null)) {
				return null;
			}

			if ($field instanceof SelectQuery || ($field instanceof DialectString && $field instanceof Aliased)) {
				return $field->getAlias();
			}
		}

		return $alias;
	}

	/**
	 * @return QuerySkeleton
	 **/
	protected function checkReturning(Dialect $dialect) {
		if ($this->returning && !$dialect->hasReturning()) {
			throw new UnimplementedFeatureException();
		}

		return $this;
	}

	protected function toDialectStringField($field, Dialect $dialect) {
		if ($field instanceof SelectQuery) {
			Assert::isTrue(null !== $alias = $field->getName(), 'can not use SelectQuery to table without name as get field: ' . $field->toDialectString(ImaginaryDialect::me()));

			return "({$field->toDialectString($dialect)}) AS " . $dialect->quoteField($alias);
		} else {
			return $field->toDialectString($dialect);
		}
	}

	protected function toDialectStringReturning(Dialect $dialect) {
		$fields = [];

		foreach ($this->returning as $field) {
			$fields[] = $this->toDialectStringField($field, $dialect);
		}

		return implode(', ', $fields);
	}
}
