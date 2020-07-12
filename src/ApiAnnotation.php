<?php
namespace Mzh\Swagger;

use Doctrine\Common\Annotations\AnnotationReader;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\ReflectionManager;
use Mzh\Swagger\Annotation\ApiController;

class ApiAnnotation
{
    public static function methodMetadata($className, $methodName)
    {
        $reflectMethod = ReflectionManager::reflectMethod($className, $methodName);
        $reader = new AnnotationReader();
        $methodAnnotations = $reader->getMethodAnnotations($reflectMethod);
        return $methodAnnotations;
    }

    public static function classMetadata($className)
    {
        return AnnotationCollector::getClassAnnotation($className, ApiController::class);
    }
}