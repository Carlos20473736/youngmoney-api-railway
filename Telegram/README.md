# Graninha Bot Render - Sistema Completo com Missões Diárias

## 📦 Estrutura do Projeto

Este projeto combina **painel de login e missões diárias** (inspirado no Young Money) com o **Graninha Bot v3.1**, criando um sistema completo de automação hospedado no Render.com.

### Arquivos Principais

```
graninha-bot-render/
├── server/
│   └── index.js                  # Backend Express com proxy para API Graninha
├── public/
│   ├── index.html                # Painel principal com login e missões
│   ├── graninha-bot.html         # Bot de automação Graninha
│   ├── graninha-bot-style.css    # Estilos do bot
│   ├── graninha-bot-script.js    # Script do bot (adaptado para backend proxy)
│   ├── session-security.js       # Segurança de sessão
│   └── assets/                   # Recursos React compilados
│       ├── index-Bt8lmjj0.js     # JavaScript principal (adaptado)
│       └── index-C85mVqpZ.css    # CSS principal (adaptado)
├── package.json                  # Dependências Node.js
├── render.yaml                   # Configuração de deployment Render
└── README.md                     # Este arquivo
```

## 🎯 Funcionalidades

### 1. Painel de Missões Diárias (index.html)

- **Login de usuário** com autenticação
- **Missões diárias** com sistema de recompensas
- **Assistir anúncios** para ganhar pontos
- **Dashboard** com estatísticas e progresso
- **Interface React** moderna e responsiva
- **Redirecionamento automático** para o bot após completar missões

### 2. Bot de Automação Graninha (graninha-bot.html)

- **Automação 100% legit** com simulação de vídeos
- **Raspadinhas, Roleta, Quiz e 33 Jogos**
- **Loop infinito** com intervalos realistas (5-15 minutos)
- **Logs em tempo real** de todas as ações
- **Estatísticas detalhadas** (saldo, ganhos, ações)
- **Integração com backend** (sem CORS proxy externo)

### 3. Backend Express (server/index.js)

- **Proxy genérico** para API Graninha
- **Aceita qualquer endpoint** dinamicamente
- **Headers corretos** para autenticação
- **Logs detalhados** de todas as requisições
- **Tratamento de erros** robusto

## 🚀 Como Fazer Deploy no Render.com

### Pré-requisitos

- Conta no GitHub (repositório já criado)
- Conta no Render.com (https://render.com)

### Passo 1: Acessar Render

1. Acesse: **https://dashboard.render.com**
2. Faça login com sua conta

### Passo 2: Criar Web Service

1. Clique em **"New +"** → **"Web Service"**
2. Procure por **"graninha-bot-render"**
3. Clique em **"Connect"**

### Passo 3: Configurar

| Campo | Valor |
|-------|-------|
| **Name** | `graninha-bot-render` |
| **Environment** | `Node` |
| **Build Command** | `npm install` |
| **Start Command** | `npm start` |
| **Plan** | `Free` |

### Passo 4: Variáveis de Ambiente

Adicione estas variáveis:

```
NODE_ENV = production
PORT = 3000
CORS_ORIGIN = *
GRANINHA_API_URL = https://painel.graninha.com.br/api/v1
```

### Passo 5: Deploy

1. Clique em **"Create Web Service"**
2. Aguarde 3-5 minutos
3. Acesse a URL gerada

## 🌐 Como Usar

### Acessar o Painel Principal

```
https://sua-url.onrender.com
```

Você verá o painel de missões diárias com:

1. **Tela de Login** - Faça login com seu e-mail
2. **Dashboard** - Veja suas estatísticas e missões
3. **Missões Diárias** - Complete tarefas e assista anúncios
4. **Botão "Graninha Bot"** - Acesse o bot de automação

### Acessar o Bot Diretamente

```
https://sua-url.onrender.com/graninha-bot.html
```

Você verá a interface do bot com:

1. **Configuração** - Insira Bearer Token e EX ID
2. **Status** - Acompanhe saldo e ganhos
3. **Logs** - Veja todas as ações em tempo real
4. **Loop Infinito** - Ative para execução contínua

## 🔄 Fluxo de Uso Completo

### 1. Usuário Acessa o Painel

```
https://sua-url.onrender.com
```

### 2. Faz Login

- Insere e-mail
- Sistema autentica

### 3. Vê Missões Diárias

- Missões disponíveis
- Progresso atual
- Recompensas

### 4. Assiste Anúncios

- Clica em "Assistir Anúncio"
- Aguarda tempo do anúncio
- Recebe recompensa

### 5. Acessa o Bot

- Clica em "Graninha Bot" ou botão similar
- É redirecionado para `/graninha-bot.html`

### 6. Configura o Bot

- Insere **Bearer Token** (via HTTP Catcher)
- Insere **EX ID**
- Ativa **Loop Infinito** (opcional)

### 7. Inicia Automação

- Bot começa a jogar automaticamente
- Raspadinhas, Roleta, Quiz, Jogos
- Logs em tempo real
- Saldo atualizado

## 🤖 Endpoints da API

O backend fornece um endpoint genérico que aceita qualquer requisição:

### POST /api/:endpoint

Proxy genérico para API Graninha.

**Parâmetros:**

```json
{
  "bearer_token": "seu_token_aqui",
  "ex_id": "seu_ex_id",
  "data": "payload_criptografado"
}
```

**Exemplos de endpoints:**

- `/api/user` - Obtém informações do usuário
- `/api/datas` - Verifica limites
- `/api/scratch` - Joga raspadinha
- `/api/roulette` - Joga roleta
- `/api/quiz` - Responde quiz
- `/api/game` - Joga um dos 33 jogos

### GET /health

Verifica o status do servidor.

```bash
curl https://sua-url.onrender.com/health
```

## 📝 Diferenças vs Versão Anterior

| Aspecto | Versão Anterior | Versão Atual |
|---------|----------------|--------------|
| **Painel de Missões** | ❌ Não tinha | ✅ Completo com React |
| **Login** | ❌ Não tinha | ✅ Sistema de autenticação |
| **Missões Diárias** | ❌ Não tinha | ✅ Com anúncios e recompensas |
| **Bot** | ✅ Simples | ✅ Integrado com painel |
| **CORS** | ⚠️ Proxy externo | ✅ Backend próprio |
| **Arquitetura** | Frontend puro | Frontend + Backend |
| **Redirecionamento** | ❌ Manual | ✅ Automático após missões |

## 🔧 Adaptações Realizadas

### 1. Aplicação React (index.html)

- ✅ Copiado do Young Money Tasks
- ✅ Substituído "Young Money" por "Graninha"
- ✅ Substituído "Pix Assistindo" por "Graninha Bot"
- ✅ Atualizado link de redirecionamento para `/graninha-bot.html`
- ✅ Mantida toda a lógica React original

### 2. Bot Graninha (graninha-bot.html)

- ✅ Renomeado de `index.html` para `graninha-bot.html`
- ✅ Script adaptado para usar backend proxy
- ✅ Removido `corsproxy.io` externo
- ✅ Integrado com sistema de missões

### 3. Backend Express (server/index.js)

- ✅ Criado endpoint genérico `/api/:endpoint`
- ✅ Aceita qualquer requisição dinamicamente
- ✅ Headers corretos para API Graninha
- ✅ Logs detalhados

### 4. Arquivos Estáticos

- ✅ `assets/index-Bt8lmjj0.js` - JavaScript React adaptado
- ✅ `assets/index-C85mVqpZ.css` - CSS React adaptado
- ✅ `session-security.js` - Segurança de sessão mantida

## 🔐 Segurança

### Bearer Token

- **Nunca compartilhe** seu Bearer Token
- **Obtido via HTTP Catcher** no app Graninha
- **Expira** após algumas horas
- **Armazenado** apenas no navegador (não no servidor)

### EX ID

- **Específico** para cada usuário
- **Encontrado** nas requisições do app
- **Necessário** para todas as operações

### Sessão

- **Sistema de segurança** de sessão única
- **Previne** múltiplas abas abertas
- **Protege** contra uso indevido

## 📊 Monitoramento

### Logs do Servidor (Render Dashboard)

```
[PROXY] Requisição para user
[PROXY] user - sucesso
[PROXY] Requisição para scratch
[PROXY] scratch - sucesso
```

### Logs do Bot (Interface)

```
📺 Carregando propaganda para raspadinha...
📹 Assistindo propaganda... (32.5s)
✅ Propaganda concluída, fechando...
🎰 Jogando raspadinha...
💰 Ganhou: 15 pontos
```

## 🎯 Próximos Passos

Após o deployment:

1. **Testar o painel** de missões
2. **Fazer login** com e-mail
3. **Completar missões** e assistir anúncios
4. **Acessar o bot** via botão ou URL direta
5. **Configurar o bot** com Bearer Token e EX ID
6. **Iniciar automação** e acompanhar logs

## 📞 Suporte

### Documentação

- **README.md** - Este arquivo
- **DEPLOYMENT_GUIDE.md** - Guia de deployment detalhado
- **RESUMO_PROJETO.md** - Resumo executivo

### Recursos

- **Render Docs**: https://render.com/docs
- **GitHub Repo**: https://github.com/MadagascarMods/graninha-bot-render
- **Render Community**: https://community.render.com

## 📄 Licença

MIT

## 🎯 Versão

- **Graninha Bot**: v3.1
- **Painel de Missões**: Young Money Template (adaptado)
- **Backend**: Express.js
- **Data**: Dezembro 2025
- **Status**: ✅ Pronto para Deploy

---

**Desenvolvido com ❤️ para automação legítima do Graninha Bot**

**Sistema completo:** Painel de Missões + Bot de Automação + Backend Proxy
