<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace Mzh\Swagger;

use Mzh\Swagger\Command\GenCommand;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'commands' => [
            ],
            'dependencies' => [
                \Hyperf\HttpServer\Router\DispatcherFactory::class => \Mzh\Swagger\DispathcerFactory::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for swagger.',
                    'source' => __DIR__ . '/../publish/swagger.php',
                    'destination' => BASE_PATH . '/config/autoload/swagger.php',
                ],
            ],
        ];
    }
}
