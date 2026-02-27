# 🚀 Guia Rápido - Sistema Completo Graninha

## 📋 O Que Foi Adicionado

Agora o projeto tem **DUAS PÁGINAS** integradas:

### 1️⃣ Painel de Missões Diárias (index.html)

**URL:** `https://sua-url.onrender.com`

- ✅ Sistema de login com e-mail
- ✅ Dashboard com estatísticas
- ✅ Missões diárias para completar
- ✅ Assistir anúncios para ganhar recompensas
- ✅ Botão para acessar o bot
- ✅ Interface React completa (igual Young Money)

### 2️⃣ Bot de Automação Graninha (graninha-bot.html)

**URL:** `https://sua-url.onrender.com/graninha-bot.html`

- ✅ Configuração com Bearer Token e EX ID
- ✅ Automação de raspadinhas, roleta, quiz e jogos
- ✅ Loop infinito com intervalos realistas
- ✅ Logs em tempo real
- ✅ Estatísticas detalhadas

---

## 🔄 Fluxo de Uso

```
1. Usuário acessa → https://sua-url.onrender.com
   ↓
2. Faz login com e-mail
   ↓
3. Vê dashboard com missões diárias
   ↓
4. Clica em "Assistir Anúncio" nas missões
   ↓
5. Aguarda anúncio e recebe recompensa
   ↓
6. Clica em botão "Graninha Bot" ou acessa /graninha-bot.html
   ↓
7. Insere Bearer Token e EX ID
   ↓
8. Clica em "Iniciar Bot"
   ↓
9. Bot automatiza tudo (raspadinhas, roleta, quiz, jogos)
   ↓
10. Acompanha logs e saldo em tempo real
```

---

## 🎯 Como Testar Localmente

### 1. Instalar Dependências

```bash
cd graninha-bot-render
npm install
```

### 2. Iniciar Servidor

```bash
npm start
```

### 3. Acessar no Navegador

**Painel de Missões:**
```
http://localhost:3000
```

**Bot Direto:**
```
http://localhost:3000/graninha-bot.html
```

---

## 🌐 Após Deploy no Render

### Acessar Painel Principal

```
https://graninha-bot-render.onrender.com
```

Você verá:

- **Tela de Login** - Digite seu e-mail
- **Dashboard** - Veja suas estatísticas
- **Missões** - Complete tarefas diárias
- **Anúncios** - Assista e ganhe recompensas
- **Botão "Bot"** - Acesse o bot de automação

### Acessar Bot Diretamente

```
https://graninha-bot-render.onrender.com/graninha-bot.html
```

Você verá:

- **Configuração** - Bearer Token e EX ID
- **Status** - Saldo inicial, atual, ganhos
- **Progresso** - Raspadinhas, roleta, quiz, jogos
- **Logs** - Todas as ações em tempo real

---

## 🔑 Como Obter Bearer Token e EX ID

### Bearer Token

1. Instale **HTTP Catcher** no celular
2. Abra o app **Graninha**
3. Faça qualquer ação (jogar, assistir, etc.)
4. No HTTP Catcher, procure requisições para `painel.graninha.com.br`
5. Copie o valor do header `Authorization: Bearer XXXXX`
6. Use apenas a parte `XXXXX` (sem "Bearer ")

### EX ID

1. No HTTP Catcher, veja o corpo (body) das requisições
2. Procure por um campo chamado `ex_id` ou similar
3. Copie o valor (geralmente um número longo)
4. Use no bot

---

## 📝 Estrutura de Arquivos

```
graninha-bot-render/
├── public/
│   ├── index.html                 # ← PAINEL DE MISSÕES (página principal)
│   ├── graninha-bot.html          # ← BOT DE AUTOMAÇÃO
│   ├── graninha-bot-style.css     # Estilos do bot
│   ├── graninha-bot-script.js     # Script do bot
│   ├── session-security.js        # Segurança de sessão
│   └── assets/
│       ├── index-Bt8lmjj0.js      # React compilado (adaptado)
│       └── index-C85mVqpZ.css     # CSS React (adaptado)
│
├── server/
│   └── index.js                   # Backend Express com proxy
│
├── package.json
├── render.yaml
├── README.md
├── DEPLOYMENT_GUIDE.md
├── RESUMO_PROJETO.md
└── GUIA_RAPIDO.md                 # ← ESTE ARQUIVO
```

---

## 🔧 Adaptações Realizadas

### No Painel (index.html)

✅ Copiado do Young Money Tasks  
✅ Substituído "Young Money" → "Graninha"  
✅ Substituído "Pix Assistindo" → "Graninha Bot"  
✅ Atualizado link de redirecionamento  
✅ Mantida toda a lógica React original  

### No Bot (graninha-bot.html)

✅ Renomeado de `index.html`  
✅ Adaptado para usar backend proxy  
✅ Removido `corsproxy.io` externo  
✅ Integrado com sistema de missões  

### No Backend (server/index.js)

✅ Criado endpoint genérico `/api/:endpoint`  
✅ Aceita qualquer requisição dinamicamente  
✅ Headers corretos para API Graninha  
✅ Logs detalhados de todas as operações  

---

## 🚨 Importante

### Bearer Token Expira

- Tokens expiram após algumas horas
- Se o bot parar de funcionar, obtenha um novo token
- Use HTTP Catcher para capturar novo token

### Cold Start no Render (Plano Free)

- Serviço hiberna após 15 minutos de inatividade
- Primeira requisição pode demorar 30-60 segundos
- Use UptimeRobot para fazer ping a cada 10 minutos
- Ou upgrade para plano Starter ($7/mês) sem cold start

### Segurança

- **Nunca compartilhe** seu Bearer Token
- **Não exponha** seu EX ID publicamente
- **Use** o sistema apenas para fins legítimos
- **Revise** logs regularmente

---

## 📊 Endpoints da API

O backend fornece um proxy genérico:

### POST /api/:endpoint

**Aceita qualquer endpoint da API Graninha:**

- `/api/user` - Informações do usuário
- `/api/datas` - Verifica limites
- `/api/scratch` - Raspadinha
- `/api/roulette` - Roleta
- `/api/quiz` - Quiz
- `/api/game` - Jogos

**Formato da requisição:**

```json
{
  "bearer_token": "seu_token",
  "ex_id": "seu_ex_id",
  "data": "payload_criptografado"
}
```

### GET /health

Verifica status do servidor:

```bash
curl https://sua-url.onrender.com/health
```

---

## 🎯 Checklist de Deploy

- [ ] Repositório GitHub criado e atualizado
- [ ] Render.com conectado ao repositório
- [ ] Variáveis de ambiente configuradas
- [ ] Build concluído com sucesso
- [ ] Serviço está "Live"
- [ ] Painel principal acessível (index.html)
- [ ] Bot acessível (graninha-bot.html)
- [ ] Health check respondendo
- [ ] Logs sendo gerados corretamente

---

## 🆘 Solução de Problemas

### Painel não carrega

- Verifique se o build foi concluído
- Veja os logs no Render Dashboard
- Confirme que `index.html` existe em `public/`
- Limpe o cache do navegador

### Bot não conecta à API

- Verifique se o Bearer Token está correto
- Confirme que o EX ID está correto
- Veja os logs do servidor no Render
- Teste o endpoint `/health`

### Erro 404 ao acessar bot

- URL correta: `/graninha-bot.html` (com .html)
- Verifique se o arquivo existe em `public/`
- Confirme que o servidor está rodando

### CORS Error

- Não deveria acontecer (backend proxy resolve isso)
- Se acontecer, verifique variável `CORS_ORIGIN=*`
- Veja logs do servidor para detalhes

---

## 📞 Suporte

### Documentação Completa

- **README.md** - Documentação principal
- **DEPLOYMENT_GUIDE.md** - Guia de deployment
- **RESUMO_PROJETO.md** - Resumo executivo
- **GUIA_RAPIDO.md** - Este arquivo

### Links Úteis

- **Repositório**: https://github.com/MadagascarMods/graninha-bot-render
- **Render Docs**: https://render.com/docs
- **Render Dashboard**: https://dashboard.render.com

---

## ✅ Resumo

### O Que Você Tem Agora

1. **Painel de Missões** (index.html) - Sistema completo com login, missões, anúncios
2. **Bot de Automação** (graninha-bot.html) - Automação de raspadinhas, roleta, quiz, jogos
3. **Backend Express** - Proxy para API Graninha, sem CORS
4. **Deploy Automático** - Push no GitHub = deploy no Render

### Como Usar

1. Acesse o painel → Faça login → Complete missões
2. Clique no botão do bot → Configure → Inicie automação
3. Acompanhe logs e estatísticas em tempo real

### Próximos Passos

1. Fazer deploy no Render.com
2. Testar o painel de missões
3. Obter Bearer Token e EX ID
4. Configurar e iniciar o bot
5. Monitorar logs e resultados

---

**🎉 Sistema completo pronto para uso!**

**Desenvolvido com ❤️ para automação legítima do Graninha Bot**
