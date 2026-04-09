
# DATABASE.md — Subscription Model

## accounts
- id
- account_name
- email
- password_hint
- notes

## subscriptions
- id
- account_id
- store_source
- subscription_type
- expired_at
- status

## account_usages
- id
- subscription_id
- usage_type
- remaining_percent
- reset_at

## account_usage_histories
- id
- account_usage_id
- old_percent
- new_percent

## reminder_logs
- account_id
- subscription_id
- reminder_type
