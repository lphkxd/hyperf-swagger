<?php
declare(strict_types = 1);
namespace Mzh\Swagger\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Path extends Param
{
    public $in = 'path';
    public $userOpen = false;

}
