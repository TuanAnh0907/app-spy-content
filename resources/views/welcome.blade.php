<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — Spy Service</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: #0a0f1e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #e2e8f0;
            overflow: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(20, 184, 166, 0.12) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(6, 182, 212, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 60% 80%, rgba(16, 185, 129, 0.06) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Grid overlay */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(20, 184, 166, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(20, 184, 166, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .container {
            text-align: center;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(20, 184, 166, 0.1);
            border: 1px solid rgba(20, 184, 166, 0.3);
            color: #14b8a6;
            padding: 6px 16px;
            border-radius: 100px;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 2rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #14b8a6;
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
            box-shadow: 0 0 0 0 rgba(20, 184, 166, 0.5);
        }

        @keyframes pulse {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(20, 184, 166, 0.5);
            }

            50% {
                box-shadow: 0 0 0 6px rgba(20, 184, 166, 0);
            }
        }

        /* Logo / Icon */
        .icon-wrap {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #0d9488, #0891b2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: 0 20px 40px rgba(20, 184, 166, 0.25);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        h1 {
            font-size: clamp(1.8rem, 4vw, 2.6rem);
            font-weight: 700;
            background: linear-gradient(135deg, #e2e8f0 0%, #5eead4 50%, #67e8f9 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .subtitle {
            font-size: 0.95rem;
            color: #6b7280;
            margin-bottom: 2.5rem;
        }

        /* Info cards */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            max-width: 540px;
            margin: 0 auto 2.5rem;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 14px;
            padding: 1rem 1.2rem;
            text-align: left;
            transition: border-color 0.2s, background 0.2s;
        }

        .info-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(20, 184, 166, 0.3);
        }

        .info-label {
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #5eead4;
        }

        .info-value.green {
            color: #34d399;
        }

        .info-value.blue {
            color: #67e8f9;
        }

        /* Footer */
        .footer {
            font-size: 0.78rem;
            color: #374151;
        }

        .footer span {
            color: #4b5563;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="status-badge">
            <span class="status-dot"></span>
            Server đang hoạt động
        </div>

        <div class="icon-wrap">🕵️</div>

        <h1>{{ config('app.name') }}</h1>
        <p class="subtitle">Spy & Crawler Service</p>

        <div class="info-grid">
            <div class="info-card">
                <div class="info-label">Trạng thái</div>
                <div class="info-value green">✓ Online</div>
            </div>
            <div class="info-card">
                <div class="info-label">Môi trường</div>
                <div class="info-value blue">{{ ucfirst(app()->environment()) }}</div>
            </div>
            <div class="info-card">
                <div class="info-label">Laravel</div>
                <div class="info-value">v{{ app()->version() }}</div>
            </div>
            <div class="info-card">
                <div class="info-label">PHP</div>
                <div class="info-value">{{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}</div>
            </div>
        </div>

        <p class="footer">
            &copy; {{ date('Y') }} <span>{{ config('app.name') }}</span> — All rights reserved
        </p>
    </div>
</body>

</html>
