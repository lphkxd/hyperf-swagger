<?php
declare(strict_types=1);

namespace Mzh\Swagger\Annotation;

use Hyperf\HttpServer\Annotation\Controller;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class ApiController extends Controller
{
    public $ignore = [];

    public $generate = [];

    public $tag;
    /**
     * @var null|string
     */
    public $prefix = '';
    /**
     * @var string
     */
    public $server = 'http';
    /**
     * @var string
     */
    public $description = '';
}
