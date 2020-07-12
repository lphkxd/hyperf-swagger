<?php
declare(strict_types = 1);
namespace Mzh\Swagger\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Header extends Param
{
    public $in = 'header';
}
