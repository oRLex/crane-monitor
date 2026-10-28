<?php

// Router for PHP's built-in server on Wasmer Edge: serve existing files from public/,
// send everything else (including paths with dots) to Laravel.
$path = $_SERVER['DOCUMENT_ROOT'].urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if ($path !== $_SERVER['DOCUMENT_ROOT'].'/' && is_file($path)) {
    return false;
}

require $_SERVER['DOCUMENT_ROOT'].'/index.php';
