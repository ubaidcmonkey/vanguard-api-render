<?php
http_response_code(200);

$activeWindow = 900;
$now = time();
$activeUsers = [];
$activeFile = __DIR__ . '/runtime/active_users.json';

if (is_file($activeFile)) {
    $users = json_decode((string) file_get_contents($activeFile), true);
    if (is_array($users)) {
        foreach ($users as $user) {
            if (!is_array($user)) {
                continue;
            }

            $lastSeen = isset($user['last_seen']) && is_int($user['last_seen']) ? $user['last_seen'] : 0;
            if ($lastSeen >= $now - $activeWindow) {
                $activeUsers[] = $user;
            }
        }
    }
}

usort($activeUsers, static function (array $a, array $b): int {
    return ($b['last_seen'] ?? 0) <=> ($a['last_seen'] ?? 0);
});

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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>ECHO</title>
    <style>
        :root {
            --gold: #f5c451;
            --gold-soft: #ffd978;
            --black: #050505;
            --panel: #111113;
            --panel-2: #181816;
            --text: #f6f0df;
            --muted: #9d9686;
            --line: rgba(245, 196, 81, 0.26);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                linear-gradient(135deg, rgba(245, 196, 81, 0.14), transparent 30%),
                radial-gradient(circle at 80% 10%, rgba(245, 196, 81, 0.18), transparent 30%),
                var(--black);
        }

        .scanlines {
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image: linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px);
            background-size: 100% 5px;
            opacity: 0.18;
        }

        main {
            width: min(1120px, calc(100% - 32px));
            min-height: 100vh;
            margin: 0 auto;
            padding: 48px 0;
            display: grid;
            align-content: center;
            gap: 24px;
        }

        .hero {
            display: grid;
            gap: 18px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--line);
        }

        .eyebrow {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gold-soft);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .pulse {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 22px var(--gold);
        }

        h1 {
            margin: 0;
            color: var(--gold);
            font-size: clamp(64px, 12vw, 148px);
            line-height: 0.86;
            letter-spacing: 0;
            text-shadow: 0 0 26px rgba(245, 196, 81, 0.24);
        }

        .subtitle {
            max-width: 720px;
            margin: 0;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.6;
        }

        .grid {
            display: grid;
            grid-template-columns: 0.9fr 1.5fr;
            gap: 18px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.035), transparent), var(--panel);
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.42);
        }

        .stat {
            padding: 26px;
            display: grid;
            gap: 18px;
            align-content: start;
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .stat-value {
            color: var(--gold);
            font-size: 72px;
            font-weight: 900;
            line-height: 1;
        }

        .status-pill {
            width: fit-content;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 9px 13px;
            color: var(--gold-soft);
            background: rgba(245, 196, 81, 0.08);
            font-size: 13px;
            font-weight: 800;
        }

        .users {
            overflow: hidden;
        }

        .users-header {
            padding: 20px 22px;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid var(--line);
            background: var(--panel-2);
        }

        .users-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .users-header span {
            color: var(--muted);
            font-size: 13px;
        }

        .user-list {
            display: grid;
        }

        .user-row {
            min-height: 66px;
            padding: 16px 22px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            border-bottom: 1px solid rgba(245, 196, 81, 0.12);
        }

        .user-row:last-child {
            border-bottom: 0;
        }

        .ip {
            min-width: 0;
            overflow-wrap: anywhere;
            color: var(--text);
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
            font-size: 15px;
        }

        .meta {
            margin-top: 6px;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
        }

        .seen {
            color: var(--gold-soft);
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .keepalive {
            color: var(--muted);
            font-size: 12px;
        }

        .admin-link {
            width: fit-content;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--gold-soft);
            background: rgba(245, 196, 81, 0.08);
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .empty {
            padding: 34px 22px;
            color: var(--muted);
            line-height: 1.6;
        }

        @media (max-width: 760px) {
            main {
                padding: 32px 0;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .stat-value {
                font-size: 58px;
            }

            .user-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="scanlines"></div>
    <main>
        <section class="hero">
            <div class="eyebrow"><span class="pulse"></span> Live Gateway Monitor</div>
            <h1>ECHO</h1>
            <p class="subtitle">Vanguard API status panel tracking recent gateway activity and live player connections.</p>
            <a class="admin-link" href="/admin.php">Admin Panel</a>
        </section>

        <section class="grid">
            <aside class="panel stat">
                <div class="stat-label">Active Users</div>
                <div class="stat-value"><?= count($activeUsers) ?></div>
                <div class="status-pill">Gateway Online</div>
            </aside>

            <section class="panel users">
                <div class="users-header">
                    <div>
                        <h2>Active IPs</h2>
                        <span>Updated every 30 seconds</span>
                    </div>
                    <span class="keepalive" id="keepalive">Keep-alive armed</span>
                </div>

                <div class="user-list">
                    <?php if (!$activeUsers): ?>
                        <div class="empty">No active API users detected yet. New gateway requests will appear here automatically.</div>
                    <?php endif; ?>

                    <?php foreach ($activeUsers as $user): ?>
                        <?php
                            $ip = isset($user['ip']) && is_string($user['ip']) ? $user['ip'] : 'unknown';
                            $action = isset($user['action']) && is_string($user['action']) ? $user['action'] : 'unknown';
                            $game = isset($user['game']) && is_string($user['game']) ? $user['game'] : 'unknown';
                            $lastSeen = isset($user['last_seen']) && is_int($user['last_seen']) ? $user['last_seen'] : $now;
                        ?>
                        <div class="user-row">
                            <div>
                                <div class="ip"><?= e($ip) ?></div>
                                <div class="meta"><?= e($action) ?> / <?= e($game) ?></div>
                            </div>
                            <div class="seen"><?= e(seen_label($lastSeen, $now)) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>
    </main>
    <script>
        const keepalive = document.getElementById('keepalive');

        async function pingGateway() {
            try {
                await fetch('/health.php?keepalive=' + Date.now(), {
                    cache: 'no-store',
                    credentials: 'same-origin'
                });

                keepalive.textContent = 'Keep-alive active';
            } catch (error) {
                keepalive.textContent = 'Keep-alive retrying';
            }
        }

        pingGateway();
        setInterval(pingGateway, 240000);
    </script>
</body>
</html>
