# Dokumentasi Integrasi 9router ke GPT Tracker (CodeIgniter 4)

## 1. Tujuan

Dokumen ini menjelaskan rencana integrasi antara:

- 9router
- GPT Tracker pribadi
- CodeIgniter 4
- Tailscale

Tujuan utama integrasi:

- otomatisasi pencatatan usage AI
- monitoring akun ChatGPT/Codex
- pencatatan jenis akun (free/plus/pro)
- monitoring tanggal expired dan renewal
- dashboard statistik token dan request
- eliminasi input usage manual

---

# 2. Arsitektur Sistem

```text
Codex CLI / VSCode Extension
            ↓
         9router
            ↓
   Log Usage / SQLite
            ↓
   Collector Service (CI4)
            ↓
     GPT Tracker Database
            ↓
 Dashboard & Analytics Web
```

---

# 3. Teknologi yang Digunakan

## Backend

- PHP 8+
- CodeIgniter 4
- MySQL/MariaDB

## Frontend

- Tailwind CSS
- Vite
- Server-side rendered views

## Integrasi AI

- 9router
- Codex
- OpenAI-compatible API

## Networking

- Tailscale (recommended)
- Cloudflare Tunnel (optional/testing only)

---

# 4. Kenapa Tailscale Lebih Direkomendasikan

## Masalah Cloudflare Quick Tunnel

Cloudflare Quick Tunnel:

- URL berubah-ubah
- health check kadang gagal
- kurang cocok untuk streaming AI
- endpoint terbuka ke internet publik
- tidak ideal untuk endpoint sensitif

## Keuntungan Tailscale

Tailscale lebih cocok karena:

- private network antar device
- latency rendah
- stabil untuk streaming AI
- tidak expose endpoint ke publik
- cocok untuk Codex & 9router

Contoh akses:

```text
http://100.x.x.x:20128/v1
```

---

# 5. Struktur Existing Web

Aplikasi existing menggunakan pola:

```text
Monolith MVC
```

dengan struktur:

```text
app/
  Controllers/
  Models/
  Services/
  Commands/
  Views/
```

Sistem existing sudah memiliki:

- accounts
- subscriptions
- usage histories
- grafik usage
- reminder Telegram

Sehingga integrasi 9router cukup menjadi:

```text
collector + mapper + observability layer
```

tanpa merombak core sistem existing.

---

# 6. Konsep Integrasi

## Existing Flow

```text
User input usage manual
    ↓
account_usages
    ↓
grafik dashboard
```

## New Flow

```text
9router log
    ↓
collector otomatis
    ↓
mapping account
    ↓
account_usages
    ↓
grafik existing
```

---

# 7. Tabel Baru yang Direkomendasikan

## ai_router_accounts

Digunakan untuk mencatat:

- jenis akun
- status akun
- expired
- renewal
- mapping akun 9router

```sql
CREATE TABLE ai_router_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_id INT NULL,
    subscription_id INT NULL,

    provider VARCHAR(50) NOT NULL,

    router_account_ref VARCHAR(150) NOT NULL,

    email VARCHAR(150) NULL,

    account_plan ENUM(
        'free',
        'plus',
        'pro',
        'team',
        'unknown'
    ) DEFAULT 'unknown',

    plan_started_at DATETIME NULL,
    plan_expires_at DATETIME NULL,
    renewal_at DATETIME NULL,

    status ENUM(
        'active',
        'expired',
        'disabled',
        'cooldown',
        'unknown'
    ) DEFAULT 'active',

    notes TEXT NULL,

    created_at DATETIME NULL,
    updated_at DATETIME NULL
);
```

---

## ai_router_usage_events

Digunakan untuk menyimpan raw usage dari 9router.

```sql
CREATE TABLE ai_router_usage_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    router_account_id INT NULL,

    provider VARCHAR(50) NOT NULL,

    model VARCHAR(150) NOT NULL,

    router_account_ref VARCHAR(150) NULL,

    input_tokens INT DEFAULT 0,
    output_tokens INT DEFAULT 0,

    cache_read_tokens INT DEFAULT 0,

    reasoning_tokens INT DEFAULT 0,

    duration_ms INT DEFAULT 0,

    status VARCHAR(30) DEFAULT 'success',

    raw_log TEXT NULL,

    created_at DATETIME NULL,
    updated_at DATETIME NULL
);
```

---

# 8. Struktur Modul Baru CI4

Direkomendasikan:

```text
app/
  Commands/
    Collect9routerUsage.php
    Sync9routerAccounts.php

  Models/
    AiRouterAccountModel.php
    AiRouterUsageEventModel.php

  Services/
    RouterUsageCollectorService.php
    RouterAccountMappingService.php
```

---

# 9. Command Collector

## Command

```bash
php spark router:push-usage
```

## Fungsi

Collector bertugas:

- membaca log 9router
- parsing usage
- menyimpan ke database
- mapping akun otomatis

---

# 10. Format Log 9router

Contoh:

```text
[USAGE] CODEX | in=14609 | out=50 | account=57b02c20... | cache_read=14208 | reasoning=20
```

Yang diambil:

| Field | Fungsi |
|---|---|
| provider | codex/openai/dll |
| model | model akhir yang dipakai request |
| in | input token |
| out | output token |
| account | identitas akun |
| cache_read | cache token |
| reasoning | reasoning token |
| Using ... account | email akun yang dipakai router |
| STREAM ... ms | latency/duration request |
| STREAM status | status akhir (`complete`, `disconnect`, dll) |
| msgs / tools / effort | konteks ukuran request + effort |
| available x/y | kapasitas pool account saat request |

---

# 11. Mapping Account

9router biasanya menggunakan:

```text
account=624de2f4...
```

Maka perlu mapping:

```text
624de2f4... → akun lokal
```

Tujuannya:

- grafik usage per akun
- statistik per akun
- reminder expired
- tracking akun aktif

---

# 12. Dashboard yang Direkomendasikan

## Ringkasan

```text
Total akun
Plus aktif
Pro aktif
Akun expired
Akan expired ≤ 7 hari
Total token hari ini
Total request hari ini
```

## Statistik

```text
Akun paling aktif
Model paling sering dipakai
Provider paling stabil
Cache ratio
Reasoning usage
```

## Monitoring

```text
Akun cooldown
Akun error
Request gagal
Latency tinggi
```

---

# 13. Reminder Expired

Karena sistem existing sudah punya Telegram reminder, fitur berikut bisa ditambahkan:

## Reminder

- H-7 expired
- H-3 expired
- H-1 expired
- akun expired
- akun disabled

## Data yang Dipakai

```text
plan_expires_at
renewal_at
status
```

---

# 14. Tahapan Implementasi

## Tahap 1

- tambah tabel ai_router_accounts
- tambah field expired/renewal
- migrasi data manual existing

## Tahap 2

- tambah ai_router_usage_events
- parsing log otomatis

## Tahap 3

- mapping router account → akun lokal
- update grafik otomatis

## Tahap 4

- monitoring error
- monitoring cooldown
- statistik provider

## Tahap 5

- integrasi penuh dengan Tailscale
- akses multi-device

---

# 15. Hal yang Tidak Direkomendasikan

## Jangan langsung:

- expose 9router ke public internet
- menyimpan credential provider ke dashboard
- mengubah besar WebController
- menjadikan DB 9router sebagai source utama

## Hindari:

- Cloudflare quick tunnel untuk production
- akses tanpa auth
- hardcoded credential

---

# 16. Kesimpulan

Arsitektur terbaik:

```text
9router = router AI
GPT Tracker = observability + account registry
Tailscale = secure network layer
```

Dengan pendekatan ini:

- sistem existing tetap stabil
- dashboard existing tetap dipakai
- input usage manual bisa dihilangkan bertahap
- monitoring akun menjadi jauh lebih lengkap
- scaling multi-account menjadi lebih mudah

---

# 17. Status Implementasi Saat Ini (2026-05-18)

Implementasi yang sudah masuk di codebase:

- migration baru `Create9routerIntegrationSchema`:
  - `ai_router_accounts`
  - `ai_router_usage_events`
  - `ai_router_collector_states`
- migration lanjutan `CreateAiRouterAccountSessionsTable`:
  - `ai_router_account_sessions`
- model baru:
  - `AiRouterAccountModel`
  - `AiRouterUsageEventModel`
  - `AiRouterCollectorStateModel`
  - `AiRouterAccountSessionModel`
- service baru:
  - `RouterUsageCollectorService` (parser + collector file log)
  - `RouterAccountMappingService` (create/mapping router account ref)
  - `RouterAnalyticsService` (ringkasan analytics account/model)
- command baru:
  - `php spark router:push-usage`
  - alias lama: `php spark router:collect-usage`
  - `php spark router:sync-accounts`
  - `php spark router:analytics-summary`

Catatan implementasi:

- collector membaca file log incremental berbasis cursor (`offset` + `line number`) di tabel `ai_router_collector_states`
- event disimpan append-only ke `ai_router_usage_events`
- dedup event menggunakan `event_hash`
- saat menemukan `router_account_ref` baru, sistem otomatis create row `ai_router_accounts` dengan `mapping_status=unmapped`
- ingest event sekarang otomatis update `ai_router_account_sessions` (request count, token totals, latency, first/last seen, last status, email jika tersedia)

---

# 18. Cara Menjalankan Collector

Contoh manual:

```bash
php spark router:push-usage --file=/path/to/9router.log --provider=9router
```

Jika `router.logPath` dan `router.provider` sudah diisi di `.env`, cukup:

```bash
php spark router:push-usage
```

Reset cursor lalu scan ulang dari awal file:

```bash
php spark router:push-usage --file=/path/to/9router.log --provider=9router --reset-cursor
```

Sinkronisasi mapping account dari event raw:

```bash
php spark router:sync-accounts --limit=2000
```

---

# 19. Konfigurasi ENV (Opsional)

Jika tidak ingin selalu kirim argumen command:

```ini
router.logPath = /path/to/9router.log
router.provider = 9router
```

---

# 20. Next Step yang Belum Diimplementasikan

Bagian berikut masih tahap lanjutan:

- agregasi token raw menjadi metrik dashboard existing (`account_usages`)
- panel UI untuk mapping `unmapped` account ref -> account/subscription lokal
- lifecycle reminder berbasis `plan_expires_at` dari `ai_router_accounts`

---

# 21. Mode Shipper Lokal -> Shared Hosting (Implemented)

Untuk skenario:

- 9router jalan di laptop lokal
- GPT Tracker di shared hosting

gunakan flow ini:

```text
Laptop lokal (9router log)
  -> scripts/router_log_shipper.php
  -> POST HTTPS /api/router/ingest
  -> ai_router_usage_events
```

## Endpoint ingest

- `POST /api/router/ingest`
- body JSON:
  - `source` (string)
  - `provider` (string)
  - `events` (array event)
- header opsional:
  - `X-Router-Ingest-Key` (jika `router.ingestKey` di server diisi)

Catatan keamanan:
- untuk development pribadi, `router.ingestKey` boleh dikosongkan
- untuk production/public hosting, sangat direkomendasikan mengisi `router.ingestKey` agar endpoint ingest tidak open

## Event field yang diterima

Contoh minimal event:

```json
{
  "provider": "codex",
  "model": "gpt-5.3-codex-high-review",
  "router_account_ref": "57b02c20...",
  "account_email": "bimoalfarrabi24@gmail.com",
  "input_tokens": 166027,
  "output_tokens": 212,
  "cache_read_tokens": 165248,
  "reasoning_tokens": 516,
  "duration_ms": 8132,
  "status": "complete",
  "event_at": "2026-05-18 08:08:34",
  "event_hash": "sha256...",
  "meta": {
    "request_messages": 338,
    "request_tools": 17,
    "reasoning_effort": "high",
    "connection_available": 7,
    "connection_total": 7,
    "route_from_model": "cx/gpt-5.3-codex-high-review",
    "route_to_model": "codex/gpt-5.3-codex-high-review"
  }
}
```

---

# 22. Menjalankan Script Lokal

Script: `scripts/router_log_shipper.php`

Contoh eksekusi:

```bash
php scripts/router_log_shipper.php \
  --log=/path/to/9router.log \
  --endpoint=https://domainkamu.com/api/router/ingest \
  --source=laptop-pribadi \
  --provider=9router \
  --max-events=500 \
  --timeout=20
```

Jika endpoint ingest key diaktifkan:

```bash
php scripts/router_log_shipper.php \
  --log=/path/to/9router.log \
  --endpoint=https://domainkamu.com/api/router/ingest \
  --key=INGEST_SECRET
```

Dry run:

```bash
php scripts/router_log_shipper.php --log=/path/to/9router.log --endpoint=https://domainkamu.com/api/router/ingest --dry-run
```

---

# 23. Endpoint Ringkasan Analytics (Implemented)

Endpoint:

```text
GET /api/router/analytics/summary
GET /api/router/analytics/charts
```

Query opsional:

- `provider` (contoh: `codex`)
- `days` (default `30`)

Contoh:

```text
GET /api/router/analytics/summary?provider=codex&days=30
GET /api/router/analytics/charts?provider=codex&days=30&top=10
```

Output ringkas mencakup:

- total request/token (input, output, cache, reasoning)
- average latency
- cache efficiency ratio
- leaderboard akun:
  - paling sering dipakai
  - paling boros token
  - reasoning tertinggi
  - cache efficiency terbaik
  - latency rata-rata terendah
- top 5 model berdasarkan total request

Endpoint `charts` mengembalikan data siap visualisasi:
- `daily_tokens` (time-series harian input/output/total/cache/reasoning/latency)
- `usage_by_account` (ranking akun + cache ratio + avg latency)
- `usage_by_model` (ranking model + cache ratio + avg latency)
- `activity_by_hour` (timeline request per jam)

CLI alternatif:

```bash
php spark router:analytics-summary --provider=codex --days=30
```
