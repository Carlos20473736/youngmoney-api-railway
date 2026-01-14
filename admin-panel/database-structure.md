# Estrutura do Banco de Dados Real - Young Money

## Tabelas Principais

### users
- id (INT, PRIMARY KEY)
- ymid (INT, UNIQUE)
- device_id (VARCHAR)
- google_id (VARCHAR)
- telegram_id (VARCHAR)
- email (VARCHAR)
- name (VARCHAR)
- username (VARCHAR)
- photo_url (TEXT)
- profile_picture (TEXT)
- balance (DECIMAL)
- points (INT)
- monetag_impressions (INT)
- monetag_clicks (INT)
- daily_points (INT)
- invite_code (VARCHAR)
- invited_by (INT)
- has_used_invite_code (BOOLEAN)
- token (VARCHAR)
- token_expires_at (DATETIME)
- created_at (DATETIME)
- updated_at (DATETIME)
- master_seed (TEXT)
- session_salt (VARCHAR)
- last_login_at (DATETIME)
- salt_updated_at (DATETIME)

### Outras Tabelas
1. active_sessions
2. admin_logs
3. checkin_reset_logs
4. daily_checkin
5. daily_tasks
6. device_blacklist
7. monetag_events
8. monetag_postbacks
9. monetag_reset_logs
10. notifications
11. pix_keys
12. pix_payments
13. point_transactions
14. points_history
15. ranking_periods
16. ranking_points
17. ranking_reset_log
18. ranking_reset_logs
19. referrals
20. request_log
21. roulette_prizes
22. roulette_settings
23. security_violations
24. spin_history
25. spin_reset_logs
26. spins
27. system_settings
28. tracking_events
29. user_tokens
30. withdrawal_quick_values
31. withdrawal_requests
32. withdrawals

## Observações
- A tabela users usa `ymid` como identificador único do Young Money
- Pontos são armazenados como INT na coluna `points`
- Sistema de convites usa `invite_code` e `invited_by`
- Suporte para múltiplos métodos de login (Google, Telegram, Device)
