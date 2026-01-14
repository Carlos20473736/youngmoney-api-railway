# Estrutura Real da Tabela `users` no Railway

## Colunas da Tabela

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | int | ID do usuário (PK, auto-increment) |
| ymid | int | Young Money ID |
| device_id | varchar | ID do dispositivo |
| google_id | varchar | ID do Google OAuth |
| telegram_id | varchar | ID do Telegram |
| email | varchar | Email do usuário |
| name | varchar | Nome do usuário |
| username | varchar | Nome de usuário |
| photo_url | varchar | URL da foto (antigo) |
| profile_picture | varchar | URL da foto de perfil |
| balance | decimal | Saldo em reais |
| points | int | Pontos acumulados |
| monetag_impressions | int | Impressões Monetag |
| monetag_clicks | int | Cliques Monetag |
| daily_points | int | Pontos diários |
| invite_code | varchar | Código de convite |
| invited_by | varchar | Convidado por |
| has_used_invite_code | tinyint | Usou código de convite |
| token | varchar | Token de sessão |
| token_expires_at | datetime | Expiração do token |
| created_at | datetime | Data de criação |
| updated_at | datetime | Data de atualização |
| master_seed | text | Seed mestre |
| session_salt | varchar | Salt da sessão |
| last_login_at | datetime | Último login |
| salt_updated_at | datetime | Atualização do salt |

## Dados de Exemplo

- ID 31: ABSOLUT1 FF (c.g.f.d.s106@gmail.com) - 571240 pontos
- ID 32: Teste (teste726363@gmail.com) - 573730 pontos
- ID 33: Ana Paula (aniicostaa1210@gmail.com) - 4733 pontos
- ID 34-42: Usuários de teste

## Observações

- A tabela real se chama `users` (não `app_users`)
- Não existe coluna `ymid` como campo separado em alguns registros (NULL)
- Os pontos são armazenados na coluna `points`
- O saldo em reais está na coluna `balance`
