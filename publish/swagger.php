<?php

return [
    'output_file' => BASE_PATH . '/public/swagger.json',
    'swagger' => '2.0',
    'enable' => false, // 开启web访问？
    'host' => 'hyperf.io',
    'info' => [
        'description' => 'hyperf swagger api desc',
        'version' => '1.0.0',
        'title' => 'HYPERF API DOC',
    ],
    'schemes' => ['http']
];
