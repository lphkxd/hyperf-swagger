<?php
declare(strict_types=1);

namespace Mzh\Swagger\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class ApiResponse extends AbstractAnnotation
{

    public $code = 500;
    public $description;
    public $schema = [];

    public function __construct($value = null)
    {
        parent::__construct($value);
        if (is_array($this->description)) {
            $this->description = json_encode($this->description, JSON_UNESCAPED_UNICODE);
        }
        $this->makeSchema();
    }

    public function makeSchema()
    {

    }
}
