# Atualização do App YoungMoney - Botão Assistir Anúncio com YMID

## Alterações Realizadas

### 1. Modificação do SpinWheelActivity.java

O botão "Assistir Anúncio" na tela da roleta foi atualizado para abrir o navegador externo do celular com o YMID do usuário.

**Arquivo modificado:** `app/src/main/java/com/youngmoney2/SpinWheelActivity.java`

**Alterações:**

#### a) Import adicionado:
```java
import android.net.Uri;
```

#### b) Método `openAdWebView()` atualizado:

**Antes:**
```java
@JavascriptInterface
public void openAdWebView() {
    Log.d(TAG, "openAdWebView() chamado - Abrindo tela de anúncio");
    runOnUiThread(() -> {
        Intent intent = new Intent(SpinWheelActivity.this, AdWebViewActivity.class);
        startActivity(intent);
    });
}
```

**Depois:**
```java
@JavascriptInterface
public void openAdWebView() {
    Log.d(TAG, "openAdWebView() chamado - Abrindo navegador externo com YMID");
    runOnUiThread(() -> {
        // Obter o YMID do usuário
        SessionManager sessionManager = SessionManager.getInstance(SpinWheelActivity.this);
        String ymid = sessionManager.getUserId();
        
        // Construir URL com o YMID
        String baseUrl = "https://5000-ic0tesgwq81zrlum2ysh3-d51dcffc.us2.manus.computer";
        String url = baseUrl + "?ymid=" + (ymid != null ? ymid : "");
        
        Log.d(TAG, "Abrindo URL no navegador: " + url);
        
        // Abrir no navegador externo do celular
        Intent browserIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
        startActivity(browserIntent);
    });
}
```

## Comportamento Esperado

Quando o usuário clicar no botão **"Assistir Anúncio"** na tela da roleta:

1. O app obtém o YMID (ID do usuário) do `SessionManager`
2. Constrói a URL: `https://5000-ic0tesgwq81zrlum2ysh3-d51dcffc.us2.manus.computer?ymid=USER_ID`
3. Abre o navegador externo do celular (Chrome, Firefox, etc.) com essa URL
4. O site YoungMoney recebe o parâmetro `ymid` na query string

## Como Usar

### Passo 1: Atualizar o Projeto Android

1. Abra o projeto Android Studio do YoungMoney
2. Substitua o arquivo `app/src/main/java/com/youngmoney2/SpinWheelActivity.java` pelo arquivo atualizado
3. Compile e faça build do APK

### Passo 2: Substituir a URL Permanente

A URL atual (`https://5000-ic0tesgwq81zrlum2ysh3-d51dcffc.us2.manus.computer`) é temporária.

**Você precisa substituir por sua URL permanente:**

No arquivo `SpinWheelActivity.java`, linha ~489:
```java
String baseUrl = "https://5000-ic0tesgwq81zrlum2ysh3-d51dcffc.us2.manus.computer";
```

Altere para sua URL permanente:
```java
String baseUrl = "https://seu-dominio.com";
```

### Passo 3: Fazer Deploy

1. Compile e gere o APK assinado
2. Faça upload para a Google Play Store ou distribua via link direto

## Arquivo Atualizado

O arquivo `SpinWheelActivity.java` atualizado está disponível em:
- `app-source/android/SpinWheelActivity.java`

## Notas Importantes

- ✅ O YMID é obtido automaticamente do usuário logado
- ✅ A URL é construída dinamicamente com o YMID como parâmetro
- ✅ O navegador externo é aberto (não a WebView interna)
- ⚠️ A URL temporária do Manus ficará indisponível após o sandbox ser desligado
- ⚠️ Você DEVE substituir pela sua URL permanente antes de fazer deploy em produção

## Próximos Passos

1. Atualizar a URL permanente no código
2. Testar em um dispositivo Android
3. Fazer build do APK assinado
4. Fazer upload para a Play Store ou distribuir
5. Configurar o site para receber e processar o parâmetro `ymid`
