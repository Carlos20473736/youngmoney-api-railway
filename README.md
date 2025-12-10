# YoungMoney API v2.0.0

API backend do YoungMoney com sistema de pagamento automático PIX.

## 🚀 Features

- ✅ Sistema de pagamento PIX automático
- ✅ Top 10 do ranking com valores escalonados
- ✅ Registro de chaves PIX dos usuários
- ✅ Histórico de pagamentos
- ✅ Relatórios e analytics

## 📋 Requisitos

- Node.js >= 18.0.0
- MySQL/PostgreSQL
- npm ou yarn

## 🔧 Instalação

1. **Clonar repositório**
```bash
git clone <seu-repo>
cd youngmoney-api-backend
```

2. **Instalar dependências**
```bash
npm install
```

3. **Configurar variáveis de ambiente**
```bash
cp .env.example .env
# Editar .env com suas credenciais
```

4. **Executar migrations do banco de dados**
```bash
mysql -h $MYSQLHOST -u $MYSQLUSER -p$MYSQLPASSWORD $MYSQLDATABASE < database/pix_payment_schema.sql
```

5. **Iniciar servidor**
```bash
npm start
```

## 📡 Endpoints

### Health Check
```
GET /health
```

### PIX Payment Endpoints

#### Salvar Chave PIX
```
POST /api/pix/save-key
Body: {
  "user_id": "123",
  "pix_key_type": "CPF",
  "pix_key": "12345678901"
}
```

#### Obter Chave PIX
```
GET /api/pix/key/:user_id
```

#### Processar Pagamentos Top 10
```
POST /api/pix/process-top10-payments
Body: {
  "ranking_period": "2024-12-10"
}
```

#### Histórico de Pagamentos
```
GET /api/pix/payments/:user_id
```

#### Atualizar Status de Pagamento
```
PUT /api/pix/payment/:payment_id
Body: {
  "status": "completed|failed|pending"
}
```

## 🗄️ Database Schema

Tabelas criadas automaticamente:
- `pix_keys` - Armazena chaves PIX dos usuários
- `pix_payments` - Registra pagamentos processados

## 📊 Valores de Pagamento

| Posição | Valor |
|---------|-------|
| Top 1 | R$ 20,00 |
| Top 2 | R$ 10,00 |
| Top 3 | R$ 5,00 |
| Top 4-10 | R$ 1,00 |

## 🚢 Deployment no Railway

1. Conectar repositório GitHub
2. Configurar variáveis de ambiente no Railway
3. Deploy automático

## 📝 Logs

Verificar logs no Railway Dashboard:
```
railway.com/project/[project-id]/logs
```

## 🆘 Troubleshooting

### Erro de conexão com banco de dados
- Verificar variáveis de ambiente
- Confirmar que MySQL está rodando
- Verificar firewall/network rules

### Erro ao processar pagamentos
- Verificar se tabelas foram criadas
- Confirmar que usuários têm chaves PIX registradas
- Verificar logs do servidor

## 📄 Documentação

Ver `INTEGRATION_GUIDE.md` para guia completo de integração.

## 📄 License

MIT

## 👥 Autor

YoungMoney Team
