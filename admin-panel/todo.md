# Young Money Admin Panel - TODO

## Funcionalidades Principais

### 1. Dashboard Principal
- [x] Exibir total de usuários
- [x] Exibir pontos distribuídos
- [x] Exibir saques pendentes
- [x] Exibir receita total
- [x] Gráficos de estatísticas gerais

### 2. Gerenciamento de Usuários
- [x] Listar todos os usuários
- [x] Visualizar detalhes individuais de usuários
- [x] Editar pontos manualmente
- [x] Banir/desbanir contas
- [x] Acessar histórico de atividades por usuário

### 3. Sistema de Pontos
- [x] Adicionar pontos manualmente
- [x] Remover pontos manualmente
- [x] Visualizar histórico de transações
- [x] Configurar valores de recompensa por ação

### 4. Gerenciamento de Saques
- [x] Listar solicitações de saque
- [x] Aprovar saques via PIX
- [x] Rejeitar saques
- [x] Visualizar histórico de saques
- [x] Exportar relatórios de saques

### 5. Sistema de Convites
- [x] Visualizar estatísticas de convites
- [x] Configurar pontos por convite
- [x] Rastrear referências entre usuários
- [x] Visualizar top referrers

### 6. Ranking de Usuários
- [x] Visualizar top usuários
- [x] Configurar prêmios por posição
- [x] Acessar histórico de rankings

### 7. Configurações de Jogos
- [x] Configurar Candy Crush (probabilidades, recompensas, limites)
- [x] Configurar Roleta (probabilidades, recompensas, limites)
- [x] Ajustar limites diários

### 8. Configurações do Sistema
- [x] Definir pontos por tarefa
- [x] Configurar limites de saque
- [x] Definir taxas aplicáveis
- [x] Gerenciar notificações

### 9. Autenticação e Controle de Acesso
- [x] Sistema de login administrativo
- [x] Controle de acesso baseado em roles
- [x] Diferentes níveis de permissão

### 10. Logs e Auditoria
- [x] Registrar todas as ações administrativas
- [x] Visualizar logs de auditoria
- [x] Filtrar logs por usuário/ação/data
- [x] Exportar logs


## Integração com Banco de Dados Real

### Conectar ao Banco Real do Railway
- [ ] Investigar estrutura das tabelas existentes no banco
- [ ] Mapear campos e tipos de dados reais
- [ ] Ajustar schema do Drizzle para corresponder ao banco real
- [ ] Atualizar queries para buscar dados reais dos usuários
- [ ] Testar visualização de usuários existentes
- [ ] Validar todas as operações CRUD com dados reais


## Correções de Erros

### Erros de Banco de Dados
-- [x] Aplicar migrations para criar tabelas faltantes
- [x] Corrigir queries com ORDER BY vazio
- [x] Validar estrutura das tabelas no banco
- [x] Testar todas as páginas do painel
- [x] Remover tela de login do painel administrativo
- [x] Tornar painel acessível sem autenticação


## Correção de Erro admin_logs
- [x] Adicionar try-catch na função getAuditLogs


## Conexão com Banco Real do Railway
- [x] Acessar Railway e verificar estrutura das tabelas
- [x] Mapear colunas reais da tabela de usuários
- [x] Ajustar schema do Drizzle para corresponder ao banco
- [x] Testar exibição de usuários reais
- [x] Conectar ao banco MySQL do Railway (gondola.proxy.rlwy.net:46765)
- [x] Exibir 17 usuários reais no dashboard
- [x] Exibir 1.150.997 pontos em circulação


## Criação de Saque Manual
- [x] Verificar ID do usuário ABSOLUT1 FF (ID: 31)
- [x] Verificar estrutura da tabela withdrawals
- [x] Criar saque de R$ 20,00 para o usuário (PIX CPF: 55537568802)
- [x] Confirmar criação no banco de dados (ID do saque: 4)


## Integração Completa de Endpoints

### Mapeamento de Tabelas e Endpoints
- [x] Listar todas as tabelas do banco de dados (33 tabelas)
- [x] Identificar endpoints da API existente
- [x] Mapear funcionalidades de cada tabela

### Implementação no Painel
- [x] Gerenciamento de sessões ativas (active_sessions)
- [x] Logs de administração (admin_logs)
- [x] Check-in diário (daily_checkin, checkin_reset_logs)
- [x] Tarefas diárias (daily_tasks)
- [x] Blacklist de dispositivos (device_blacklist)
- [x] Eventos Monetag (monetag_events, monetag_postbacks, monetag_reset_logs)
- [x] Notificações (notifications)
- [x] Chaves PIX (pix_keys)
- [x] Pagamentos PIX (pix_payments)
- [x] Transações de pontos (point_transactions, points_history)
- [x] Ranking (ranking_periods, ranking_points, ranking_reset_logs)
- [x] Referências/Convites (referrals)
- [x] Logs de requisições (request_log)
- [x] Roleta (roulette_prizes, roulette_settings, spin_history, spins, spin_reset_logs)
- [x] Configurações do sistema (system_settings)
- [x] Eventos de rastreamento (tracking_events)
- [x] Tokens de usuário (user_tokens)
- [x] Saques (withdrawal_quick_values, withdrawal_requests, withdrawals)
- [x] Violações de segurança (security_violations)

### Páginas do Painel
- [x] Dashboard com estatísticas em tempo real
- [x] Gerenciamento de Usuários
- [x] Sistema de Saques
- [x] Ranking de Usuários
- [x] Configurações da Roleta
- [x] Sistema de Notificações (envio individual e broadcast)
- [x] Monetag (eventos e postbacks)
- [x] Blacklist de Dispositivos
- [x] Segurança (sessões ativas e violações)
- [x] Configurações do Sistema
- [x] Explorador de Banco de Dados (SQL queries)
- [x] Logs de Auditoria
