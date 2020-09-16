<?php

namespace Mzh\Swagger\Swagger;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\ReflectionManager;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\Utils\ApplicationContext;
use Mzh\Swagger\Annotation\ApiController;
use Mzh\Swagger\Annotation\ApiResponse;
use Mzh\Swagger\Annotation\Body;
use Mzh\Swagger\Annotation\FormData;
use Mzh\Swagger\Annotation\GetApi;
use Mzh\Swagger\Annotation\Param;
use Mzh\Swagger\Annotation\Path;
use Mzh\Swagger\Annotation\Query;
use Mzh\Swagger\ApiAnnotation;
use Mzh\Validate\Annotations\RequestValidation;
use Mzh\Validate\Annotations\Validation;

class SwaggerJson
{

    public $config;

    public $swagger;

    public function __construct()
    {
        $this->config = ApplicationContext::getContainer()->get(ConfigInterface::class);
        $this->swagger = $this->config->get('swagger');
    }

    public function addPath($className, $methodName, $path, $properties)
    {
        /** @var ApiController $classAnnotation */
        $classAnnotation = ApiAnnotation::classMetadata($className);
        $methodAnnotations = ApiAnnotation::methodMetadata($className, $methodName);
        $params = [];
        $responses = [];
        /** @var GetApi $mapping */
        $mapping = null;
        foreach ($methodAnnotations as $key => $option) {
            $validate_class = "";
            switch (true) {
                case $option instanceof Body && $option->scene != '':
                case $option instanceof Query && $option->scene != '':
                case $option instanceof FormData && $option->scene != '':
                    //如果没有设置模块则通过类名获取
                    $validate_class = ($option->validate == '') ? ($properties['validateClass'] ?? '') : $option->validate;
                    $methodAnnotations[$key] = $option;
                    break;
                case $option instanceof Validation:
                case $option instanceof RequestValidation:
                    //兼容validate规则生成body
                    $body = new Body();
                    $validate_class = ($option->validate == '') ? ($properties['validateClass'] ?? '') : $option->validate;
                    if ($option->mode != "") {
                        $validate = explode(".", $option->mode);
                        $validate_class = "\\App\\Validate\\" . $validate[0] . "Validation";
                        if ($option->scene == '') $option->scene = $validate[0] ?? '';
                    }
                    $body->validate = $validate_class;
                    $body->scene = $option->scene;
                    $methodAnnotations[$key] = $body;
                    unset($body);
                    break;
            }
            $option->validate = $validate_class;
            //没有任何验证场景就退出
            if (!isset($option->scene) || $option->scene == '') {
                continue;
            }

            if (is_bool($classAnnotation->ignore)) {
                return;
            }

            if (!class_exists($validate_class)) {
                $classAnnotation->ignore[] = $methodName;
                continue;
            }
            $rulesClass = ReflectionManager::reflectClass($validate_class);
            if (!isset($rulesClass->getDefaultProperties()['scene'][$option->scene])) {
                $classAnnotation->ignore[] = $methodName;
            }
        }

        foreach ($methodAnnotations as $key => $option) {
            if (($option instanceof Body || $option instanceof Query || $option instanceof FormData) && in_array($methodName, $classAnnotation->ignore)) {
                unset($classAnnotation->ignore[array_search($methodName, $classAnnotation->ignore)]);
            }
        }

        foreach ($methodAnnotations as $option) {
            if (is_bool($classAnnotation->ignore)) {
                return;
            }

            if (in_array($methodName, $classAnnotation->ignore)) {
                continue;
            }
            if (!empty($classAnnotation->generate) && !in_array($methodName, $classAnnotation->generate)) {
                continue;
            }

            if ($option instanceof Mapping) {
                $mapping = $option;
            }
            if ($option instanceof Param) {
                $params[] = $option;
            }
            if ($option instanceof ApiResponse) {
                $responses[] = $option;
            }
        }

        $tag = $classAnnotation->tag ?: $className;
        $this->swagger['tags'][$tag] = [
            'name' => $tag,
            'description' => $classAnnotation->description,
        ];
        if ($mapping == null) {
            return;
        }
//        $path = $base_path . '/' . $methodName;
//        if ($mapping->path) {
//            $path = $base_path . '/' . $mapping->path;
//        }
        $method = strtolower($mapping->methods[0]);
        if (empty($responses) && isset($this->swagger['defaultResponses'])) {
            $responses = $this->swagger['defaultResponses'];
        }
        list($parameters, $consumes) = $this->makeParameters($params, $path, $method);
        $this->swagger['paths'][$path][$method] = [
            'tags' => [
                $tag,
            ],
            'operationId' => $method . str_replace('/', '', $path),
            'summary' => $mapping->summary,
            'parameters' => $parameters,
            'consumes' => $consumes,
            'produces' => $consumes,
            'responses' => $this->makeResponses($responses, $path, $method),
            'description' => $mapping->description,
        ];

        if ($mapping->security && isset($this->swagger['securityDefinitions'])) {
            foreach ($this->swagger['securityDefinitions'] as $key => $val) {
                $this->swagger['paths'][$path][$method]['security'][] = [$key => $val['petstore_auth'] ?? []];
            }
        }
    }

    public function getModelName($className)
    {
        $arr = explode("\\", $className);
        return end($arr);
    }

    public function initModel()
    {
        $arraySchema = [
            'type' => 'array',
            'required' => [],
            'items' => [
                'type' => 'string'
            ],
        ];
        $objectSchema = [
            'type' => 'object',
            'required' => [],
            'items' => [
                'type' => 'string'
            ],
        ];

        $this->swagger['definitions']['ModelArray'] = $arraySchema;
        $this->swagger['definitions']['ModelObject'] = $objectSchema;
    }


    public function rules2schema($body, &$schema)
    {
        if ($body->validate != '') {
            $validate = $this->getValidate($body->validate, $body, $schema['required']);
            if (!empty($validate)) {
                $schema['properties'] = array_merge($schema['properties'], $validate);
            }
        } else {
            if ($body->key != '' && !isset($schema['properties'][$body->key])) {
                $property = [
                    'in' => $body->in,
                    'name' => $body->name,
                    'description' => $body->description,
                    'required' => $body->required,
                    'type' => $body->type,
                ];
                if ($body->type == 'array') {
                    $property['name'] = "{$body->name}[]";
                    $property['items'] = new \stdClass();
                    $property['collectionFormat'] = 'multi';
                }
                //这里的对象,是键值对数组
                if ($body->type == 'object') {
                    //若有下级参数,则不显示父级参数
                    $property['name'] = "{$body->name}";
                    $property['type'] = 'array';
                    $property['items'] = new \stdClass();
                    $property['required'] = false;
                    $property['collectionFormat'] = 'multi';
                }
                $schema['properties'][$body->key] = $property;
            }

            if (!empty($body->example)) {
                foreach ($body->example as $key => $val) {
                    if (!empty($body->extra)) {
                        $property['example'] = $body->extra;
                    }
                    if (isset($schema['properties'][$key])) $schema['properties'][$key]['example'] = $val;
                }
            }
            if (!empty($body->default)) {
                foreach ($body->default as $key => $val) {
                    if (isset($schema['properties'][$key])) $schema['properties'][$key]['default'] = $val;
                }
            }
        }

        if (isset($body->rules)) {
            foreach ($body->rules as $field => $rule) {
                $property = [];
                $fieldNameLabel = explode('|', $field);
                $fieldName = $fieldNameLabel[0];
                if (!is_array($rule)) {
                    $type = $this->getTypeByRule($rule);
                } else {
                    //TODO 结构体多层
                    $type = 'string';
                }
                if ($type == 'array') {
                    $property['$ref'] = '#/definitions/ModelArray';;
                }
                if ($type == 'object') {
                    $property['$ref'] = '#/definitions/ModelObject';;
                }
                $property['description'] = $fieldNameLabel[1] ?? '';
                $property['type'] = $type;
                $schema['properties'][$fieldName] = $property;
            }
        }

        if (!empty($body->extra)) {

            foreach ($body->extra as $key => $extra) {
                if (isset($schema['properties'][$key])) $schema['properties'][$key]['example'] = $extra;
            }

        }


        return array_filter($schema);
    }


    public function rulesQuerySchema(Query $item, $schema)
    {
        if ($item->validate != '') {
            $schema = $this->getQueryValidate($item);
            return array_filter($schema);
        } else {
            $property = [
                'in' => $item->in,
                'name' => $item->name,
                'description' => $item->description,
                'required' => $item->required,
                'type' => $item->type,
            ];
            $parameters[$item->name] = $property;
            if (!is_null($item->default)) {
                $parameters[$item->name]['default'] = $item->default;
            }
            if (!is_null($item->enum)) {
                $parameters[$item->name]['enum'] = $item->enum;
            }
            if (!is_null($item->example)) {
                $parameters[$item->name]['example'] = $item->example;
            }
        }

        return array_filter($parameters);
    }


    public function rulesFormSchema(FormData $body, &$schema)
    {
        if (!empty($body->example)) {
            foreach ($body->example as $key => $val) {
                if (isset($schema['properties'][$key])) $schema['properties'][$key]['example'] = $val;
            }
        }
        if (!empty($body->default)) {
            foreach ($body->example as $key => $val) {
                if (isset($schema['properties'][$key])) $schema['properties'][$key]['default'] = $val;
            }
        }
        $property = [];
        $fieldName = $body->name;

        //TODO 结构体多层
        $type = $body->type;
        $property['description'] = $fieldNameLabel[1] ?? '';
        $property['type'] = $type;
        $property['type'] = $body->required;

        $schema['properties'][$fieldName] = $property;

        return array_filter($schema);
    }


    public function getValidate($rule, $body, &$required)
    {
        $properties = [];
        if (!class_exists($body->validate)) {
            return $properties;
        }
        $validation = ReflectionManager::reflectClass($body->validate)->getDefaultProperties();
        $rules = $validation['scene'][$body->scene] ?? [];

        foreach ($rules as $field => $rule) {
            if (is_integer($field)) {
                $field = $rule;
                $rule = $validation['rule'][$field] ?? '';
            } else {
                if (strpos($rule, 'require') !== false) {
                    $required[] = $field;
                }
            }
            $property = [];
            $property['description'] = $validation['field'][$field] ?? $field;
            if (is_array($rule)) {
                $default = $rule;
            } else {
                $default = explode('|', preg_replace('/\[.*\]/', '', $rule));
            }
            foreach ($default as $item) {
                if ($item == 'arrayHasOnlyInts') {
                    $property['type'] = 'array';
                    $property['example'] = $example[$field] ?? [1, 2];
                }
                if (!strpos($item, ':')) {
                    continue;
                }
                $property = array_merge($this->getValType($item), $property);
            }
            $type = $this->getTypeByRule($rule);
            $property['type'] = $type;

            $example = $body->example;
            if (isset($example[$field])) {
                $property['example'] = $example[$field];
            }
            if ($type == 'array') {
                $property['example'] = [1];
            }
            $properties[$field] = $property;
        }
        return $properties;
    }

    public function getValidateType($type)
    {
        $alias = [
            'gt' => ' > ', 'egt' => ' >= ', 'lt' => ' < ', 'elt' => ' <= ', 'eq' => ' = ', 'min' => '最小 ', 'max' => '最大 ',
        ];
        if (isset($alias[$type])) {
            // 判断别名
            return $alias[$type];
        }
        return '';
    }

    public function getTypeByRule($rule)
    {
        if (is_array($rule)) {
            return 'array';
        }
        $default = explode('|', preg_replace('/\[.*\]/', '', $rule));
        if (array_intersect($default, ['int', 'lt', 'gt', 'ge', 'integer', 'max', 'min'])) {
            return 'integer';
        }
        if (array_intersect($default, ['array'])) {
            return 'array';
        }
        if (array_intersect($default, ['object'])) {
            return 'object';
        }
        if (array_intersect($default, ['arrayHasOnlyInts'])) {
            return 'array';
        }
        return 'string';
    }


    public function makeParameters($params, $path, $method = '')
    {
        $consumes = ["application/json"];
        $this->initModel();
        $path = str_replace(['{', '}'], '', $path);
        $parameters = [];
        /** @var Query $item */

        $schema = [
            'type' => 'object',
            'required' => [],
            'properties' => [],
            'items' => [],
        ];
        $query = [];
        foreach ($params as $item) {
            if ($item instanceof Path) {
                $parameters[$item->name] = [
                    'in' => $item->in,
                    'name' => $item->name,
                    'type' => $item->type ?? 'string',
                    'description' => $item->description ?? '',
                    'required' => false,
                ];
            }
            if ($item instanceof Body) {
                $parameters[$item->name] = [
                    'in' => $item->in,
                    'name' => $item->name,
                    'description' => $item->description ?? '',
                    'required' => false,
                ];
                $modelName = implode('', array_map('ucfirst', explode('/', $path)));
                $definitions = $this->rules2schema($item, $schema);
                $this->swagger['definitions'][$modelName] = $definitions;
                $parameters[$item->name]['schema']['$ref'] = '#/definitions/' . $modelName;
                $parameters[$item->name]['required'] = !empty($schema['required']);
            }
            if ($item instanceof FormData) {
                if ($method == 'post') {
                    $consumes = ["application/x-www-form-urlencoded"];
                }
                $parameters[$item->name] = [
                    'in' => $item->in,
                    'name' => $item->name,
                    'description' => $item->description ?? '',
                    'required' => false,
                ];

                $property = [
                    'in' => $item->in,
                    'name' => $item->name,
                    'description' => $item->description,
                    'required' => $item->required,
                    'type' => $item->type ?? 'string',
                ];
                if ($item->type == 'array') {
                    $property['name'] = "{$item->name}[]";
                    $property['items'] = new \stdClass();
                    $property['collectionFormat'] = 'multi';
                }
                //这里的对象,是键值对数组
                if ($item->type == 'object') {
                    $property['name'] = "{$item->name}";
                    $property['type'] = 'array';
                    $property['items'] = new \stdClass();
                    $property['required'] = false;
                    $property['collectionFormat'] = 'multi';
                }
                $parameters[$item->name] = $property;
                if (!is_null($item->default)) {
                    $parameters[$item->name]['default'] = $item->default;
                }
                if (!is_null($item->enum)) {
                    $parameters[$item->name]['enum'] = $item->enum;
                }
                if (!is_null($item->example)) {
                    $parameters[$item->name]['example'] = $item->example;
                }
            }
            if ($item instanceof Query) {
                $query = array_merge($this->rulesQuerySchema($item, $query), $query);
            }
        }
        return [array_values(array_merge($parameters, $query)), $consumes];
    }

    public function makeResponses($responses, $path, $method)
    {
        $path = str_replace(['{', '}'], '', $path);
        $resp = [];
        $default = false;
        /** @var ApiResponse $item */
        $tmp = $responses;
        $responses = [];
        foreach ($tmp as $code => $response) {
            if (!$response instanceof ApiResponse) {
                $item = new ApiResponse();
                $item->code = $code;
                $item->schema = $response['schema'] ?? [];
                $item->description = $response['description'] ?? '';
                $response = $item;
                unset($item);
                $default = true;
            }
            $responses[] = $response;
        }
        foreach ($responses as $item) {
            $resp[$item->code] = [
                'description' => $item->description,
            ];
            if ($item->schema) {
                if (!$default) {
                    $modelName = implode('', array_map('ucfirst', explode('/', $path))) . ucfirst($method) . 'Response' . $item->code;
                } else {
                    $modelName = 'ResponseCode' . $item->code;
                }
                $ret = $this->responseSchemaTodefinition($item->schema, $modelName);
                if ($ret) {
                    $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $modelName;
                }
            }
        }
        return $resp;
    }

    public function responseSchemaTodefinition($schema, $modelName, $level = 0)
    {
        if (!$schema) {
            return false;
        }
        $definition = [];
        foreach ($schema as $key => $val) {
            $_key = str_replace('_', '', $key);
            $property = [];
            $property['type'] = gettype($val);
            if (is_array($val)) {
                $definitionName = $modelName . ucfirst($_key);
                if ($property['type'] == 'array' && isset($val[0])) {
                    if (is_array($val[0])) {
                        if (isset($val[0][0]) && is_array($val[0][0])) {
                            $property['type'] = 'array';
                            $ret = $this->responseSchemaTodefinition($val[0][0], $definitionName, 2);
                            $property['items']['$ref'] = '#/definitions/' . $definitionName;
                        } else {
                            $property['type'] = 'array';
                            $ret = $this->responseSchemaTodefinition($val[0], $definitionName, 1);
                            $property['items']['$ref'] = '#/definitions/' . $definitionName;
                        }
                    } else {
                        $property['type'] = 'array';
                        $property['items']['type'] = gettype($val[0]);
                    }
                } else {
                    $property['type'] = 'array';
                    $ret = $this->responseSchemaTodefinition($val, $definitionName, 1);
                    $property['$ref'] = '#/definitions/' . $definitionName;
                }
                if (isset($ret)) {
                    $this->swagger['definitions'][$definitionName] = $ret;
                }
            } else {
                $property['example'] = $val;
            }
            $definition['properties'][$key] = $property;
        }
        if ($level === 0) {
            $this->swagger['definitions'][$modelName] = $definition;
        }
        return $definition;
    }

    public function save()
    {
        $this->swagger['tags'] = array_values($this->swagger['tags'] ?? []);
        $outputFile = $this->swagger['output_file'] ?? '';
        if (!$outputFile) {
            return;
        }
        unset($this->swagger['output_file']);
        unset($this->swagger['defaultResponses']);
        file_put_contents($outputFile, json_encode($this->swagger, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function getQueryValidate(Query $item)
    {
        $parameters = [];
        if (!class_exists($item->validate)) {
            return $parameters;
        }
        $validation = ReflectionManager::reflectClass($item->validate)->getDefaultProperties();
        $rules = $validation['scene'][$item->scene] ?? [];
        $messages = $validation['field'] ?? [];;
        foreach ($rules as $name => $rule) {
            $property = [
                'in' => $item->in,
                'name' => $name,
                'description' => $messages[$name] ?? '',
                'required' => false,
                'type' => $item->type,
            ];
            if (is_numeric($name)) {
                $name = $rule;
                $property['name'] = $rule;
                $ruleType = $validation['rule'][$name] ?? '';
            } else {
                $property['required'] = strpos($rule, 'require') !== false;
                $ruleType = $rule;
            }
            $property = array_merge($property, $this->getValType($ruleType));
            $example = $item->example;
            if (isset($example[$name])) {
                $property['example'] = $example[$name];
            }
            $parameters[$name] = $property;
        }
        return $parameters;
    }

    private function getValType($item)
    {
        if (empty($item) || strpos($item, ':') === false) {
            return [];
        }
        $property = [];
        list($key, $val) = explode(':', preg_replace('/\[.*\]/', '', $item));
        switch ($key) {
            case 'int':
            case 'intOrArrayInt':
            case 'lt':
            case  'gt':
            case  'ge':
            case  'integer':
                $property['type'] = 'integer';
                break;
            case  'max':
                $property['type'] = 'string';
                $property['maxLength'] = intval($val);
                break;
            case  'min':
                $property['type'] = 'string';
                $property['minLength'] = intval($val);
                break;
            case 'length':
                list($property['minLength'], $property['maxLength']) = array_map(function ($val) {
                    return intval($val);
                }, explode(",", $val));
                break;
            case "in":
                if (is_numeric(current(explode(",", $val)))) {
                    $property['type'] = 'integer';
                    $property['example'] = intval(current(explode(",", $val)));
                } else {
                    $property['type'] = 'string';
                    $property['example'] = current(explode(",", $val));
                }
                $property['enum'] = array_map(function ($val) {
                    if (is_numeric($val)) {
                        return intval($val);
                    } else {
                        return ($val);
                    }
                }, explode(",", $val));
                break;
        }
        return $property;
    }
}