<?php
/**
 * @project    Hesper Framework
 * @author     Alex Gorbylev
 * @originally onPHP Framework
 * @originator Ivan Y. Khvostishkov
 */
namespace Hesper\Core\Form\Primitive;

use Hesper\Core\Base\Assert;
use Hesper\Core\Base\Identifiable;
use Hesper\Core\Exception\BaseException;
use Hesper\Core\Exception\WrongArgumentException;
use Hesper\Core\Exception\WrongStateException;
use Hesper\Main\DAO\DAOConnected;
use Hesper\Main\Util\ClassUtils;

/**
 * Hint: use raw values like 'City.42' or 'Country.42' where City and
 * Country are childrens of base class GeoLocation, for example.
 * @package Hesper\Core\Form\Primitive
 */
final class PrimitivePolymorphicIdentifier extends PrimitiveIdentifier {

	const WRONG_CID_FORMAT = 201;
	const WRONG_CLASS      = 202;

	const DELIMITER = '.';

	private $baseClassName = null;

	public static function export($value) {
		if ($value === null) {
			return null;
		}

		Assert::isInstance($value, Identifiable::class);

		return get_class($value) . self::DELIMITER . $value->getId();
	}

	/**
	 * @throws WrongStateException
	 **/
	public function of($class) {
		throw new WrongStateException('of() must not be called directly, use ofBase()');
	}

	/**
	 * @throws WrongArgumentException
	 * @return PrimitivePolymorphicIdentifier
	 **/
	public function ofBase($className) {
		Assert::classExists($className);

		Assert::isInstance($className, DAOConnected::class, "class '{$className}' must implement DAOConnected interface");

		$this->baseClassName = $className;

		return $this;
	}

	public function getBaseClassName() {
		return $this->baseClassName;
	}

	/**
	 * @return PrimitivePolymorphicIdentifier
	 **/
	public function setValue($value) {
		Assert::isInstance($value, $this->baseClassName);

		parent::of(get_class($value));

		return parent::setValue($value);
	}

	public function exportValue() {
		if ($this->value === null) {
			return null;
		}

		return self::export($this->value);
	}

	public function importValue($value) {
		return $this->import([$this->getName() => self::export($value)]);
	}

	public function import($scope) {
		$savedRaw = null;

		if (isset($scope[$this->name]) && $scope[$this->name]) {
			$savedRaw = $scope[$this->name];

			$this->customError = null;

			try {

				list($class, $id) = explode(self::DELIMITER, $savedRaw, 2);

			} catch (BaseException $e) {

				$this->customError = self::WRONG_CID_FORMAT;

			}

			if (!$this->customError && !ClassUtils::isInstanceOf($class, $this->baseClassName)) {

				$this->customError = self::WRONG_CLASS;

			}

			if (!$this->customError) {
				parent::of($class);

				$scope[$this->name] = $id;
			}

		} else {
			// we need some class in any case
			parent::of($this->baseClassName);
		}

		if (!$this->customError) {
			$result = parent::import($scope);
		} else {
			$this->value = null;
			$result = false;
		}

		if ($savedRaw) {
			$this->raw = $savedRaw;
		}

		return $result;
	}
}
