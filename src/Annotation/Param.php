<?php

namespace Mzh\Swagger\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

abstract class Param extends AbstractAnnotation
{
    /**
     * @var string 在哪个结构
     */
    public $in;


    /**
     * @var string 字段key,相当于"name[|description]"
     */
    public $key;


    /**
     * @var string 单个规则字符串
     */
    public $rule;


    /**
     * @var string 默认值
     */
    public $default;


    /**
     * @var string 字段名
     */
    public $name;


    /**
     * @var string 字段描述
     */
    public $description;


    /**
     * @var array 详细规则数组
     */
    public $_detailRules = [];


    /**
     * @var bool 是否必须
     */
    public $required = false;


    /**
     * @var string 字段类型
     */
    public $type;


    /**
     * @var array 字段枚举值
     */
    public $enum;

    public $extra = [];
    public $example;


    public function __construct($value = null)
    {
        parent::__construct($value);
        $this->setName()->setDescription()->setRquire()->setType();
    }

    public function setName()
    {
        if (!empty($this->name)) {
            return $this;
        }
        $this->name = explode('|', $this->key)[0];
        return $this;
    }

    public function setDescription()
    {
        if (!empty($this->description)) {
            return $this;
        }
        $this->description = $this->description ?: explode('|', $this->key)[1] ?? '';

        return $this;
    }

    public function setRquire()
    {
        $this->required = strpos($this->rule, 'required') !== false;

        return $this;
    }

    public function setType()
    {
        if (!empty($this->type)) {
            return $this;
        }
        $type = 'string';
        if (strpos($this->rule, 'int') !== false) {
            $type = 'integer';
        }
        $this->type = $type;
        return $this;
    }
}
