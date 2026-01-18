# Investigação do Bug: Usuários Subindo no Ranking com Pagamentos Pendentes

## Problema Reportado
Usuários estão aparecendo no ranking mesmo quando deveriam estar em período de cooldown (após receberem prêmio).

## Arquivos Analisados

### 1. `ranking/list.php` - Lista do Ranking
- **Correto**: Exclui usuários em cooldown usando LEFT JOIN com `ranking_cooldowns`
- Query correta:
```sql
SELECT u.id, u.name, u.profile_picture, u.daily_points as points
FROM users u
LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > ?
WHERE u.daily_points > 0 
  AND u.pix_key IS NOT NULL 
  AND u.pix_key != ''
  AND rc.id IS NULL
```

### 2. `ranking/add_points.php` - Adicionar Pontos
- **PROBLEMA ENCONTRADO**: Verifica apenas se usuário tem chave PIX
- **NÃO VERIFICA** se usuário está em cooldown antes de adicionar `daily_points`
- Usuários em cooldown continuam acumulando `daily_points`

### 3. `api/v1/checkin.php` - Check-in Diário
- **PROBLEMA**: Adiciona pontos diretamente sem verificar cooldown
- Linha 249: `UPDATE users SET points = points + ?, daily_points = daily_points + ?`

### 4. `api/v1/spin.php` - Roleta
- **PROBLEMA**: Adiciona pontos diretamente sem verificar cooldown
- Linha 185: `daily_points = daily_points + ?`

### 5. `api/v1/invite.php` - Sistema de Convites
- **PROBLEMA**: Adiciona pontos diretamente sem verificar cooldown
- Linha 313-320: Adiciona `daily_points` para quem convida e quem é convidado

### 6. `api/v1/cron/auto_reset.php` - Reset Automático
- **PROBLEMA CRÍTICO**: A query do Top 10 NÃO exclui usuários em cooldown!
- Linha 182-188:
```sql
SELECT id, name, email, daily_points, pix_key, pix_key_type 
FROM users 
WHERE daily_points > 0 
ORDER BY daily_points DESC 
LIMIT 10
```
- Esta query deveria excluir usuários com cooldown ativo, mas não faz isso!

### 7. `api/v1/reset/ranking_scheduled.php` - Reset Agendado
- **Correto**: Exclui usuários em cooldown na query do Top 10

## Causa Raiz do Bug

### Problema Principal: `auto_reset.php`
O arquivo `auto_reset.php` (que é chamado pelo cron-job.org) **NÃO exclui usuários em cooldown** ao buscar o Top 10. Isso significa que:

1. Um usuário ganha o prêmio e entra em cooldown
2. Seu `daily_points` é resetado para 0
3. Ele continua ganhando pontos via check-in, roleta, convites (pois esses endpoints não verificam cooldown)
4. No próximo reset, ele pode aparecer no Top 10 novamente porque a query não exclui cooldowns

### Problema Secundário: Endpoints de Pontos
Os endpoints que adicionam pontos (`checkin.php`, `spin.php`, `invite.php`) não verificam se o usuário está em cooldown antes de adicionar `daily_points`. Isso permite que usuários em cooldown continuem acumulando pontos para o ranking.

## Inconsistência Entre Arquivos
- `ranking_scheduled.php` - **CORRETO** (exclui cooldowns)
- `auto_reset.php` - **INCORRETO** (não exclui cooldowns)
- `ranking/list.php` - **CORRETO** (exclui cooldowns na listagem)

## Correções Necessárias

### 1. Corrigir `auto_reset.php` (CRÍTICO)
Alterar a query do Top 10 para excluir usuários em cooldown:
```sql
SELECT u.id, u.name, u.email, u.daily_points, u.pix_key, u.pix_key_type 
FROM users u
LEFT JOIN ranking_cooldowns rc ON u.id = rc.user_id AND rc.cooldown_until > NOW()
WHERE u.daily_points > 0 
  AND u.pix_key IS NOT NULL 
  AND u.pix_key != ''
  AND rc.id IS NULL
ORDER BY u.daily_points DESC 
LIMIT 10
```

### 2. Corrigir endpoints de pontos (OPCIONAL)
Adicionar verificação de cooldown antes de adicionar `daily_points`:
- Se usuário está em cooldown, adicionar apenas `points` (total)
- Não adicionar `daily_points` (ranking)

Arquivos afetados:
- `api/v1/checkin.php`
- `api/v1/spin.php`
- `api/v1/invite.php`
- `ranking/add_points.php`

## Conclusão
O bug principal está no arquivo `auto_reset.php` que não exclui usuários em cooldown ao determinar o Top 10. Isso permite que usuários que já ganharam prêmios e estão em período de cooldown apareçam novamente no ranking e potencialmente ganhem outro prêmio.
