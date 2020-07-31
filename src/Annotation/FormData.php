<?php
declare(strict_types = 1);
namespace Mzh\Swagger\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class FormData extends Param
{
    public $in = 'formData';
    public $scene = '';
    public $validate = '';
}
