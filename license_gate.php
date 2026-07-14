<?php

function ensure_app_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function keyauth_config(): array
{
    return [
        'name' => getenv('KEYAUTH_NAME') ?: 'Polaris',
        'ownerid' => getenv('KEYAUTH_OWNERID') ?: 'ro5ZMaI1GQ',
        'version' => getenv('KEYAUTH_VERSION') ?: '1.0',
        'url' => rtrim(getenv('KEYAUTH_URL') ?: 'https://keyauth.win/api/1.3/', '/') . '/',
    ];
}

function keyauth_request(array $params): array
{
    $config = keyauth_config();
    $url = $config['url'] . '?' . http_build_query($params);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ['success' => false, 'message' => 'KeyAuth request failed.'];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'message' => 'Invalid KeyAuth response.'];
    }

    return $decoded;
}

function keyauth_init(): array
{
    $config = keyauth_config();

    return keyauth_request([
        'type' => 'init',
        'ver' => $config['version'],
        'name' => $config['name'],
        'ownerid' => $config['ownerid'],
        'hash' => 'undefined',
        'token' => 'undefined',
        'thash' => 'undefined',
    ]);
}

function keyauth_hwid(): string
{
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $agent = is_string($agent) ? $agent : '';

    return hash('sha256', client_ip() . '|' . $agent);
}

function keyauth_license_login(string $licenseKey): array
{
    $licenseKey = trim($licenseKey);
    if ($licenseKey === '') {
        return ['success' => false, 'message' => 'Enter a license key.'];
    }

    $config = keyauth_config();
    $init = keyauth_init();
    if (empty($init['success']) || empty($init['sessionid']) || !is_string($init['sessionid'])) {
        return [
            'success' => false,
            'message' => isset($init['message']) && is_string($init['message']) ? $init['message'] : 'KeyAuth initialization failed.',
        ];
    }

    return keyauth_request([
        'type' => 'license',
        'key' => $licenseKey,
        'sessionid' => $init['sessionid'],
        'name' => $config['name'],
        'ownerid' => $config['ownerid'],
        'hwid' => keyauth_hwid(),
        'code' => '',
    ]);
}

function license_is_authenticated(): bool
{
    ensure_app_session();

    return !empty($_SESSION['license_authenticated'])
        && isset($_SESSION['license_ip'])
        && $_SESSION['license_ip'] === client_ip();
}

function authenticate_license(string $licenseKey): array
{
    ensure_app_session();

    $result = keyauth_license_login($licenseKey);
    if (!empty($result['success'])) {
        $_SESSION['license_authenticated'] = true;
        $_SESSION['license_ip'] = client_ip();
        $_SESSION['license_login_at'] = time();
    }

    return $result;
}

function logout_license(): void
{
    ensure_app_session();
    unset($_SESSION['license_authenticated'], $_SESSION['license_ip'], $_SESSION['license_login_at']);
}

function require_license_for_json(): void
{
    if (license_is_authenticated()) {
        return;
    }

    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'license required']);
    exit;
}

function e_license(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function render_license_gate(?string $error = null): never
{
    http_response_code(200);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>License Required</title>
    <style>
        :root {
            --bg: #08090b;
            --panel: #12161b;
            --text: #f4f0e8;
            --muted: #a3abb5;
            --line: rgba(255, 255, 255, 0.12);
            --gold: #f2be4b;
            --red: #ff6b6b;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            color: var(--text);
            background: linear-gradient(135deg, rgba(242, 190, 75, 0.14), transparent 36%), var(--bg);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(420px, 100%);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 26px;
            background: var(--panel);
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.42);
        }

        h1 {
            margin: 0 0 10px;
            color: var(--gold);
            font-size: 30px;
            letter-spacing: 0;
        }

        p {
            margin: 0 0 20px;
            color: var(--muted);
            line-height: 1.55;
        }

        label {
            display: grid;
            gap: 8px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
        }

        input, button {
            width: 100%;
            min-height: 46px;
            border-radius: 8px;
            font: inherit;
        }

        input {
            margin-top: 8px;
            border: 1px solid var(--line);
            padding: 0 13px;
            color: var(--text);
            background: #090b0e;
            outline: none;
        }

        button {
            margin-top: 14px;
            border: 0;
            color: #1b1303;
            background: var(--gold);
            font-weight: 900;
            cursor: pointer;
        }

        .error {
            margin-bottom: 14px;
            border: 1px solid rgba(255, 107, 107, 0.42);
            border-radius: 8px;
            padding: 11px 12px;
            color: #ffd0d0;
            background: rgba(255, 107, 107, 0.1);
        }
    </style>
</head>
<body>
    <main>
        <h1>License Required</h1>
        <p>Your public IP is allowed. Enter your KeyAuth license key to continue.</p>
        <?php if ($error): ?>
            <div class="error"><?= e_license($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/">
            <label>
                License key
                <input name="license_key" type="password" autocomplete="off" required autofocus>
            </label>
            <button type="submit">Continue</button>
        </form>
    </main>
</body>
</html>
    <?php
    exit;
}
