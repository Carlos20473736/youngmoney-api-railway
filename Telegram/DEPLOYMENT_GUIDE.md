# 🚀 Guia de Deployment - Graninha Bot no Render.com

## ✅ Pré-requisitos Concluídos

- ✅ Repositório GitHub criado: https://github.com/MadagascarMods/graninha-bot-render
- ✅ Código adaptado e otimizado para Render.com
- ✅ Configuração `render.yaml` pronta
- ✅ Backend Express configurado com proxy para API Graninha

---

## 📋 Passo a Passo para Deploy no Render.com

### 1️⃣ Acessar o Render.com

1. Acesse: **https://dashboard.render.com**
2. Faça login ou crie uma conta gratuita
3. Conecte sua conta GitHub (se ainda não estiver conectada)

---

### 2️⃣ Criar Novo Web Service

1. No dashboard do Render, clique em **"New +"** (canto superior direito)
2. Selecione **"Web Service"**
3. Você verá a lista de repositórios do GitHub

---

### 3️⃣ Conectar o Repositório

1. Procure por **"graninha-bot-render"** na lista
2. Clique em **"Connect"** ao lado do repositório
3. Se não aparecer, clique em **"Configure account"** e autorize o acesso ao repositório

---

### 4️⃣ Configurar o Web Service

Preencha os seguintes campos:

| Campo | Valor |
|-------|-------|
| **Name** | `graninha-bot-render` |
| **Region** | `Oregon (US West)` ou `Frankfurt (EU Central)` |
| **Branch** | `main` |
| **Root Directory** | (deixe em branco) |
| **Environment** | `Node` |
| **Build Command** | `npm install` |
| **Start Command** | `npm start` |
| **Plan** | `Free` (ou escolha um plano pago) |

> **Nota**: O Render detectará automaticamente o `render.yaml` e preencherá alguns campos automaticamente.

---

### 5️⃣ Configurar Variáveis de Ambiente

Role a página até a seção **"Environment Variables"** e adicione:

```
NODE_ENV = production
PORT = 3000
CORS_ORIGIN = *
GRANINHA_API_URL = https://painel.graninha.com.br/api/v1
```

**Como adicionar:**

1. Clique em **"Add Environment Variable"**
2. Digite o **Key** (nome da variável)
3. Digite o **Value** (valor da variável)
4. Repita para todas as 4 variáveis

---

### 6️⃣ Configurações Avançadas (Opcional)

Expanda a seção **"Advanced"** e configure:

- **Auto-Deploy**: `Yes` (para deploy automático em cada push)
- **Health Check Path**: `/health`

---

### 7️⃣ Iniciar o Deploy

1. Revise todas as configurações
2. Clique em **"Create Web Service"**
3. O Render iniciará o build automaticamente

---

## ⏳ Aguardar o Build

O processo de build levará aproximadamente **2-5 minutos**:

1. **Installing dependencies**: Instalando pacotes npm
2. **Building**: Executando o build
3. **Starting**: Iniciando o servidor
4. **Live**: Serviço online e funcionando

Você pode acompanhar o progresso em tempo real na aba **"Logs"**.

---

## 🎉 Deploy Concluído!

Quando o status mudar para **"Live"**, seu bot estará online!

### URL do Serviço

Você receberá uma URL no formato:

```
https://graninha-bot-render.onrender.com
```

ou

```
https://graninha-bot-render-XXXX.onrender.com
```

---

## 🧪 Testar o Deployment

### 1. Testar o Health Check

Abra o terminal e execute:

```bash
curl https://sua-url.onrender.com/health
```

Resposta esperada:

```json
{
  "status": "ok",
  "timestamp": "2025-12-13T01:00:00.000Z",
  "environment": "production",
  "service": "Graninha Bot Render"
}
```

### 2. Acessar a Interface

Abra o navegador e acesse:

```
https://sua-url.onrender.com
```

Você verá a interface do Graninha Bot com:

- ⚙️ Seção de Configuração (Bearer Token e EX ID)
- 📊 Seção de Status (Saldo, Ganhos, Ações)
- 📝 Seção de Logs

### 3. Testar o Bot

1. Insira seu **Bearer Token** (obtenha via HTTP Catcher)
2. Insira seu **EX ID**
3. Marque **"Ativar loop infinito"** (opcional)
4. Clique em **"🚀 Iniciar Bot"**
5. Acompanhe os logs em tempo real

---

## 🔄 Atualizações Automáticas

Após o deployment inicial, qualquer mudança no código será deployada automaticamente:

```bash
# Faça alterações no código
git add .
git commit -m "Update: descrição das mudanças"
git push origin main
```

O Render detectará o push e iniciará um novo build automaticamente.

---

## 📊 Monitoramento no Render Dashboard

### Logs em Tempo Real

1. Acesse o dashboard do Render
2. Clique no serviço **"graninha-bot-render"**
3. Vá para a aba **"Logs"**
4. Você verá todos os eventos do servidor:
   - Requisições recebidas
   - Proxy para API Graninha
   - Erros e avisos

### Métricas

Na aba **"Metrics"**, você pode ver:

- CPU Usage
- Memory Usage
- Request Count
- Response Time

### Events

Na aba **"Events"**, você verá:

- Deploys realizados
- Builds com sucesso/falha
- Restarts do serviço

---

## 🔧 Solução de Problemas

### ❌ Build Failed

**Sintomas**: Build falha durante a instalação de dependências

**Soluções**:

1. Verifique se o `package.json` está correto
2. Confirme que o `render.yaml` está no root do projeto
3. Verifique os logs de build no Render Dashboard
4. Tente fazer rebuild manual: **"Manual Deploy"** → **"Deploy latest commit"**

---

### ❌ Service Unavailable

**Sintomas**: Serviço não responde ou retorna erro 503

**Soluções**:

1. Verifique se o serviço está **"Live"** no dashboard
2. Aguarde alguns minutos (cold start no plano Free)
3. Verifique os logs para erros de inicialização
4. Confirme que a porta está configurada corretamente (3000)

---

### ❌ CORS Error

**Sintomas**: Erro de CORS no console do navegador

**Soluções**:

1. Verifique se `CORS_ORIGIN` está configurado como `*`
2. Limpe o cache do navegador (Ctrl + Shift + Del)
3. Tente em uma aba anônima
4. Verifique os headers da resposta no Network tab (F12)

---

### ❌ API Connection Error

**Sintomas**: Bot não consegue conectar à API Graninha

**Soluções**:

1. Verifique se `GRANINHA_API_URL` está correto
2. Teste a API diretamente:
   ```bash
   curl -X POST https://sua-url.onrender.com/api/get_user \
     -H "Content-Type: application/json" \
     -d '{"bearer_token": "seu_token"}'
   ```
3. Verifique os logs do servidor para erros de proxy
4. Confirme que o Bearer Token está válido (tokens expiram)

---

### ❌ Token Inválido

**Sintomas**: Erro "Token inválido" ou "Unauthorized"

**Soluções**:

1. Obtenha um novo Bearer Token via HTTP Catcher
2. Tokens expiram após algumas horas
3. Confirme que o token está completo (sem espaços extras)
4. Verifique se o EX ID está correto

---

## 🎯 Configurações Recomendadas

### Plano Free do Render

- ✅ Gratuito para sempre
- ✅ 750 horas de execução por mês
- ✅ SSL/HTTPS automático
- ✅ Deploy automático
- ⚠️ Cold start após 15 minutos de inatividade
- ⚠️ 512 MB de RAM

### Plano Pago (Starter - $7/mês)

- ✅ Sem cold start
- ✅ 512 MB de RAM
- ✅ Execução contínua 24/7
- ✅ Suporte prioritário

---

## 🔐 Segurança

### Boas Práticas

1. **Nunca compartilhe seu Bearer Token** publicamente
2. **Use variáveis de ambiente** para dados sensíveis
3. **Mantenha o repositório privado** se necessário
4. **Revise os logs regularmente** para atividades suspeitas
5. **Atualize as dependências** periodicamente

### Tornar Repositório Privado

Se desejar tornar o repositório privado:

```bash
gh repo edit MadagascarMods/graninha-bot-render --visibility private
```

---

## 📞 Suporte e Recursos

### Documentação Oficial

- **Render.com Docs**: https://render.com/docs
- **Node.js on Render**: https://render.com/docs/deploy-node-express-app
- **Environment Variables**: https://render.com/docs/environment-variables

### Comunidade

- **Render Community**: https://community.render.com
- **GitHub Issues**: https://github.com/MadagascarMods/graninha-bot-render/issues

### Status do Render

- **Status Page**: https://status.render.com

---

## 🎓 Próximos Passos

Após o deployment bem-sucedido, você pode:

1. **Configurar domínio customizado** (ex: `bot.seudominio.com`)
2. **Adicionar monitoramento** com serviços externos (UptimeRobot, Pingdom)
3. **Configurar notificações** para erros e downtime
4. **Otimizar performance** com caching e CDN
5. **Adicionar analytics** para rastrear uso

---

## ✅ Checklist Final

Antes de considerar o deployment completo, verifique:

- [ ] Repositório GitHub criado e código enviado
- [ ] Web Service criado no Render.com
- [ ] Variáveis de ambiente configuradas
- [ ] Build concluído com sucesso
- [ ] Serviço está "Live"
- [ ] Health check respondendo corretamente
- [ ] Interface acessível no navegador
- [ ] Bot funciona com Bearer Token válido
- [ ] Logs sendo gerados corretamente
- [ ] Auto-deploy configurado

---

## 🎉 Parabéns!

Seu **Graninha Bot** está agora hospedado no Render.com e pronto para uso!

**URL do Repositório**: https://github.com/MadagascarMods/graninha-bot-render

**Próximo Deploy**: Automático a cada push no branch `main`

---

**Desenvolvido com ❤️ para automação legítima do Graninha Bot**

**Versão**: 1.0.0  
**Data**: Dezembro 2025  
**Status**: ✅ Production Ready
