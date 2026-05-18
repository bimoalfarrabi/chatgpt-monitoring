# API_SPEC.md — API Specification

## Base URL
- `/api`

## Auth
- Mayoritas endpoint API memakai session login (`auth` filter).
- Khusus `POST /api/router/ingest` tidak memakai session karena dipanggil collector lokal.
- Jika `router.ingestKey` diaktifkan, kirim header: `X-Router-Ingest-Key: <INGEST_SECRET>`.

---

## Accounts (Auth Required)

### GET /api/accounts
Get all accounts.

### GET /api/accounts/{id}
Get account detail.

### POST /api/accounts
Create account.

### PUT /api/accounts/{id}
Update account.

### DELETE /api/accounts/{id}
Delete account.

---

## Subscriptions (Auth Required)

### GET /api/subscriptions
Get all subscriptions.

### GET /api/subscriptions/{id}
Get subscription detail.

### POST /api/subscriptions
Create subscription.

### PUT /api/subscriptions/{id}
Update subscription.

### DELETE /api/subscriptions/{id}
Delete subscription.

---

## Telegram (Auth Required)

### GET /api/telegram/settings
Get telegram settings untuk user login.

### PUT /api/telegram/settings
Update telegram settings untuk user login.

### POST /api/telegram/test
Send test message ke chat Telegram aktif.

---

## Router Ingest (No Session Auth)

### POST /api/router/ingest
Menerima batch event usage 9router dari collector/shipper lokal.

Body ringkas:
```json
{
  "source": "laptop-viasco",
  "provider": "codex",
  "events": [
    {
      "provider": "codex",
      "model": "gpt-5.3-codex-high-review",
      "router_account_ref": "57b02c20...",
      "account_email": "contoh@email.com",
      "input_tokens": 166027,
      "output_tokens": 212,
      "cache_read_tokens": 165248,
      "reasoning_tokens": 0,
      "duration_ms": 8132,
      "status": "complete",
      "event_at": "2026-05-18 08:08:34"
    }
  ]
}
```

---

## Router Analytics (Auth Required)

### GET /api/router/analytics/summary
Ringkasan usage observability 9router per provider/account/model.

Query opsional:
- `provider` (contoh: `codex`)
- `days` (contoh: `30`)

### GET /api/router/analytics/charts
Data siap visualisasi dashboard observability:
- token harian
- aktivitas per jam
- usage per akun
- distribusi model
- cache ratio harian
- latency harian

Query opsional:
- `provider` (contoh: `codex`)
- `days` (contoh: `30`)
- `top` (contoh: `10`)

---

## Notes
- `expired` = masa invite/subscription habis.
- Status subscription tidak mempengaruhi akun utama, hanya akses langganan/workspace.
- Sumber usage dashboard/detail akun sekarang dari event 9router (bukan input manual legacy).
