<?php
/**
 * Created by PhpStorm.
 * User: lubomir
 * Date: 03.04.18
 * Time: 18:27
 */

namespace Hesper\Core\Base;

use Hesper\Main\Base\AbstractProtoClass;
use Hesper\Main\Criteria\Criteria;
use Hesper\Main\DAO\StorableDAO;

abstract class BaseModel extends IdentifiableObject {

    protected $form;

    static $model;

    /** @return StorableDAO */
    public static function dao() {
    }

    /** @return AbstractProtoClass */
    public static function proto() {
    }

    public function __construct() {
        $this->form = static::proto()->makeForm();
    }

    public function load($data) {

        if (!isset($data[static::$model])) {

            return false;
        }

        if (!$data[static::$model]['id']) {
            $this->form->get('id')->optional();
        }

        $this->form
            ->import($data[static::$model])
            ->toModel($this);

        return $this;
    }

    public function setAttributes($data = []) {

        if (!empty($data)) {
            $this->form
                ->import($data)
                ->toModel($this, false);
        }

        return $this;
    }

    public function getAttributes() {
        $properties = get_class_vars(static::class);

        $res = [];

        foreach ($properties as $name => $val) {
            $method = 'get' . ucfirst($name);

            if (method_exists($this, $method)) {
                $res[$name] = $this->$method();
            }
        }

        return $res;
    }

    public function save() {

        try {
            $this->beforeSave();

            $insert = static::dao()->take($this);

            $this->afterSave($insert);
        } catch (\Throwable $e) {
            $error[] = $e->getMessage();
            $error[] = $e->getFile() . ' строка: ' . $e->getLine();
            echo implode('<br>', $error);
            exit;
        }

        return $this;
    }

    protected function beforeSave() {

        if ($this->form->getErrors()) {
            $errors = array_keys($this->form->getErrors());

            throw new \Exception('Не валидные данные полей: ' . implode(', ', $errors));
        }
    }

    protected function afterSave(Identifiable $insert) {

        $this->id = $insert->getId();
    }

    public static function getModelName() {
        return static::$model;
    }

    public static function find() {
        return Criteria::create(static::dao());
    }
}