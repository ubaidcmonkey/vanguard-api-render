<?php
require_once __DIR__ . '/access_control.php';

reject_unlisted_ip();

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(["status" => "ok"]);
