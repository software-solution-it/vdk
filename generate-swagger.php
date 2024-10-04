<?php
require 'vendor/autoload.php';

use OpenApi\Generator;

$openapi = Generator::scan(['app/Controllers']);

file_put_contents('swagger.json', $openapi->toJson());

echo "Swagger documentation generated successfully!";
