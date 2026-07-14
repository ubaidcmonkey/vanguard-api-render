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
            --black: #050505;
            --black-2: #090806;
            --panel: rgba(15, 14, 10, 0.86);
            --panel-strong: rgba(22, 19, 13, 0.94);
            --gold: #f5c451;
            --gold-hot: #ffd86b;
            --gold-deep: #8f6820;
            --text: #fff8e8;
            --muted: #a99f8d;
            --line: rgba(245, 196, 81, 0.24);
            --green: #64e7a0;
            --blue: #71c7ff;
            --red: #ff6a73;
            --shadow: rgba(0, 0, 0, 0.56);
        }

        * {
            box-sizing: border-box;
        }

        html {
            background: var(--black);
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                linear-gradient(120deg, rgba(245, 196, 81, 0.16), transparent 24%),
                linear-gradient(300deg, rgba(113, 199, 255, 0.08), transparent 34%),
                linear-gradient(180deg, #0c0a06 0%, #050505 54%, #020202 100%);
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(rgba(245, 196, 81, 0.052) 1px, transparent 1px),
                linear-gradient(90deg, rgba(245, 196, 81, 0.038) 1px, transparent 1px);
            background-size: 80px 80px;
            mask-image: linear-gradient(to bottom, black 0%, rgba(0, 0, 0, 0.72) 48%, transparent 100%);
        }

        body::after {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                repeating-linear-gradient(0deg, rgba(255, 255, 255, 0.028) 0 1px, transparent 1px 5px),
                linear-gradient(90deg, transparent, rgba(245, 196, 81, 0.055), transparent);
            mix-blend-mode: screen;
            opacity: 0.42;
        }

        #signal-canvas {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            opacity: 0.46;
        }

        main {
            position: relative;
            z-index: 1;
            width: min(1240px, calc(100% - 32px));
            min-height: 100vh;
            margin: 0 auto;
            padding: 24px 0 36px;
            display: grid;
            gap: 18px;
        }

        .topbar {
            min-height: 74px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 18px;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px 16px;
            background: linear-gradient(180deg, rgba(245, 196, 81, 0.11), transparent), rgba(5, 5, 5, 0.72);
            box-shadow: 0 22px 70px var(--shadow);
            backdrop-filter: blur(18px);
        }

        .brand {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(245, 196, 81, 0.58);
            border-radius: 8px;
            color: var(--gold-hot);
            background: linear-gradient(145deg, rgba(245, 196, 81, 0.18), rgba(245, 196, 81, 0.04));
            font-size: 20px;
            font-weight: 950;
            box-shadow: inset 0 0 22px rgba(245, 196, 81, 0.14), 0 0 34px rgba(245, 196, 81, 0.12);
        }

        .brand small {
            display: block;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .brand strong {
            display: block;
            margin-top: 3px;
            color: var(--text);
            font-size: 18px;
            line-height: 1;
        }

        .top-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .chip {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 9px 11px;
            color: var(--text);
            background: rgba(255, 255, 255, 0.045);
            font-size: 12px;
            font-weight: 850;
            text-transform: uppercase;
        }

        .chip.live {
            color: var(--green);
            border-color: rgba(100, 231, 160, 0.36);
            background: rgba(100, 231, 160, 0.08);
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 18px currentColor;
            animation: pulse 1.7s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.58; transform: scale(0.82); }
            50% { opacity: 1; transform: scale(1.18); }
        }

        .hero {
            min-height: 430px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(340px, 0.72fr);
            gap: 18px;
            align-items: stretch;
        }

        .hero-stage,
        .core-panel,
        .panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.075), rgba(255, 255, 255, 0.018)), var(--panel);
            box-shadow: 0 28px 90px var(--shadow);
            backdrop-filter: blur(18px);
        }

        .hero-stage {
            position: relative;
            overflow: hidden;
            padding: 38px;
            display: grid;
            align-content: end;
        }

        .hero-stage::before {
            content: "";
            position: absolute;
            inset: 18px;
            border: 1px solid rgba(245, 196, 81, 0.22);
            border-radius: 8px;
        }

        .hero-stage::after {
            content: "";
            position: absolute;
            right: -130px;
            top: -190px;
            width: 520px;
            height: 520px;
            border: 1px solid rgba(245, 196, 81, 0.34);
            border-radius: 50%;
            background:
                conic-gradient(from 32deg, rgba(245, 196, 81, 0.34), transparent 22%, rgba(113, 199, 255, 0.15), transparent 48%, rgba(245, 196, 81, 0.24), transparent 73%),
                radial-gradient(circle, transparent 0 35%, rgba(245, 196, 81, 0.08) 36% 37%, transparent 38% 100%);
            animation: rotateCore 18s linear infinite;
        }

        @keyframes rotateCore {
            to { transform: rotate(360deg); }
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 780px;
            display: grid;
            gap: 18px;
        }

        .eyebrow {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gold-hot);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            color: var(--gold);
            font-size: 138px;
            line-height: 0.82;
            letter-spacing: 0;
            text-shadow: 0 0 18px rgba(245, 196, 81, 0.18), 0 0 64px rgba(245, 196, 81, 0.18);
        }

        .subtitle {
            max-width: 640px;
            margin: 0;
            color: var(--muted);
            font-size: 17px;
            line-height: 1.65;
        }

        .hero-strip {
            position: relative;
            z-index: 1;
            margin-top: 8px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .strip-item {
            min-height: 76px;
            border: 1px solid rgba(245, 196, 81, 0.18);
            border-radius: 8px;
            padding: 13px;
            background: rgba(5, 5, 5, 0.55);
        }

        .strip-item span {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .strip-item strong {
            display: block;
            margin-top: 10px;
            color: var(--text);
            font-size: 19px;
            line-height: 1;
        }

        .core-panel {
            position: relative;
            overflow: hidden;
            min-height: 430px;
            padding: 24px;
            display: grid;
            align-content: center;
            justify-items: center;
            text-align: center;
        }

        .core-panel::before {
            content: "";
            position: absolute;
            inset: 28px;
            border: 1px solid rgba(245, 196, 81, 0.2);
            border-radius: 50%;
            animation: breatheRing 3.8s ease-in-out infinite;
        }

        .core-panel::after {
            content: "";
            position: absolute;
            width: 280px;
            height: 280px;
            border: 1px dashed rgba(245, 196, 81, 0.36);
            border-radius: 50%;
            animation: rotateCore 12s linear infinite reverse;
        }

        @keyframes breatheRing {
            0%, 100% { transform: scale(0.96); opacity: 0.5; }
            50% { transform: scale(1.04); opacity: 0.92; }
        }

        .core-content {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 12px;
            justify-items: center;
        }

        .core-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .core-number {
            color: var(--gold-hot);
            font-size: 150px;
            font-weight: 950;
            line-height: 0.9;
            text-shadow: 0 0 28px rgba(245, 196, 81, 0.28);
        }

        .core-status {
            min-width: 220px;
            border: 1px solid rgba(100, 231, 160, 0.34);
            border-radius: 8px;
            padding: 12px 14px;
            color: var(--green);
            background: rgba(100, 231, 160, 0.08);
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 0.78fr 1.42fr;
            gap: 18px;
        }

        .panel {
            overflow: hidden;
        }

        .telemetry {
            padding: 22px;
            display: grid;
            gap: 16px;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: start;
        }

        .section-title h2 {
            margin: 0;
            color: var(--text);
            font-size: 18px;
            line-height: 1.2;
        }

        .section-title span {
            color: var(--muted);
            font-size: 12px;
            font-weight: 850;
            text-transform: uppercase;
        }

        .signal-bars {
            display: grid;
            gap: 10px;
        }

        .signal-bar {
            height: 13px;
            overflow: hidden;
            border: 1px solid rgba(245, 196, 81, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.045);
        }

        .signal-bar span {
            display: block;
            width: 72%;
            height: 100%;
            background: linear-gradient(90deg, var(--gold-deep), var(--gold-hot), var(--blue));
            animation: scanBar 2.6s ease-in-out infinite;
        }

        .signal-bar:nth-child(2) span {
            width: 56%;
            animation-delay: 0.22s;
        }

        .signal-bar:nth-child(3) span {
            width: 84%;
            animation-delay: 0.44s;
        }

        @keyframes scanBar {
            0%, 100% { transform: translateX(-18%); opacity: 0.72; }
            50% { transform: translateX(22%); opacity: 1; }
        }

        .telemetry-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            border-top: 1px solid rgba(245, 196, 81, 0.14);
            padding-top: 14px;
        }

        .telemetry-row span {
            color: var(--muted);
            font-size: 12px;
            font-weight: 850;
            text-transform: uppercase;
        }

        .telemetry-row strong {
            color: var(--text);
            font-size: 14px;
        }

        .users-header {
            padding: 20px 22px;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid rgba(245, 196, 81, 0.18);
            background: var(--panel-strong);
        }

        .users-header h2 {
            margin: 0;
            color: var(--text);
            font-size: 20px;
        }

        .users-header span {
            color: var(--muted);
            font-size: 13px;
        }

        .keepalive {
            color: var(--gold-hot);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .user-list {
            display: grid;
        }

        .user-row {
            min-height: 74px;
            padding: 17px 22px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 130px;
            gap: 16px;
            align-items: center;
            border-bottom: 1px solid rgba(245, 196, 81, 0.12);
            background:
                linear-gradient(90deg, rgba(245, 196, 81, 0.08), transparent 42%),
                rgba(255, 255, 255, 0.012);
        }

        .user-row:last-child {
            border-bottom: 0;
        }

        .ip {
            min-width: 0;
            overflow-wrap: anywhere;
            color: var(--text);
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
            font-size: 16px;
        }

        .meta {
            margin-top: 7px;
            color: var(--gold-hot);
            font-size: 12px;
            font-weight: 850;
            text-transform: uppercase;
        }

        .seen {
            justify-self: end;
            width: 100%;
            border: 1px solid rgba(245, 196, 81, 0.18);
            border-radius: 8px;
            padding: 10px 11px;
            color: var(--green);
            background: rgba(100, 231, 160, 0.06);
            font-size: 13px;
            font-weight: 900;
            white-space: nowrap;
            text-align: center;
        }

        .empty {
            padding: 42px 22px;
            color: var(--muted);
            line-height: 1.65;
        }

        @media (max-width: 980px) {
            .topbar,
            .hero,
            .dashboard {
                grid-template-columns: 1fr;
            }

            .top-actions {
                justify-content: flex-start;
            }

            h1 {
                font-size: 104px;
            }
        }

        @media (max-width: 700px) {
            main {
                width: min(100% - 24px, 1240px);
                padding-top: 16px;
            }

            .hero-stage,
            .core-panel {
                min-height: 360px;
                padding: 24px;
            }

            h1 {
                font-size: 70px;
            }

            .subtitle {
                font-size: 15px;
            }

            .hero-strip {
                grid-template-columns: 1fr;
            }

            .core-number {
                font-size: 112px;
            }

            .user-row {
                grid-template-columns: 1fr;
            }

            .seen {
                justify-self: start;
                width: fit-content;
            }
        }
    </style>
</head>
<body>
    <canvas id="signal-canvas" aria-hidden="true"></canvas>
    <main>
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark">E</div>
                <div>
                    <small>Vanguard API</small>
                    <strong>ECHO Command</strong>
                </div>
            </div>
            <div class="top-actions">
                <div class="chip live"><span class="dot"></span>Online</div>
                <div class="chip">Refresh 30s</div>
                <div class="chip">Window 15m</div>
            </div>
        </header>

        <section class="hero">
            <div class="hero-stage">
                <div class="hero-content">
                    <div class="eyebrow"><span class="dot"></span> Live Gateway Monitor</div>
                    <h1>ECHO</h1>
                    <p class="subtitle">A black-and-gold command view for gateway activity, active request origins, and service heartbeat across the Vanguard API.</p>
                    <div class="hero-strip" aria-hidden="true">
                        <div class="strip-item">
                            <span>Mode</span>
                            <strong>Gateway</strong>
                        </div>
                        <div class="strip-item">
                            <span>Heartbeat</span>
                            <strong>Armed</strong>
                        </div>
                        <div class="strip-item">
                            <span>Signal</span>
                            <strong>Live</strong>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="core-panel">
                <div class="core-content">
                    <div class="core-label">Active Users</div>
                    <div class="core-number"><?= count($activeUsers) ?></div>
                    <div class="core-status">Gateway Online</div>
                </div>
            </aside>
        </section>

        <section class="dashboard">
            <aside class="panel telemetry">
                <div class="section-title">
                    <div>
                        <h2>Signal Telemetry</h2>
                        <span>Live pulse</span>
                    </div>
                </div>
                <div class="signal-bars" aria-hidden="true">
                    <div class="signal-bar"><span></span></div>
                    <div class="signal-bar"><span></span></div>
                    <div class="signal-bar"><span></span></div>
                </div>
                <div class="telemetry-row">
                    <span>Response</span>
                    <strong>HTTP 200</strong>
                </div>
                <div class="telemetry-row">
                    <span>Refresh</span>
                    <strong>30 seconds</strong>
                </div>
                <div class="telemetry-row">
                    <span>Active window</span>
                    <strong>15 minutes</strong>
                </div>
            </aside>

            <section class="panel users">
                <div class="users-header">
                    <div>
                        <h2>Active IPs</h2>
                        <span>Gateway requests appear here automatically</span>
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
        let flashes = [];

        function resizeCanvas() {
            const ratio = window.devicePixelRatio || 1;
            canvas.width = Math.floor(window.innerWidth * ratio);
            canvas.height = Math.floor(window.innerHeight * ratio);
            canvas.style.width = window.innerWidth + 'px';
            canvas.style.height = window.innerHeight + 'px';
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            points = Array.from({ length: 58 }, () => ({
                x: Math.random() * window.innerWidth,
                y: Math.random() * window.innerHeight,
                vx: (Math.random() - 0.5) * 0.46,
                vy: (Math.random() - 0.5) * 0.46
            }));
            flashes = Array.from({ length: 7 }, () => ({
                x: Math.random() * window.innerWidth,
                y: Math.random() * window.innerHeight,
                r: 18 + Math.random() * 80,
                a: Math.random() * 0.22
            }));
        }

        function drawSignals() {
            ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);

            for (const flash of flashes) {
                flash.r += 0.18;
                flash.a -= 0.0015;
                if (flash.a <= 0) {
                    flash.x = Math.random() * window.innerWidth;
                    flash.y = Math.random() * window.innerHeight;
                    flash.r = 18 + Math.random() * 80;
                    flash.a = 0.12 + Math.random() * 0.2;
                }

                ctx.beginPath();
                ctx.strokeStyle = 'rgba(245, 196, 81,' + flash.a + ')';
                ctx.lineWidth = 1;
                ctx.arc(flash.x, flash.y, flash.r, 0, Math.PI * 2);
                ctx.stroke();
            }

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
                    if (distance < 150) {
                        ctx.strokeStyle = 'rgba(245, 196, 81,' + (0.18 - distance / 1150) + ')';
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.moveTo(a.x, a.y);
                        ctx.lineTo(b.x, b.y);
                        ctx.stroke();
                    }
                }
            }

            ctx.fillStyle = 'rgba(255, 216, 107, 0.82)';
            for (const point of points) {
                ctx.beginPath();
                ctx.arc(point.x, point.y, 1.65, 0, Math.PI * 2);
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
