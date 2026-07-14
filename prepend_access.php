<?php
require_once __DIR__ . '/access_control.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($requestPath !== '/health.php') {
    reject_unlisted_ip();
}
