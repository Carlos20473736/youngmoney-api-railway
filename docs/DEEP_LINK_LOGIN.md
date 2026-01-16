# Sistema de Login por Deep Link - Young Money

## Visão Geral

Este documento descreve o sistema de **Login por Link** (Deep Link) implementado para o aplicativo Young Money. O sistema permite que usuários façam login através de uma página web no navegador, e o aplicativo Android detecta automaticamente o link de autenticação.

## Fluxo de Autenticação

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Navegador     │     │      API        │     │   App Android   │
│   (Web Login)   │     │   (Backend)     │     │  (Deep Link)    │
└────────┬────────┘     └────────┬────────┘     └────────┬────────┘
         │                       │                       │
         │ 1. Login Google       │                       │
         │──────────────────────>│                       │
         │                       │                       │
         │ 2. Gerar Token        │                       │
         │<──────────────────────│                       │
         │                       │                       │
         │ 3. Redirect Deep Link │                       │
         │ youngmoney://login?token=XXX                  │
         │──────────────────────────────────────────────>│
         │                       │                       │
         │                       │ 4. Validar Token      │
         │                       │<──────────────────────│
         │                       │                       │
         │                       │ 5. Dados do Usuário   │
         │                       │──────────────────────>│
         │                       │                       │
         │                       │        6. Login OK    │
         │                       │                       │
```

## Componentes

### 1. Página Web de Login (`/public/web-login/index.html`)

Página responsiva que permite login com Google e gera o deep link.

**URL de Acesso:**
```
https://youngmoney-api-railway-production.up.railway.app/public/web-login/
```

**Funcionalidades:**
- Login com Google (One Tap ou Popup)
- Geração de token temporário via API
- Redirecionamento automático para o app
- Fallback com cópia manual do token

### 2. API de Link Login (`/api/v1/auth/link-login.php`)

Endpoint que gerencia a geração e validação de tokens temporários.

**Endpoints:**

#### Gerar Token
```http
POST /api/v1/auth/link-login.php?action=generate
Content-Type: application/json

{
    "google_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Resposta de Sucesso:**
```json
{
    "status": "success",
    "data": {
        "link_token": "a1b2c3d4e5f6...",
        "expires_in": 300,
        "deep_link": "youngmoney://login?token=a1b2c3d4e5f6...",
        "user": {
            "name": "João Silva",
            "email": "joao@email.com",
            "profile_picture": "https://..."
        }
    }
}
```

#### Validar Token
```http
POST /api/v1/auth/link-login.php?action=validate
Content-Type: application/json

{
    "link_token": "a1b2c3d4e5f6..."
}
```

**Resposta de Sucesso:**
```json
{
    "status": "success",
    "data": {
        "token": "auth_token_here",
        "session_salt": "salt_here",
        "encrypted_seed": "seed_here",
        "user": {
            "id": 123,
            "email": "joao@email.com",
            "name": "João Silva",
            "google_id": "google_id_here",
            "profile_picture": "https://...",
            "points": 1500
        }
    }
}
```

### 3. Deep Link Activity (`DeepLinkActivity.java`)

Activity Android que processa os deep links recebidos.

**Deep Link Format:**
```
youngmoney://login?token=XXXXXXXX
```

**Funcionalidades:**
- Captura deep links via intent-filter
- Valida token na API
- Salva dados de autenticação
- Redireciona para MainActivity

### 4. Tabela de Tokens (`link_login_tokens`)

Tabela MySQL para armazenar tokens temporários.

**Estrutura:**
```sql
CREATE TABLE link_login_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    auth_token VARCHAR(255) NOT NULL,
    session_salt VARCHAR(64) NOT NULL,
    encrypted_seed TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);
```

## Configuração Android

### AndroidManifest.xml

```xml
<!-- Deep Link Activity para login via navegador -->
<activity
    android:name=".DeepLinkActivity"
    android:exported="true"
    android:launchMode="singleTask"
    android:theme="@style/Theme.YoungMoney">
    
    <!-- Deep Link Scheme: youngmoney://login?token=XXX -->
    <intent-filter>
        <action android:name="android.intent.action.VIEW" />
        <category android:name="android.intent.category.DEFAULT" />
        <category android:name="android.intent.category.BROWSABLE" />
        <data android:scheme="youngmoney" android:host="login" />
    </intent-filter>
    
    <!-- App Links (HTTPS) -->
    <intent-filter android:autoVerify="true">
        <action android:name="android.intent.action.VIEW" />
        <category android:name="android.intent.category.DEFAULT" />
        <category android:name="android.intent.category.BROWSABLE" />
        <data 
            android:scheme="https" 
            android:host="youngmoney-api-railway-production.up.railway.app" 
            android:pathPrefix="/app/login" />
    </intent-filter>
</activity>
```

## Segurança

### Medidas Implementadas

1. **Token Temporário:** Expira em 5 minutos (300 segundos)
2. **Uso Único:** Token é marcado como usado após validação
3. **Limpeza Automática:** Tokens expirados são removidos periodicamente
4. **HTTPS:** Comunicação criptografada entre web e API
5. **Validação de Google Token:** Token do Google é validado antes de gerar link

### Códigos de Erro

| Código | Descrição |
|--------|-----------|
| 20001 | Token não encontrado no link |
| 20002 | Token inválido |
| 20003 | Token expirado |
| 20004 | Erro de rede |

## Testes

### Testar Deep Link via ADB

```bash
# Testar deep link scheme
adb shell am start -W -a android.intent.action.VIEW \
    -d "youngmoney://login?token=test123" \
    com.youngmoney2

# Testar App Link HTTPS
adb shell am start -W -a android.intent.action.VIEW \
    -d "https://youngmoney-api-railway-production.up.railway.app/app/login?token=test123" \
    com.youngmoney2
```

### Testar API

```bash
# Gerar token (requer google_token válido)
curl -X POST "https://youngmoney-api-railway-production.up.railway.app/api/v1/auth/link-login.php?action=generate" \
    -H "Content-Type: application/json" \
    -d '{"google_token": "YOUR_GOOGLE_TOKEN"}'

# Validar token
curl -X POST "https://youngmoney-api-railway-production.up.railway.app/api/v1/auth/link-login.php?action=validate" \
    -H "Content-Type: application/json" \
    -d '{"link_token": "YOUR_LINK_TOKEN"}'
```

## Arquivos Modificados/Criados

### API (Backend)
- `/api/v1/auth/link-login.php` - Endpoint principal
- `/public/web-login/index.html` - Página de login web
- `/public/app/login/index.php` - Redirect para App Links
- `/.well-known/assetlinks.json` - Verificação de App Links
- `/migrations/create_link_login_tokens.sql` - Migration da tabela

### Android (APK)
- `AndroidManifest.xml` - Adicionado DeepLinkActivity
- `DeepLinkActivity.java` - Nova Activity para deep links
- `ApiClient.java` - Adicionado método `validateLinkToken()`

## Deploy

### 1. Deploy da API

```bash
# Fazer push das alterações para o repositório
cd /path/to/youngmoney-api-railway
git add .
git commit -m "feat: add deep link login system"
git push origin main

# Railway fará deploy automático
```

### 2. Executar Migration

A tabela será criada automaticamente na primeira requisição, mas você pode executar manualmente:

```bash
mysql -h HOST -u USER -p DATABASE < migrations/create_link_login_tokens.sql
```

### 3. Configurar App Links (Opcional)

Para App Links funcionarem, configure o arquivo `assetlinks.json`:

1. Obtenha o SHA256 fingerprint do seu certificado de assinatura
2. Atualize o arquivo `/.well-known/assetlinks.json`
3. Certifique-se que o arquivo é acessível via HTTPS

### 4. Build do APK

Compile o APK com as novas alterações:

```bash
cd /path/to/YoungMoney
./gradlew assembleRelease
```

## Troubleshooting

### Deep Link não abre o app

1. Verifique se o app está instalado
2. Confirme que o scheme `youngmoney` está correto
3. Teste via ADB para debug

### Token inválido ou expirado

1. Verifique se o token tem menos de 5 minutos
2. Confirme que o token não foi usado antes
3. Verifique logs do servidor para mais detalhes

### Erro de conexão

1. Verifique conectividade com a internet
2. Confirme que a URL da API está correta
3. Verifique se o servidor está online

## Suporte

Para problemas ou dúvidas, consulte os logs:

- **Android:** `adb logcat | grep DeepLinkActivity`
- **API:** Logs do Railway ou `error_log` do PHP
