# Configuração do Banco de Dados Railway

## Passo 1: Adicionar Variável de Ambiente no Railway

Para conectar o painel ao banco de dados real do Young Money:

1. Acesse seu projeto no Railway: https://railway.com/project/79d5cf15-843f-4bb7-8538-167449561f7f

2. Clique no serviço **youngmoney-admin-panel** (ou crie um novo serviço para este painel)

3. Vá em **Variables**

4. Clique em **New Variable**

5. Adicione a seguinte variável:
   - **Nome**: `DATABASE_URL`
   - **Valor**: `${{MySQL.MYSQL_URL}}`
   
   Isso fará referência automática ao banco MySQL do seu projeto.

6. Clique em **Add** e depois em **Deploy**

## Passo 2: Verificar Conexão

Após adicionar a variável, o painel irá:
- Conectar automaticamente ao banco MySQL do Railway
- Mostrar os usuários reais cadastrados
- Permitir gerenciar pontos, saques e todas as funcionalidades

## Estrutura do Banco Detectada

O painel está configurado para trabalhar com as seguintes tabelas do seu banco:

✅ **users** - Usuários do aplicativo
✅ **point_transactions** - Histórico de pontos
✅ **withdrawals** - Solicitações de saque
✅ **referrals** - Sistema de convites
✅ **ranking_points** - Ranking de usuários
✅ **pix_keys** - Chaves PIX dos usuários
✅ **pix_payments** - Pagamentos realizados
✅ **system_settings** - Configurações do sistema
✅ **admin_logs** - Logs de auditoria

## Observações

- O painel usa o mesmo banco de dados que a API do Young Money
- Todas as operações são realizadas diretamente no banco real
- Os dados são sincronizados em tempo real com o aplicativo
