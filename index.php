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
            --bg: #07090d;
            --panel: rgba(14, 18, 24, 0.84);
            --panel-strong: rgba(20, 26, 34, 0.94);
            --text: #f5f7fb;
            --muted: #9099a8;
            --line: rgba(255, 255, 255, 0.12);
            --gold: #f3c35b;
            --teal: #55d6c2;
            --blue: #7aa7ff;
            --red: #ff6f79;
            --shadow: rgba(0, 0, 0, 0.42);
        }

        * {
            box-sizing: border-box;
        }

        html {
            background: var(--bg);
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                linear-gradient(130deg, rgba(85, 214, 194, 0.13), transparent 28%),
                linear-gradient(310deg, rgba(243, 195, 91, 0.11), transparent 34%),
                linear-gradient(180deg, #0b1119 0%, #07090d 48%, #050609 100%);
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.026) 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: linear-gradient(to bottom, rgba(0, 0, 0, 0.88), transparent 78%);
        }

        #signal-canvas {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            opacity: 0.34;
        }

        main {
            position: relative;
            z-index: 1;
            width: min(1180px, calc(100% - 32px));
            min-height: 100vh;
            margin: 0 auto;
            padding: 34px 0;
            display: grid;
            align-content: start;
            gap: 24px;
        }

        .hero {
            min-height: 310px;
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.9fr);
            gap: 22px;
            align-items: stretch;
            padding: 24px 0 0;
            border-bottom: 1px solid var(--line);
        }

        .hero-copy {
            display: grid;
            align-content: center;
            gap: 18px;
            padding-bottom: 24px;
        }

        .eyebrow {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--teal);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .pulse {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--teal);
            box-shadow: 0 0 22px var(--teal);
            animation: breathe 1.8s ease-in-out infinite;
        }

        @keyframes breathe {
            0%, 100% { transform: scale(0.82); opacity: 0.68; }
            50% { transform: scale(1.15); opacity: 1; }
        }

        h1 {
            margin: 0;
            color: var(--text);
            font-size: 112px;
            line-height: 0.88;
            letter-spacing: 0;
            text-shadow: 0 0 30px rgba(122, 167, 255, 0.22);
        }

        .subtitle {
            max-width: 720px;
            margin: 0;
            color: var(--muted);
            font-size: 17px;
            line-height: 1.6;
        }

        .hero-panel {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.07), transparent),
                var(--panel);
            box-shadow: 0 30px 90px var(--shadow);
        }

        .hero-panel::before {
            content: "";
            position: absolute;
            inset: 18px;
            border: 1px solid rgba(85, 214, 194, 0.28);
            border-radius: 8px;
        }

        .radar {
            position: absolute;
            width: 250px;
            height: 250px;
            right: 34px;
            top: 30px;
            border: 1px solid rgba(85, 214, 194, 0.26);
            border-radius: 50%;
            background:
                linear-gradient(90deg, transparent 49.5%, rgba(85, 214, 194, 0.22) 50%, transparent 50.5%),
                linear-gradient(0deg, transparent 49.5%, rgba(85, 214, 194, 0.22) 50%, transparent 50.5%),
                radial-gradient(circle, transparent 29%, rgba(85, 214, 194, 0.16) 30%, transparent 31%, transparent 59%, rgba(85, 214, 194, 0.16) 60%, transparent 61%);
        }

        .radar::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: conic-gradient(from 0deg, rgba(85, 214, 194, 0.55), rgba(85, 214, 194, 0.02) 54deg, transparent 55deg);
            animation: sweep 4.5s linear infinite;
        }

        @keyframes sweep {
            to { transform: rotate(360deg); }
        }

        .hero-metrics {
            position: absolute;
            inset: auto 22px 22px 22px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .mini {
            min-height: 82px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 13px;
            background: rgba(5, 7, 10, 0.58);
        }

        .mini span {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .mini strong {
            display: block;
            margin-top: 10px;
            color: var(--text);
            font-size: 22px;
            line-height: 1;
        }

        .grid {
            display: grid;
            grid-template-columns: 0.82fr 1.5fr;
            gap: 18px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.055), transparent), var(--panel);
            box-shadow: 0 24px 70px var(--shadow);
            backdrop-filter: blur(18px);
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
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .stat-value {
            color: var(--gold);
            font-size: 72px;
            font-weight: 900;
            line-height: 1;
        }

        .status-pill {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px 13px;
            color: var(--teal);
            background: rgba(85, 214, 194, 0.08);
            font-size: 13px;
            font-weight: 800;
        }

        .stat-bars {
            display: grid;
            gap: 8px;
        }

        .bar {
            height: 8px;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
        }

        .bar span {
            display: block;
            width: 68%;
            height: 100%;
            background: linear-gradient(90deg, var(--teal), var(--blue), var(--gold));
            animation: barPulse 2.8s ease-in-out infinite;
        }

        @keyframes barPulse {
            0%, 100% { transform: translateX(-8%); opacity: 0.74; }
            50% { transform: translateX(22%); opacity: 1; }
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
            background: var(--panel-strong);
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
            grid-template-columns: minmax(0, 1fr) 120px;
            gap: 14px;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background: linear-gradient(90deg, rgba(85, 214, 194, 0.04), transparent);
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
            color: var(--blue);
            font-size: 12px;
            text-transform: uppercase;
        }

        .seen {
            color: var(--teal);
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
            text-align: right;
        }

        .keepalive {
            color: var(--teal);
            font-size: 12px;
            font-weight: 800;
        }

        .empty {
            padding: 34px 22px;
            color: var(--muted);
            line-height: 1.6;
        }

        @media (max-width: 920px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .hero-panel {
                min-height: 280px;
            }

            h1 {
                font-size: 82px;
            }
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

            .hero-metrics {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 62px;
            }

            .user-row {
                grid-template-columns: 1fr;
            }

            .seen {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <canvas id="signal-canvas" aria-hidden="true"></canvas>
    <main>
        <section class="hero">
            <div class="hero-copy">
                <div class="eyebrow"><span class="pulse"></span> Live Gateway Monitor</div>
                <h1>ECHO</h1>
                <p class="subtitle">A real-time command view for gateway activity, active request origins, and service heartbeat across the Vanguard API.</p>
            </div>
            <div class="hero-panel" aria-hidden="true">
                <div class="radar"></div>
                <div class="hero-metrics">
                    <div class="mini">
                        <span>Refresh</span>
                        <strong>30s</strong>
                    </div>
                    <div class="mini">
                        <span>Window</span>
                        <strong>15m</strong>
                    </div>
                    <div class="mini">
                        <span>Signal</span>
                        <strong>Live</strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid">
            <aside class="panel stat">
                <div class="stat-label">Active Users</div>
                <div class="stat-value"><?= count($activeUsers) ?></div>
                <div class="status-pill">Gateway Online</div>
                <div class="stat-bars" aria-hidden="true">
                    <div class="bar"><span></span></div>
                    <div class="bar"><span></span></div>
                    <div class="bar"><span></span></div>
                </div>
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
        const canvas = document.getElementById('signal-canvas');
        const ctx = canvas.getContext('2d');
        let points = [];

        function resizeCanvas() {
            canvas.width = window.innerWidth * window.devicePixelRatio;
            canvas.height = window.innerHeight * window.devicePixelRatio;
            canvas.style.width = window.innerWidth + 'px';
            canvas.style.height = window.innerHeight + 'px';
            ctx.setTransform(window.devicePixelRatio, 0, 0, window.devicePixelRatio, 0, 0);
            points = Array.from({ length: 38 }, () => ({
                x: Math.random() * window.innerWidth,
                y: Math.random() * window.innerHeight,
                vx: (Math.random() - 0.5) * 0.34,
                vy: (Math.random() - 0.5) * 0.34
            }));
        }

        function drawSignals() {
            ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);
            ctx.lineWidth = 1;

            for (const point of points) {
                point.x += point.vx;
                point.y += point.vy;

                if (point.x < 0 || point.x > window.innerWidth) {
                    point.vx *= -1;
                }

                if (point.y < 0 || point.y > window.innerHeight) {
                    point.vy *= -1;
                }
            }

            for (let i = 0; i < points.length; i++) {
                for (let j = i + 1; j < points.length; j++) {
                    const a = points[i];
                    const b = points[j];
                    const dx = a.x - b.x;
                    const dy = a.y - b.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    if (distance < 170) {
                        ctx.strokeStyle = 'rgba(85, 214, 194,' + (0.14 - distance / 1400) + ')';
                        ctx.beginPath();
                        ctx.moveTo(a.x, a.y);
                        ctx.lineTo(b.x, b.y);
                        ctx.stroke();
                    }
                }
            }

            ctx.fillStyle = 'rgba(122, 167, 255, 0.62)';
            for (const point of points) {
                ctx.beginPath();
                ctx.arc(point.x, point.y, 1.7, 0, Math.PI * 2);
                ctx.fill();
            }

            requestAnimationFrame(drawSignals);
        }

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

        resizeCanvas();
        drawSignals();
        window.addEventListener('resize', resizeCanvas);
        pingGateway();
        setInterval(pingGateway, 240000);
    </script>
</body>
</html>
