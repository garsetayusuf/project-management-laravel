<?php

$port = 8000;
if (file_exists('.env')) {
    foreach (file('.env') as $line) {
        if (strpos($line, 'APP_PORT=') === 0) {
            $port = trim(substr($line, 9));
            break;
        }
    }
}
passthru("php artisan serve --port=$port");
