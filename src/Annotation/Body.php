<?php
declare(strict_types=1);

namespace Mzh\Swagger\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Body extends Param
{
    public $in = 'body';
    public $name = 'body';
    public $rules = [];
    public $scene = '';
    public $validate = '';
    public $description = 'body';
    public $security = true;

    public function __construct($value = null)
    {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $val;
                }
            }
        }
        $this->setRquire();
    }

    public function setRquire()
    {

        $this->required = strpos(json_encode($this->rules), 'required') !== false;
        return $this;
    }

    public function setType()
    {
        $this->type = '';

        return $this;
    }
}
