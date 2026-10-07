<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopee Authorization | W68</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f4f6f5; color: #1d2a24; }
        .topbar { background: #173f2d; color: #fff; padding: 18px 24px; }
        .topbar h1 { margin: 0; font-size: 22px; }
        .wrap { max-width: 900px; margin: 28px auto; padding: 0 18px; }
        .card { background: #fff; border: 1px solid #dce3df; border-radius: 14px; padding: 24px; box-shadow: 0 6px 18px rgba(0,0,0,.05); margin-bottom: 18px; }
        .status { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; font-weight: 700; font-size: 13px; }
        .ok { background: #e6f7ec; color: #176c38; }
        .bad { background: #fdeaea; color: #a12424; }
        .warn { background: #fff4d6; color: #7d5a00; }
        .grid { display: grid; grid-template-columns: 190px 1fr; gap: 12px 18px; margin-top: 22px; }
        .label { color: #607068; font-weight: 700; }
        .value { overflow-wrap: anywhere; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        .btn { display: inline-block; border: 0; border-radius: 9px; padding: 12px 17px; font-weight: 700; text-decoration: none; cursor: pointer; font-size: 14px; }
        .primary { background: #651b25; color: #ffd94a; }
        .secondary { background: #173f2d; color: #fff; }
        .muted { background: #eef1ef; color: #2f4238; }
        .alert { border-radius: 10px; padding: 13px 15px; margin-bottom: 16px; font-weight: 600; }
        .alert-success { background: #e8f7ed; color: #176c38; border: 1px solid #bfe5cc; }
        .alert-error { background: #fdecec; color: #9f2727; border: 1px solid #f1c1c1; }
        .note { margin-top: 20px; padding: 15px; background: #f7f8f7; border-left: 4px solid #173f2d; line-height: 1.55; }
        code { background: #eef1ef; padding: 2px 5px; border-radius: 4px; }
        form { margin: 0; }
        @media (max-width: 620px) {
            .grid { grid-template-columns: 1fr; gap: 4px; }
            .label { margin-top: 10px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
<div class="topbar">
    <h1>W68 — Shopee Authorization</h1>
</div>

<div class="wrap">
    @if (session('shopee_success'))
        <div class="alert alert-success">{{ session('shopee_success') }}</div>
    @endif

    @if (session('shopee_error'))
        <div class="alert alert-error">{{ session('shopee_error') }}</div>
    @endif

    <div class="card">
        @php
            $stored = (bool) ($status['stored'] ?? false);
            $needsReauth = (bool) ($status['reauthorization_required'] ?? false);
            $partnerConfigured = (bool) ($status['partner_configured'] ?? false);
        @endphp

        @if (!$partnerConfigured)
            <span class="status bad">Partner credentials missing</span>
        @elseif (!$stored)
            <span class="status warn">Authorization required</span>
        @elseif ($needsReauth)
            <span class="status bad">Reauthorization required</span>
        @else
            <span class="status ok">Connected — automatic token rotation enabled</span>
        @endif

        <div class="grid">
            <div class="label">Shop ID</div>
            <div class="value">{{ $status['shop_id'] ?? 'Not authorized yet' }}</div>

            <div class="label">Access token expires</div>
            <div class="value">{{ $credential?->access_token_expires_at?->timezone('Asia/Manila')->format('F j, Y g:i:s A') ?? 'Not available' }}</div>

            <div class="label">Last token refresh</div>
            <div class="value">{{ $credential?->last_refreshed_at?->timezone('Asia/Manila')->format('F j, Y g:i:s A') ?? 'Not yet refreshed' }}</div>

            <div class="label">Authorized</div>
            <div class="value">{{ $credential?->authorized_at?->timezone('Asia/Manila')->format('F j, Y g:i:s A') ?? 'Not yet' }}</div>

            <div class="label">Callback URL</div>
            <div class="value"><code>{{ $redirectUrl }}</code></div>

            @if (!empty($status['last_error']))
                <div class="label">Last authorization error</div>
                <div class="value">{{ $status['last_error'] }}</div>
            @endif
        </div>

        <div class="actions">
            <a class="btn primary" href="{{ route('w68.shopee.authorize') }}">
                {{ $stored ? 'Authorize / Reauthorize Shopee' : 'Authorize Shopee' }}
            </a>

            @if ($stored && !$needsReauth)
                <form method="POST" action="{{ route('w68.shopee.refresh') }}">
                    @csrf
                    <button class="btn secondary" type="submit">Refresh Token Now</button>
                </form>
            @endif

            <a class="btn muted" href="/admin/masterlist/online-product-config">Back to Online Product Config</a>
        </div>

        <div class="note">
            When Shopee asks for an authorization duration, choose the <strong>longest duration available</strong> (use 365 days when Shopee offers it). After the one-time authorization, W68 stores the token pair encrypted in the database and automatically rotates both the access token and refresh token. You should not need to paste tokens into <code>.env</code> again.
        </div>
    </div>
</div>
</body>
</html>
