<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/tests/TestEnvironment/vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/TestEnvironment/.env');
