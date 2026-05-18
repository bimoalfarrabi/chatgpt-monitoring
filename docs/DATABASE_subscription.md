# DATABASE.md — Subscription + 9router Model

## Core Tables

### `accounts`
- `id`
- `account_name`
- `email` (unique)
- `password_hint`
- `notes`
- `created_at`, `updated_at`

### `subscriptions`
- `id`
- `account_id` (FK -> `accounts.id`)
- `store_source`
- `subscription_type`
- `account_type` (`free|pro|plus`)
- `pro_account_type` (`personal_invite|seller_account|null`)
- `workspace_name`
- `personal_workspace_name`
- `is_workspace_deactivated`
- `subscribed_at`
- `is_one_month_duration`
- `expired_at`
- `status` (`active|expiring_soon|expired|deactivated`)
- `created_at`, `updated_at`

### `reminder_logs`
- `id`
- `account_id` (FK -> `accounts.id`)
- `subscription_id` (FK -> `subscriptions.id`)
- `reminder_type`
- `sent_at`

### `telegram_settings`
- `id`
- `user_id` (FK -> `users.id`, unique per user)
- `bot_token`
- `chat_id`
- `is_active`
- `created_at`, `updated_at`

## 9router Observability Tables

### `ai_router_accounts`
- `id`
- `user_id` (nullable FK -> `users.id`)
- `account_id` (nullable FK -> `accounts.id`)
- `subscription_id` (nullable FK -> `subscriptions.id`)
- `provider` (default `9router`)
- `router_account_ref` (unique per `provider`)
- `email`
- `account_plan`
- `plan_started_at`, `plan_expires_at`, `renewal_at`
- `status`
- `mapping_status`
- `notes`
- `last_seen_at`
- `created_at`, `updated_at`

### `ai_router_usage_events`
- `id` (BIGINT)
- `router_account_id` (nullable FK -> `ai_router_accounts.id`)
- `provider`
- `model`
- `router_account_ref`
- `input_tokens`
- `output_tokens`
- `cache_read_tokens`
- `reasoning_tokens`
- `duration_ms`
- `status`
- `event_hash` (unique)
- `event_at`
- `raw_log`
- `created_at`, `updated_at`

### `ai_router_collector_states`
- `id`
- `source_key` (unique)
- `source_path`
- `last_offset`
- `last_line_number`
- `last_collected_at`
- `created_at`, `updated_at`

### `ai_router_account_sessions`
- `id` (BIGINT)
- `router_account_id` (nullable FK -> `ai_router_accounts.id`)
- `provider`
- `router_account_ref` (unique per `provider`)
- `email`
- `first_seen_at`
- `last_seen_at`
- `total_requests`
- `total_input_tokens`
- `total_output_tokens`
- `total_cache_read_tokens`
- `total_reasoning_tokens`
- `total_duration_ms`
- `last_status`
- `created_at`, `updated_at`

## Legacy Tables (Compatibility)

### `account_usages`
- `id`
- `subscription_id` (FK -> `subscriptions.id`)
- `usage_type` (`5h`, `weekly`, `weekly_personal`)
- `remaining_percent`
- `reset_at`
- `created_at`, `updated_at`

### `account_usage_histories`
- `id`
- `account_usage_id` (FK -> `account_usages.id`)
- `old_percent`
- `new_percent`
- `created_at`

## Notes
- Sumber utama usage untuk dashboard dan detail akun sekarang adalah tabel `ai_router_usage_events` (9router).
- Tabel legacy (`account_usages`, `account_usage_histories`) masih dipertahankan untuk kompatibilitas data lama.
