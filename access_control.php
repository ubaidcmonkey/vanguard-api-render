<?php

function runtime_dir(): string
{
    $dir = __DIR__ . '/runtime';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function client_ip(): string
{
    $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwardedFor) {
        $parts = explode(',', $forwardedFor);
        return trim($parts[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function whitelist_file(): string
{
    return runtime_dir() . '/whitelist.json';
}

function normalize_ip_entry(string $entry): string
{
    return trim($entry);
}

function is_valid_ip_entry(string $entry): bool
{
    $entry = normalize_ip_entry($entry);
    if ($entry === '') {
        return false;
    }

    if (filter_var($entry, FILTER_VALIDATE_IP)) {
        return true;
    }

    if (!str_contains($entry, '/')) {
        return false;
    }

    [$ip, $prefix] = explode('/', $entry, 2);
    if (!filter_var($ip, FILTER_VALIDATE_IP) || !ctype_digit($prefix)) {
        return false;
    }

    $maxPrefix = str_contains($ip, ':') ? 128 : 32;
    $prefixNumber = (int) $prefix;

    return $prefixNumber >= 0 && $prefixNumber <= $maxPrefix;
}

function load_env_whitelist(): array
{
    $entries = [];
    $envEntries = getenv('API_ALLOWED_IPS');

    if (is_string($envEntries) && trim($envEntries) !== '') {
        foreach (explode(',', $envEntries) as $entry) {
            $entry = normalize_ip_entry($entry);
            if (is_valid_ip_entry($entry)) {
                $entries[] = $entry;
            }
        }
    }

    return array_values(array_unique($entries));
}

function load_saved_whitelist(): array
{
    $entries = [];
    $file = whitelist_file();
    if (is_file($file)) {
        $stored = json_decode((string) file_get_contents($file), true);
        if (is_array($stored)) {
            foreach ($stored as $entry) {
                if (is_string($entry)) {
                    $entry = normalize_ip_entry($entry);
                    if (is_valid_ip_entry($entry)) {
                        $entries[] = $entry;
                    }
                }
            }
        }
    }

    return array_values(array_unique($entries));
}

function load_whitelist(): array
{
    return array_values(array_unique(array_merge(load_env_whitelist(), load_saved_whitelist())));
}

function save_whitelist(array $entries): void
{
    $clean = [];
    foreach ($entries as $entry) {
        if (!is_string($entry)) {
            continue;
        }

        $entry = normalize_ip_entry($entry);
        if (is_valid_ip_entry($entry)) {
            $clean[] = $entry;
        }
    }

    file_put_contents(whitelist_file(), json_encode(array_values(array_unique($clean)), JSON_PRETTY_PRINT), LOCK_EX);
}

function ip_matches_cidr(string $ip, string $cidr): bool
{
    [$range, $prefix] = explode('/', $cidr, 2);
    $ipBytes = @inet_pton($ip);
    $rangeBytes = @inet_pton($range);
    if ($ipBytes === false || $rangeBytes === false || strlen($ipBytes) !== strlen($rangeBytes)) {
        return false;
    }

    $bits = (int) $prefix;
    $fullBytes = intdiv($bits, 8);
    $remainingBits = $bits % 8;

    if ($fullBytes > 0 && substr($ipBytes, 0, $fullBytes) !== substr($rangeBytes, 0, $fullBytes)) {
        return false;
    }

    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

    return (ord($ipBytes[$fullBytes]) & $mask) === (ord($rangeBytes[$fullBytes]) & $mask);
}

function ip_is_allowed(string $ip, array $whitelist): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }

    foreach ($whitelist as $entry) {
        if ($entry === $ip) {
            return true;
        }

        if (str_contains($entry, '/') && ip_matches_cidr($ip, $entry)) {
            return true;
        }
    }

    return false;
}

function api_access_is_allowed(): bool
{
    return ip_is_allowed(client_ip(), load_whitelist());
}

function reject_unlisted_ip(): void
{
    if (api_access_is_allowed()) {
        return;
    }

    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'REJECTED';
    exit;
}
