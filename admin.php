<?php
session_start();
require_once __DIR__ . '/access_control.php';

$adminPassword = getenv('ADMIN_PASSWORD');
$adminReady = is_string($adminPassword) && $adminPassword !== '';
$message = '';
$error = '';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function seen_label(int $lastSeen, int $now): string
{
    $seconds = max(0, $now - $lastSeen);
    if ($seconds < 60) {
        return 'just now';
    }

    $minutes = (int) floor($seconds / 60);
    return $minutes . ' min ago';
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: /admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminReady) {
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'login') {
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        if (hash_equals($adminPassword, $password)) {
            $_SESSION['admin_authenticated'] = true;
            header('Location: /admin.php');
            exit;
        }

        $error = 'Invalid admin password.';
    } elseif (!empty($_SESSION['admin_authenticated'])) {
        if (!csrf_is_valid()) {
            $error = 'Security check failed. Refresh and try again.';
        } else {
            $savedWhitelist = load_saved_whitelist();
            $whitelist = load_whitelist();

            if ($action === 'add_ip') {
                $entry = isset($_POST['ip_entry']) && is_string($_POST['ip_entry']) ? normalize_ip_entry($_POST['ip_entry']) : '';
                if (!is_valid_ip_entry($entry)) {
                    $error = 'Enter a valid IP address or CIDR range.';
                } elseif (in_array($entry, $whitelist, true)) {
                    $message = 'That entry is already allowed.';
                } else {
                    $savedWhitelist[] = $entry;
                    save_whitelist($savedWhitelist);
                    $message = 'Whitelist updated.';
                }
            } elseif ($action === 'remove_ip') {
                $entry = isset($_POST['ip_entry']) && is_string($_POST['ip_entry']) ? normalize_ip_entry($_POST['ip_entry']) : '';
                save_whitelist(array_values(array_filter($savedWhitelist, static fn (string $item): bool => $item !== $entry)));
                $message = 'Whitelist entry removed.';
            } else {
                $error = 'Unknown admin action.';
            }
        }
    }
}

$authenticated = $adminReady && !empty($_SESSION['admin_authenticated']);
$now = time();
$currentIp = client_ip();
$envWhitelist = load_env_whitelist();
$savedWhitelist = load_saved_whitelist();
$whitelist = load_whitelist();
$currentIpAllowed = ip_is_allowed($currentIp, $whitelist);
$activeUsers = [];
$activeFile = runtime_dir() . '/active_users.json';

if (is_file($activeFile)) {
    $users = json_decode((string) file_get_contents($activeFile), true);
    if (is_array($users)) {
        foreach ($users as $user) {
            if (!is_array($user)) {
                continue;
            }

            $lastSeen = isset($user['last_seen']) && is_int($user['last_seen']) ? $user['last_seen'] : 0;
            if ($lastSeen >= $now - 900) {
                $activeUsers[] = $user;
            }
        }
    }
}

usort($activeUsers, static function (array $a, array $b): int {
    return ($b['last_seen'] ?? 0) <=> ($a['last_seen'] ?? 0);
});
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ECHO Admin</title>
    <style>
        :root {
            --bg: #08090b;
            --panel: #111418;
            --panel-strong: #171c22;
            --text: #f4f0e8;
            --muted: #9aa3ad;
            --line: rgba(255, 255, 255, 0.1);
            --gold: #f2be4b;
            --green: #5ce3a0;
            --red: #ff6b6b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            background:
                linear-gradient(135deg, rgba(242, 190, 75, 0.12), transparent 32%),
                linear-gradient(220deg, rgba(92, 227, 160, 0.08), transparent 28%),
                var(--bg);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        a {
            color: inherit;
        }

        main {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            padding: 38px 0;
        }

        header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            padding-bottom: 22px;
            border-bottom: 1px solid var(--line);
        }

        h1, h2, p {
            margin: 0;
        }

        h1 {
            color: var(--gold);
            font-size: clamp(42px, 8vw, 92px);
            line-height: 0.95;
            letter-spacing: 0;
        }

        h2 {
            font-size: 18px;
        }

        .kicker {
            margin-bottom: 10px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .top-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button, button {
            min-height: 40px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--text);
            background: var(--panel-strong);
            font: inherit;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        button.primary {
            border-color: rgba(242, 190, 75, 0.52);
            color: #1b1303;
            background: var(--gold);
        }

        .grid {
            display: grid;
            grid-template-columns: 0.8fr 1.2fr;
            gap: 18px;
            margin-top: 22px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(17, 20, 24, 0.88);
            box-shadow: 0 28px 80px rgba(0, 0, 0, 0.34);
            overflow: hidden;
        }

        .panel-body {
            padding: 22px;
        }

        .stack {
            display: grid;
            gap: 14px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .stat {
            min-height: 112px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel-strong);
            display: grid;
            align-content: space-between;
        }

        .stat span {
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .stat strong {
            color: var(--gold);
            font-size: 36px;
            line-height: 1;
        }

        .status {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            gap: 8px;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 8px 11px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
        }

        .status.ok {
            color: var(--green);
        }

        .status.bad {
            color: var(--red);
        }

        input {
            width: 100%;
            min-height: 44px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px 12px;
            color: var(--text);
            background: #0c0f12;
            font: inherit;
        }

        form.inline {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
        }

        .notice {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px 14px;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.04);
        }

        .notice.success {
            border-color: rgba(92, 227, 160, 0.35);
            color: var(--green);
        }

        .notice.error {
            border-color: rgba(255, 107, 107, 0.45);
            color: var(--red);
        }

        .table {
            display: grid;
        }

        .row {
            min-height: 58px;
            padding: 14px 18px;
            border-top: 1px solid var(--line);
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
        }

        .row:first-child {
            border-top: 0;
        }

        .mono {
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
            overflow-wrap: anywhere;
        }

        .meta {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
        }

        .empty {
            padding: 24px 18px;
            color: var(--muted);
            line-height: 1.5;
        }

        .login {
            max-width: 460px;
            margin: 44px auto 0;
        }

        @media (max-width: 820px) {
            header, .grid, form.inline, .stats {
                grid-template-columns: 1fr;
            }

            header {
                display: grid;
            }

            .row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main>
        <header>
            <div>
                <div class="kicker">Gateway Control</div>
                <h1>ECHO Admin</h1>
            </div>
            <nav class="top-actions">
                <a class="button" href="/">Monitor</a>
                <?php if ($authenticated): ?>
                    <a class="button" href="/admin.php?logout=1">Sign out</a>
                <?php endif; ?>
            </nav>
        </header>

        <?php if (!$adminReady): ?>
            <section class="panel login">
                <div class="panel-body stack">
                    <h2>Admin is not configured</h2>
                    <p class="notice error">Set the Render environment variable ADMIN_PASSWORD, then redeploy. The admin panel stays locked until that exists.</p>
                </div>
            </section>
        <?php elseif (!$authenticated): ?>
            <section class="panel login">
                <div class="panel-body stack">
                    <h2>Sign in</h2>
                    <?php if ($error): ?>
                        <p class="notice error"><?= e($error) ?></p>
                    <?php endif; ?>
                    <form class="stack" method="post" action="/admin.php">
                        <input type="hidden" name="action" value="login">
                        <label>
                            <span class="kicker">Admin Password</span>
                            <input type="password" name="password" autocomplete="current-password" required>
                        </label>
                        <button class="primary" type="submit">Open Admin</button>
                    </form>
                </div>
            </section>
        <?php else: ?>
            <section class="stats" aria-label="Gateway stats">
                <div class="stat">
                    <span>Active Users</span>
                    <strong><?= count($activeUsers) ?></strong>
                </div>
                <div class="stat">
                    <span>Allowed Entries</span>
                    <strong><?= count($whitelist) ?></strong>
                </div>
                <div class="stat">
                    <span>Your Access</span>
                    <strong><?= $currentIpAllowed ? 'OK' : 'NO' ?></strong>
                </div>
            </section>

            <section class="grid">
                <aside class="panel">
                    <div class="panel-body stack">
                        <h2>Whitelist</h2>
                        <?php if ($message): ?>
                            <p class="notice success"><?= e($message) ?></p>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <p class="notice error"><?= e($error) ?></p>
                        <?php endif; ?>
                        <p class="status <?= $currentIpAllowed ? 'ok' : 'bad' ?>">
                            Current IP: <?= e($currentIp) ?>
                        </p>
                        <form class="inline" method="post" action="/admin.php">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="add_ip">
                            <input name="ip_entry" placeholder="IP or CIDR, e.g. 203.0.113.10" required>
                            <button class="primary" type="submit">Allow</button>
                        </form>
                        <form method="post" action="/admin.php">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="add_ip">
                            <input type="hidden" name="ip_entry" value="<?= e($currentIp) ?>">
                            <button type="submit">Allow Current IP</button>
                        </form>
                    </div>
                    <div class="table">
                        <?php if (!$whitelist): ?>
                            <div class="empty">No IPs are allowed yet. Gateway requests will return 403 until an IP is added or API_ALLOWED_IPS is set.</div>
                        <?php endif; ?>

                        <?php foreach ($whitelist as $entry): ?>
                            <?php $isEnvEntry = in_array($entry, $envWhitelist, true) && !in_array($entry, $savedWhitelist, true); ?>
                            <div class="row">
                                <div>
                                    <div class="mono"><?= e($entry) ?></div>
                                    <?php if ($isEnvEntry): ?>
                                        <div class="meta">From API_ALLOWED_IPS</div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isEnvEntry): ?>
                                    <span class="status">Env</span>
                                <?php else: ?>
                                    <form method="post" action="/admin.php">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="remove_ip">
                                        <input type="hidden" name="ip_entry" value="<?= e($entry) ?>">
                                        <button type="submit">Remove</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <section class="panel">
                    <div class="panel-body stack">
                        <h2>Live Gateway Activity</h2>
                        <p class="status ok">Gateway Online</p>
                    </div>
                    <div class="table">
                        <?php if (!$activeUsers): ?>
                            <div class="empty">No active API users detected in the last 15 minutes.</div>
                        <?php endif; ?>

                        <?php foreach ($activeUsers as $user): ?>
                            <?php
                                $ip = isset($user['ip']) && is_string($user['ip']) ? $user['ip'] : 'unknown';
                                $action = isset($user['action']) && is_string($user['action']) ? $user['action'] : 'unknown';
                                $game = isset($user['game']) && is_string($user['game']) ? $user['game'] : 'unknown';
                                $lastSeen = isset($user['last_seen']) && is_int($user['last_seen']) ? $user['last_seen'] : $now;
                            ?>
                            <div class="row">
                                <div>
                                    <div class="mono"><?= e($ip) ?></div>
                                    <div class="meta"><?= e($action) ?> / <?= e($game) ?></div>
                                </div>
                                <strong><?= e(seen_label($lastSeen, $now)) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
