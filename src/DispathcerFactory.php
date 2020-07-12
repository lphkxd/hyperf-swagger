<?php
declare(strict_types=1);

namespace Mzh\Swagger;

use Hyperf\Di\Exception\ConflictAnnotationException;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\RouteCollector;
use Mzh\Swagger\Annotation\ApiController;
use Mzh\Swagger\Swagger\SwaggerJson;

class DispathcerFactory extends DispatcherFactory
{

    /**
     * @var SwaggerJson
     */
    public $swagger;

    public function __construct()
    {
        $this->swagger = new SwaggerJson();
        parent::__construct();
    }

    /**
     * 1. 根据注解注册路由
     * 2. 根据注解生成swagger文件
     */
    protected function handleController(string $className, Controller $annotation, array $methodMetadata, array $middlewares = []): void
    {
        if (!$methodMetadata) {
            return;
        }
        $prefix = $this->getPrefix($className, $annotation->prefix);
        $router = $this->getRouter($annotation->server);
        foreach ($methodMetadata as $methodName => $values) {
            $methodMiddlewares = $middlewares;
            // Handle method level middlewares.
            if (isset($values)) {
                $methodMiddlewares = array_merge($methodMiddlewares, $this->handleMiddleware($values));
                $methodMiddlewares = array_unique($methodMiddlewares);
            }

            foreach ($values as $mapping) {
                if (!($mapping instanceof Mapping)) {
                    continue;
                }
                if (!isset($mapping->methods)) {
                    continue;
                }
                $path = $prefix . '/' . $methodName;
                if ($mapping->path) {
                    $justId = preg_match('/{.*}/', $mapping->path);
                    if ($justId) {
                        $path = $prefix . '/' . $mapping->path;
                    } else {
                        $path = $prefix . '/' . $mapping->path;
                    }
                }
                if ($this->hasRoute($router, $mapping, $path)) {
                    continue;
                }
                $router->addRoute($mapping->methods, $path, [$className, $methodName], [
                    'middleware' => $methodMiddlewares,
                ]);
                $this->swagger->addPath($className, $methodName, $path);
            }
        }
    }

    protected function initAnnotationRoute(array $collector): void
    {

        foreach ($collector as $className => $metadata) {
            if (isset($metadata['_c'][ApiController::class])) {
                $middlewares = $this->handleMiddleware($metadata['_c']);
                $this->handleController($className, $metadata['_c'][ApiController::class], $metadata['_m'] ?? [], $middlewares);
            }
            if (isset($metadata['_c'][AutoController::class])) {
                if ($this->hasControllerAnnotation($metadata['_c'])) {
                    $message = sprintf('AutoController annotation can\'t use with Controller annotation at the same time in %s.', $className);
                    throw new ConflictAnnotationException($message);
                }
                $middlewares = $this->handleMiddleware($metadata['_c']);
                $this->handleAutoController($className, $metadata['_c'][AutoController::class], $middlewares, $metadata['_m'] ?? []);
            }
            if (isset($metadata['_c'][Controller::class])) {
                $middlewares = $this->handleMiddleware($metadata['_c']);
                parent::handleController($className, $metadata['_c'][Controller::class], $metadata['_m'] ?? [], $middlewares);
            }
        }
        $this->swagger->save();
    }

    private function hasRoute(RouteCollector $router, Mapping $mapping, $path)
    {
        foreach ($router->getData() as $datum) {
            foreach ($mapping->methods as $method){
                if (isset($datum[$method][$path])) return true;
            }
        }
        return false;
    }

}
