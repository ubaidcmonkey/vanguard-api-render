<?php
header('Content-Type: application/json');
session_start();
require 'vendor/autoload.php';
require_once __DIR__ . '/access_control.php';

if (!class_exists('Google\Protobuf\RepeatedField', true)) {
    class_alias('Google\Protobuf\Internal\RepeatedField', 'Google\Protobuf\RepeatedField');
}
require_once __DIR__ . '/LunarisMeta/Authentication.php';
require_once __DIR__ . '/LunarisMeta/Tokenresp.php';
require_once __DIR__ . '/LunarisMeta/Accessrequest.php';

require_once __DIR__ . '/Lunaris/AuthenticationRequest.php';
require_once __DIR__ . '/Lunaris/AuthenticationResponse.php';
require_once __DIR__ . '/Lunaris/AccessRequest.php';
require_once __DIR__ . '/Lunaris/Sub2.php';
require_once __DIR__ . '/Lunaris/vg_version.php';

use Vanguard\AuthenticationRequest;
use Vanguard\AuthenticationResponse;
use Vanguard\AccessRequest;
use Vanguard\Sub2;
use Vanguard\vg_version;
use phpseclib3\Crypt\RSA;

$GAME_IDS = [
    "valo" => "com.riotgames.valorant",
    "league" => "com.riotgames.league",
];

function track_active_user(?string $action = null, ?string $game = null): void
{
    $runtimeDir = runtime_dir();
    $file = $runtimeDir . '/active_users.json';
    $now = time();
    $ip = client_ip();
    $users = [];

    if (is_file($file)) {
        $existing = json_decode((string) file_get_contents($file), true);
        if (is_array($existing)) {
            $users = $existing;
        }
    }

    foreach ($users as $userIp => $seen) {
        $lastSeen = is_array($seen) ? ($seen['last_seen'] ?? 0) : 0;
        if (!is_int($lastSeen) || $lastSeen < $now - 900) {
            unset($users[$userIp]);
        }
    }

    $users[$ip] = [
        'ip' => $ip,
        'last_seen' => $now,
        'action' => $action ?: 'unknown',
        'game' => $game ?: 'unknown',
    ];

    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT), LOCK_EX);
}

function encode_varint(int $n): string
{
    $out = '';
    while (true) {
        $b = $n & 0x7F;
        $n >>= 7;
        if ($n) {
            $out .= chr($b | 0x80);
        } else {
            $out .= chr($b);
            break;
        }
    }
    return $out;
}

function fail(int $code, string $message): never
{
    http_response_code($code);
    die(json_encode(["success" => false, "message" => $message]));
}

reject_unlisted_ip();

function decrypt_resp(string $payload, string $privateKeyPem): string
{
    $minLength = 9 + 256 + 12 + 16;
    if (strlen($payload) < $minLength) {
        throw new \InvalidArgumentException('payload too short');
    }

    $offset = 9;
    $encryptedKey = substr($payload, $offset, 256);
    $offset += 256;
    $iv = substr($payload, $offset, 12);
    $offset += 12;
    $tag = substr($payload, -16);
    $ciphertext = substr($payload, $offset, strlen($payload) - $offset - 16);

    $rsa = RSA::loadPrivateKey($privateKeyPem)->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha512')->withMGFHash('sha512');
    $aesKey = $rsa->decrypt($encryptedKey);

    if ($aesKey === false || strlen($aesKey) !== 32) {
        throw new \RuntimeException('not lunaris generated session');
    }

    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);

    if ($plaintext === false) {
        throw new \RuntimeException('failed to decrypt');
    }

    return $plaintext;
}

function build_payload(string $data, string $pubkey, string $type): string
{
    $key = random_bytes(32);
    $iv = random_bytes(12);
    $tag = '';

    $ciphertext = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

    $rsa = RSA::loadPublicKey($pubkey)->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha512')->withMGFHash('sha512');
    $rsaEncKey = $rsa->encrypt($key);


    $rito_payload = hex2bin("52470100") . $rsaEncKey . $iv . $ciphertext . $tag;
    $outerWrapper = "\x08" . $type . "\x12" . encode_varint(strlen($rito_payload));

    return $outerWrapper . $rito_payload;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    fail(400, "request body must be valid json");
}

$action = isset($input["action"]) && is_string($input["action"]) ? $input["action"] : "auth";
$requested_game = isset($input["game"]) && is_string($input["game"]) ? $input["game"] : null;
$sid = isset($input["sid"]) && is_string($input["sid"]) ? $input["sid"] : null;
$gameToken = isset($input["gametoken"]) && is_string($input["gametoken"]) ? $input["gametoken"] : null;
$response_b64 = isset($input["response"]) && is_string($input["response"]) ? $input["response"] : null;

track_active_user($action, $requested_game);

if ($action === "auth") {

    if (!$gameToken) {
        fail(400, "missing required field: gametoken");
    }

    if (!$requested_game) {
        fail(400, "missing required field: game");
    }

    if ($requested_game === "valo" && !$sid) {
        fail(400, "missing required field: sid for game=valo");
    }

    if (!isset($GAME_IDS[$requested_game])) {
        fail(400, "unknown game type: expected valo or league");
    }

    $gameId = $GAME_IDS[$requested_game];

    $sessionPrivKey = "-----BEGIN PRIVATE KEY-----\nMIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCFe8i2Glpe7+Ba\nhmuHIVGLtRfsKD5cBvy8w3yXkjhARa4hHjQXA5cx4bJKOjgIrucklGtGtexyAn2D\nN7yJU++kJ6QdwikYsAWt3ukOBigmnMYJPiOi1qFl9R+mEg0v9JnzkN8I03U09hiT\n9BsSdVGxkPYVY543EvhKYBHPX/uXy921563uIepiI4Cb67RdJcG88CJXjt5NZGCa\nUSXGH6B+gp9rrj74keeMLlStydqSjeALBVtGCjHKfV7djYNI5xRR5Nedr1mjszB3\n/D0ZjJHQXTb4+bVsmW8hNFzX9PYniSMHl6inaZ4Rhju0EzxOVWcCGHSvWE9Z0sm+\n9Uu5NkibAgMBAAECggEAJ+VrhCI0SJPhtqzejrECsoMZ91e/67ma6MB1CMiHT46E\nERn577b/BcWziEQGY3IDXAeQWL4fQaRE52dNTq5rveCrSMmzhtF1oRYzCiIE9iV9\ne127QPxtmQ++ueBDWMX/DbGLOBQbwAyeI/qd7NJr7GqrYpE3xLZCx9gW+qhxhljW\nFcJW4cb/VvQYaFPfZBJvfhY0bsT6QpKNrsv1dftd/lj8Uh2+ma+7ykGWCRyEQuaK\niQHO8WDCFJOf0vHR4H6MvDNn+5rATFzv69FzXzFF5GC8KbhlhQKZ76OWG+T3U2Yu\nvhEefEC8hUYavk2aeDLtiy/S4dwI4TaaQTLOd67wwQKBgQC6Y48kVSqQaGJOIf9A\nUj5mpJGT1ZbraRTtZAaPJNpu0lWxVZHYqiL0van7pPMmSgrpnRV8pYIpLAogFaiF\nHsbJoGnFNNjVqfEVmaIUzMo1gRKKJ2njMt3QRlVfAO/Iile07wWci1lWcgNgRuPW\ngf1yTlaC7y+vSNxKrpkoDZ9KEwKBgQC3Vf/rdVdROEkFWxOcxFu63BJomB2TIF2q\ngN559PRtMYwXZZjckG26D21KNHTgTtpMbkxmgWDVQ/0lf11tlrMfHFU4lafiw+WY\n5fd4HkbQ6v2B3f8TjaLAIEZfV9e1HGpU5S8h1sEsufsyxqBbogiGRAYjfwkryT7o\nJKEmMwVYWQKBgFejfmen/+Z8nlR8mcdFpH+gu66WTGsOMr/YO1lNC8P19EL4qCYH\nAX6wO1/OVGHZiL4FlVfRfp0bTvt9E4rcSL3/Rhxq19XHHUt5vIMpM57qvKvnElu4\nzCElIPkVuKlDmy/A/5N21h/WZg375x8yadg4S2cvTe2ORb570BnMJeyvAoGAOJKa\nEQ85bX+f0L5E9AgHgkasi4f9AExpetafUCTNU/CJGSMpo04R/esKv24mbp0GcbVL\ncAoWVljPgcWmj82D4mK8zWQo1Sm77I1x6qf1FDyfE3bsYh0/jmenL36Mun9VNHMw\nMxHwtBuDryxpiT0bwkq1Vji6HL/R4JKFA6OUz6kCgYAtKJ1lhvVneGeLU4Eh1BcV\n2Wg1Wr90hdl29t5+UmZSawJyOcW/9JcEr4oM7yjCItltwhaY4+RTpC47SKMd5XuT\nQncr5G+OUjp+FjANCf6zrkAsLjhhU3NGfok5Ei3QZg+ReUOt/Y1hvXTO2Yt81Oki\nrkKvlHAKoLaDakuo4DzRlQ==\n-----END PRIVATE KEY-----\n";
    $_SESSION['private_key'] = $sessionPrivKey;
    $pubKeyBase64 = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAhXvIthpaXu/gWoZrhyFRi7UX7Cg+XAb8vMN8l5I4QEWuIR40FwOXMeGySjo4CK7nJJRrRrXscgJ9gze8iVPvpCekHcIpGLAFrd7pDgYoJpzGCT4jotahZfUfphINL/SZ85DfCNN1NPYYk/QbEnVRsZD2FWOeNxL4SmARz1/7l8vdteet7iHqYiOAm+u0XSXBvPAiV47eTWRgmlElxh+gfoKfa64++JHnjC5Urcnako3gCwVbRgoxyn1e3Y2DSOcUUeTXna9Zo7Mwd/w9GYyR0F02+Pm1bJlvITRc1/T2J4kjB5eop2meEYY7tBM8TlVnAhh0r1hPWdLJvvVLuTZImwIDAQAB";

    $msg = new AuthenticationRequest();
    $msg->setMachineId(bin2hex(random_bytes(32)));

    $f2 = new Sub2();
    $f2->setA(1);
    $f2->setB(2);
    $f2->setVersion("10.0.26200.8037");
    $msg->setField2($f2);

    $msg->setGameToken($gameToken);

    if ($requested_game === "valo") {
        $msg->setExternalSid($sid);
    }

    $msg->setClientRsaPublicKey($pubKeyBase64 . "\n");
    $msg->setGameId($gameId);
    $msg->setBootState(3);

    $vg_ver = new vg_version();
    $vg_ver->setA(1);
    $vg_ver->setB(18);
    $vg_ver->setC(3);
    $vg_ver->setD(77);
    $msg->setVersion1($vg_ver);
    $msg->setVersion2($vg_ver);

    $publicKey = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAz7Vh5LOgV9FxsyeXlvP6O\nIfD0BFDv65A4wG6pgKO5EbJ6zSxsnU/fkFJeSjE8hJxX2CeEV9XODahl2ofF/jfTv\n2GhQIJt7ePFT6s4M6ZmDiU/FC5nlJREA3FmQy7VYzPhCy0tLJOaFtZSgi3Scx2az5\nAJEPP/XKyphY0hF1UFw8dUgVa/NQvXZtgTtnt+8WRcBwDcryKsQIepK4u6xBLYdhR\n+U6zuQ3KcudI3/Ov4glRYem/XjtGBpGlPLdxbT60tPthcBcWDPWbza9FdrrhhRzNR\n3bFxreqQW2j1o+SW55+WoDJ5ZhLsdcoUkJL7Ecex+vrzJD3eI8fiEz2TaWOJwIDAQAB\n-----END PUBLIC KEY-----\n";

    $finalPayload = build_payload($msg->serializeToString(), $publicKey, "\x03");

    die(json_encode(["success" => true, "session_id" => session_id(), "data" => base64_encode($finalPayload)]));

} elseif ($action === "access" || $action === "heartbeat") {
    if (!$response_b64) {
        fail(400, "missing required field: response");
    }

    $responseBytes = base64_decode($response_b64, true);
    if ($responseBytes === false || strlen($responseBytes) === 0) {
        fail(400, "invalid response encoding");
    }

    $sessionPrivKey = $_SESSION['private_key'] ?? null;
    if (!$sessionPrivKey) {
        fail(400, "no active session -- call auth first");
    }

    try {
        $decrypted = decrypt_resp($responseBytes, $sessionPrivKey);
    } catch (\InvalidArgumentException $e) {
        fail(400, "not lunaris generated session");
    } catch (\RuntimeException $e) {
        fail(400, "not lunaris generated session");
    }

    $msg = new AuthenticationResponse();
    $msg->mergeFromString($decrypted);

    $serverPublicKey = $msg->getServerRsaPublicKey();
    if (!$serverPublicKey) {
        fail(400, "broken resp / api needs update");
    }

    $access = new AccessRequest();
    $access->setToken($msg->getToken());

    $type = $action === "access" ? "\x04" : "\x07";
    $finalPayload = build_payload($access->serializeToString(), $serverPublicKey, $type);

    die(json_encode(["success" => true, "data" => base64_encode($finalPayload)]));

} elseif ($action === "refresh") {
    $session_id = isset($input["session_id"]) && is_string($input["session_id"]) ? $input["session_id"] : null;
    $token = isset($input["token"]) && is_string($input["token"]) ? $input["token"] : null;
    $rsid = isset($input["sid"]) && is_string($input["sid"]) ? $input["sid"] : null;
    $game = isset($input["game"]) && is_string($input["game"]) ? $input["game"] : "valo";
    $region = isset($input["region"]) && is_string($input["region"]) ? $input["region"] : "eu";

    if (!$session_id || !$token || !$rsid) {
        fail(400, "missing required fields: session_id, token, sid");
    }

    $ticketDir = __DIR__ . "/tickets";
    if (!is_dir($ticketDir)) {
        mkdir($ticketDir, 0777, true);
    }

    $entry = [
        "session_id" => $session_id,
        "token" => $token,
        "sid" => $rsid,
        "game" => $game,
        "region" => $region,
        "status" => "pending",
        "created_at" => time()
    ];

    file_put_contents(
        $ticketDir . "/" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $session_id) . ".json",
        json_encode($entry)
    );

    die(json_encode(["success" => true, "session_id" => $session_id]));

} elseif ($action === "poll") {
    $session_id = isset($input["session_id"]) && is_string($input["session_id"]) ? $input["session_id"] : null;

    if (!$session_id) {
        fail(400, "missing required field: session_id");
    }

    $ticketFile = __DIR__ . "/tickets/" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $session_id) . ".json";

    if (!file_exists($ticketFile)) {
        fail(404, "session not found");
    }

    $entry = json_decode(file_get_contents($ticketFile), true);

    $status = isset($entry["status"]) ? $entry["status"] : "pending";

    if ($status === "ready" && isset($entry["ticket"])) {
        die(json_encode(["status" => "ready", "ticket" => $entry["ticket"]]));
    } elseif ($status === "failed") {
        $error = isset($entry["error"]) ? $entry["error"] : "unknown error";
        die(json_encode(["status" => "failed", "error" => $error]));
    } else {
        die(json_encode(["status" => "pending"]));
    }

} else {
    fail(400, "unknown action");
}
