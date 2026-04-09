<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle ?? 'ChatGPT Monitoring') ?></title>
    <style>
        :root {
            --bg: #f4f6fb;
            --text: #1f2937;
            --muted: #64748b;
            --card: #ffffff;
            --line: #e2e8f0;
            --green: #16a34a;
            --yellow: #ca8a04;
            --red: #dc2626;
            --blue: #2563eb;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: radial-gradient(circle at top right, #dbeafe, var(--bg));
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
        }

        .wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .nav {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .nav a {
            text-decoration: none;
            color: var(--blue);
            padding: 8px 12px;
            background: #eff6ff;
            border-radius: 999px;
            border: 1px solid #bfdbfe;
            font-weight: 600;
        }

        .grid {
            display: grid;
            gap: 14px;
        }

        .grid-4 {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }

        h1, h2, h3 { margin-top: 0; }
        .muted { color: var(--muted); font-size: 14px; }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #334155;
        }

        .badge {
            display: inline-block;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 999px;
            font-weight: 700;
            color: #fff;
        }

        .badge.active { background: var(--green); }
        .badge.expiring_soon { background: var(--yellow); }
        .badge.expired { background: var(--red); }

        .progress {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
            margin-top: 6px;
        }

        .progress > span {
            display: block;
            height: 100%;
            border-radius: 999px;
        }

        .p-green { background: var(--green); }
        .p-yellow { background: var(--yellow); }
        .p-red { background: var(--red); }

        form.inline { display: inline; }
        input, textarea, select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            margin: 4px 0 10px;
            font-size: 14px;
            background: #fff;
        }

        button, .btn {
            border: 0;
            border-radius: 8px;
            padding: 8px 12px;
            font-weight: 600;
            cursor: pointer;
            background: var(--blue);
            color: #fff;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary { background: #475569; }
        .btn-danger { background: var(--red); }

        .flash {
            margin-bottom: 14px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 14px;
        }

        .flash.success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }

        .flash.error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        dialog {
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            max-width: 460px;
            width: 90%;
        }

        @media (max-width: 768px) {
            th, td { font-size: 13px; padding: 8px; }
            .wrap { padding: 14px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <nav class="nav">
        <a href="/">Dashboard</a>
        <a href="/accounts">Account List</a>
        <a href="/telegram">Telegram Settings</a>
    </nav>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="flash success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="flash error"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <?= $this->renderSection('content') ?>
</div>
</body>
</html>
