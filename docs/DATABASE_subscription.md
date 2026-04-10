
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
- account_type
- pro_account_type
- workspace_name
- personal_workspace_name
- is_workspace_deactivated
- subscribed_at
- is_one_month_duration
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
