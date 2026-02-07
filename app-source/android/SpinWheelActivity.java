package com.youngmoney2;

import android.annotation.SuppressLint;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.content.res.Configuration;
import android.content.res.Resources;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.util.DisplayMetrics;
import android.util.Log;
import android.webkit.JavascriptInterface;
import android.webkit.WebSettings;
import android.webkit.WebView;

import androidx.appcompat.app.AppCompatActivity;

import com.youngmoney2.api.ApiClient;
import com.youngmoney2.api.ApiResponse;
import com.youngmoney2.utils.SessionManager;
import com.youngmoney2.security.SecurityConfig;

import org.json.JSONObject;

/**
 * SpinWheelActivity - Roleta profissional com WebView em tela cheia
 */
public class SpinWheelActivity extends AppCompatActivity {
    private static final String TAG = "SpinWheelActivity";
    private static final int MIN_DPI_THRESHOLD = 411;
    // Fator de escala: quanto menor o DPI original, mais reduzimos para caber na tela
    private static final float SCALE_FACTOR = 0.65f; // Reduz para 65% do tamanho
    private int originalDpi = 0;
    private int targetDpi = 0;

    private WebView webView;
    private ApiClient apiClient;
    private int spinsRemaining = 0;
    private int spinsToday = 0;
    private int maxDailySpins = 10;
    private boolean isSpinning = false;
    private boolean webViewLoaded = false;
    private String pendingPrizeValues = null;

    // Valores possíveis de pontos na roleta (sincronizados com API e JavaScript)


    @Override
    protected void attachBaseContext(Context newBase) {
        super.attachBaseContext(applyDpiIfNeeded(newBase));
    }

    /**
     * Aplica DPI customizado apenas se o DPI do dispositivo for menor que 411
     * DIMINUI o DPI para que os elementos fiquem menores e caibam na tela
     */
    private Context applyDpiIfNeeded(Context context) {
        try {
            Resources resources = context.getResources();
            DisplayMetrics displayMetrics = resources.getDisplayMetrics();
            originalDpi = displayMetrics.densityDpi;

            if (originalDpi < MIN_DPI_THRESHOLD) {
                // Calcular DPI reduzido para que elementos fiquem menores
                targetDpi = (int) (originalDpi * SCALE_FACTOR);
                Log.d(TAG, "DPI baixo detectado: " + originalDpi + " - Reduzindo para " + targetDpi + " (escala " + SCALE_FACTOR + ")");
                Configuration configuration = new Configuration(resources.getConfiguration());
                configuration.densityDpi = targetDpi;
                return context.createConfigurationContext(configuration);
            } else {
                Log.d(TAG, "DPI adequado: " + originalDpi + " - Nenhum ajuste necessário");
            }
        } catch (Exception e) {
            Log.e(TAG, "Erro ao verificar/aplicar DPI: " + e.getMessage());
        }
        return context;
    }

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Aplicar DPI adicional no onCreate se necessário
        applyDpiOnCreate();

        setContentView(R.layout.activity_spin_wheel);

        // Initialize API Client
        apiClient = ApiClient.getInstance(this);

        // Initialize views
        webView = findViewById(R.id.spin_wheel_webview);

        // Setup WebView
        setupWebView();

        // Load user spin data
        loadSpinData();

        // Verificar se a tarefa foi concluída
        checkTaskCompletionStatus();
    }

    /**
     * Aplica DPI customizado no onCreate para garantir consistência
     * DIMINUI o DPI para que os elementos fiquem menores
     */
    private void applyDpiOnCreate() {
        try {
            Resources resources = getResources();
            DisplayMetrics displayMetrics = resources.getDisplayMetrics();

            if (originalDpi == 0) {
                originalDpi = displayMetrics.densityDpi;
                targetDpi = (int) (originalDpi * SCALE_FACTOR);
            }

            if (originalDpi < MIN_DPI_THRESHOLD && targetDpi > 0) {
                displayMetrics.densityDpi = targetDpi;
                displayMetrics.density = targetDpi / 160f;
                displayMetrics.scaledDensity = targetDpi / 160f;

                Configuration configuration = resources.getConfiguration();
                configuration.densityDpi = targetDpi;

                resources.updateConfiguration(configuration, displayMetrics);

                Log.d(TAG, "DPI reduzido no onCreate: " + originalDpi + " -> " + targetDpi);
            }
        } catch (Exception e) {
            Log.e(TAG, "Erro ao aplicar DPI no onCreate: " + e.getMessage());
        }
    }

    @Override
    public void onConfigurationChanged(Configuration newConfig) {
        super.onConfigurationChanged(newConfig);
        // Reaplicar DPI se necessário após mudança de configuração
        if (originalDpi > 0 && originalDpi < MIN_DPI_THRESHOLD) {
            applyDpiOnCreate();
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        // Limpar cache do WebView para garantir que carrega a versão mais recente
        webView.clearCache(true);
        webView.clearHistory();

        WebSettings webSettings = webView.getSettings();
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setAllowFileAccess(true);
        webSettings.setAllowContentAccess(true);
        webSettings.setCacheMode(WebSettings.LOAD_NO_CACHE); // Forçar reload sem cache

        // Adicionar interface JavaScript
        webView.addJavascriptInterface(new WebAppInterface(), "Android");

        // Habilitar console do JavaScript
        webView.setWebChromeClient(new android.webkit.WebChromeClient() {
            @Override
            public boolean onConsoleMessage(android.webkit.ConsoleMessage consoleMessage) {
                Log.d(TAG, "[WebView Console] " + consoleMessage.message() + " -- From line " + consoleMessage.lineNumber() + " of " + consoleMessage.sourceId());
                return true;
            }
        });

        // Adicionar WebViewClient para detectar quando página carregou
        webView.setWebViewClient(new android.webkit.WebViewClient() {
            @Override
            public void onPageFinished(android.webkit.WebView view, String url) {
                super.onPageFinished(view, url);
                Log.d(TAG, "WebView onPageFinished");
                webViewLoaded = true;

                // Atualizar contador e valores se já foram carregados da API
                if (pendingPrizeValues != null) {
                    Log.d(TAG, "Setting prize values in onPageFinished: " + pendingPrizeValues);
                    webView.evaluateJavascript("if(typeof setPrizeValues === 'function') setPrizeValues(" + pendingPrizeValues + ")", null);
                } else {
                    Log.d(TAG, "No pending prize values yet");
                }
                Log.d(TAG, "Calling updateSpinsCounter with spins: " + spinsRemaining);
                updateSpinsCounter();

                // Se API já carregou (spinsRemaining != 3 padrão), esconder loading
                if (spinsRemaining != 3 || pendingPrizeValues != null) {
                    // Loading is now controlled by JavaScript
                }
            }
        });

        // Carregar HTML da roleta
        webView.loadUrl("file:///android_asset/spin_wheel/index.html");
    }

    /**
     * Carrega dados de giros disponíveis do usuário diretamente do servidor
     */
    /**
     * Verificar se a tarefa foi concluída via API
     * Se foi concluída: mostrar botão "Girar Roleta"
     * Se não foi: mostrar "Assistir Anúncio" e "Realize a tarefa"
     */
    private void checkTaskCompletionStatus() {
        Log.d(TAG, "🔍 Verificando status da tarefa...");

        ApiClient apiClient = ApiClient.getInstance(this);
        String userId = apiClient.getUserId();

        if (userId == null || userId.isEmpty()) {
            Log.e(TAG, "❌ User ID não encontrado para verificar tarefa");
            return;
        }

        // Primeiro buscar o número de impressões necessárias da API YoungMoney
        String progressApiUrl = SecurityConfig.getMontagProgressEndpoint() + "?user_id=" + userId;
        String statsApiUrl = SecurityConfig.getMontagStatsEndpoint() + userId;

        Log.d(TAG, "📡 Chamando API Progress: " + progressApiUrl);
        Log.d(TAG, "📡 Chamando API Stats: " + statsApiUrl);

        new Thread(() -> {
            try {
                // 1. Buscar requisitos da API (impressões necessárias randomizadas)
                int requiredImpressions = 5; // valor padrão

                try {
                    java.net.URL progressUrl = new java.net.URL(progressApiUrl);
                    java.net.HttpURLConnection progressConn = (java.net.HttpURLConnection) progressUrl.openConnection();
                    progressConn.setRequestMethod("GET");
                    progressConn.setConnectTimeout(10000);
                    progressConn.setReadTimeout(10000);

                    int progressResponseCode = progressConn.getResponseCode();
                    Log.d(TAG, "📊 Resposta API Progress: " + progressResponseCode);

                    if (progressResponseCode == java.net.HttpURLConnection.HTTP_OK) {
                        java.io.BufferedReader progressReader = new java.io.BufferedReader(
                                new java.io.InputStreamReader(progressConn.getInputStream())
                        );
                        StringBuilder progressResponse = new StringBuilder();
                        String progressLine;
                        while ((progressLine = progressReader.readLine()) != null) {
                            progressResponse.append(progressLine);
                        }
                        progressReader.close();

                        JSONObject progressJson = new JSONObject(progressResponse.toString());
                        Log.d(TAG, "📋 Progress JSON: " + progressJson.toString());

                        if (progressJson.optBoolean("success", false)) {
                            JSONObject progressData = progressJson.optJSONObject("data");
                            if (progressData != null) {
                                requiredImpressions = progressData.optInt("required_impressions", 5);
                                Log.d(TAG, "🎯 Requisitos da API: " + requiredImpressions + " impressões");
                            }
                        }
                    }
                    progressConn.disconnect();
                } catch (Exception e) {
                    Log.e(TAG, "⚠️ Erro ao buscar requisitos, usando padrão: " + e.getMessage());
                }

                // 2. Buscar stats do usuário
                java.net.URL url = new java.net.URL(statsApiUrl);
                java.net.HttpURLConnection connection = (java.net.HttpURLConnection) url.openConnection();
                connection.setRequestMethod("GET");
                connection.setConnectTimeout(10000);
                connection.setReadTimeout(10000);

                int responseCode = connection.getResponseCode();
                Log.d(TAG, "📊 Resposta da API Stats: " + responseCode);

                if (responseCode == java.net.HttpURLConnection.HTTP_OK) {
                    java.io.BufferedReader reader = new java.io.BufferedReader(
                            new java.io.InputStreamReader(connection.getInputStream())
                    );
                    StringBuilder response = new StringBuilder();
                    String line;

                    while ((line = reader.readLine()) != null) {
                        response.append(line);
                    }
                    reader.close();

                    JSONObject jsonResponse = new JSONObject(response.toString());
                    Log.d(TAG, "📋 Resposta JSON Stats: " + jsonResponse.toString());

                    int totalImpressions = jsonResponse.optInt("total_impressions", 0);

                    Log.d(TAG, "📊 Stats do usuário: " + totalImpressions + " impressões");
                    Log.d(TAG, "🎯 Requisitos: " + requiredImpressions + " impressões");

                    // Verificar se a tarefa foi concluída (APENAS IMPRESSÕES)
                    final int finalRequiredImpressions = requiredImpressions;
                    boolean taskCompleted = totalImpressions >= requiredImpressions;

                    Log.d(TAG, (taskCompleted ? "✅" : "⏳") + " Tarefa concluída: " + taskCompleted +
                            " (" + totalImpressions + "/" + requiredImpressions + " impressões)");

                    // Enviar resultado para o HTML
                    runOnUiThread(() -> {
                        if (webViewLoaded) {
                            String jsCommand = "if(typeof setTaskCompletionStatus === 'function') setTaskCompletionStatus(" + taskCompleted + ")";
                            Log.d(TAG, "📤 Enviando para HTML: " + jsCommand);
                            webView.evaluateJavascript(jsCommand, null);
                        } else {
                            Log.d(TAG, "⏳ WebView ainda não carregou, tentando novamente em 500ms");
                            new Handler(Looper.getMainLooper()).postDelayed(
                                    this::checkTaskCompletionStatus,
                                    500
                            );
                        }
                    });
                } else {
                    Log.e(TAG, "❌ Erro na API Stats: " + responseCode);
                }

                connection.disconnect();

            } catch (Exception e) {
                Log.e(TAG, "❌ Erro ao verificar tarefa: " + e.getMessage());
                e.printStackTrace();
            }
        }).start();
    }

    private void loadSpinData() {
        Log.d(TAG, "loadSpinData() called");
        apiClient.getSpinsRemaining(new ApiClient.ApiCallback() {
            @Override
            public void onSuccess(JSONObject response) {
                Log.d(TAG, "API Success: " + response.toString());
                runOnUiThread(() -> {
                    try {
                        ApiResponse apiResponse = new ApiResponse(response);
                        Log.d(TAG, "ApiResponse.isSuccess(): " + apiResponse.isSuccess());
                        Log.d(TAG, "ApiResponse.hasData(): " + apiResponse.hasData());
                        if (apiResponse.isSuccess() && apiResponse.hasData()) {
                            JSONObject data = apiResponse.getData();
                            Log.d(TAG, "Data object: " + data.toString());

                            // Obter giros restantes do servidor
                            spinsRemaining = data.optInt("spins_remaining", 0);
                            spinsToday = data.optInt("spins_today", 0);
                            maxDailySpins = data.optInt("max_daily_spins", 10);
                            Log.d(TAG, "Spins from server - remaining: " + spinsRemaining + ", today: " + spinsToday + ", max: " + maxDailySpins);

                            // Atualizar timestamp do servidor
                            long serverTimestamp = data.optLong("server_timestamp", 0);
                            if (serverTimestamp > 0) {
                                NetworkTimeManager.getInstance(SpinWheelActivity.this).updateServerTime(serverTimestamp);
                            }

                            // Obter valores da roleta do servidor
                            try {
                                org.json.JSONArray prizeValuesArray = data.getJSONArray("prize_values");
                                StringBuilder prizeValuesJs = new StringBuilder("[");
                                for (int i = 0; i < prizeValuesArray.length(); i++) {
                                    if (i > 0) prizeValuesJs.append(",");
                                    prizeValuesJs.append(prizeValuesArray.getInt(i));
                                }
                                prizeValuesJs.append("]");

                                // Armazenar valores para usar quando WebView carregar
                                pendingPrizeValues = prizeValuesJs.toString();
                                Log.d(TAG, "Prize values: " + pendingPrizeValues);

                                // Se WebView já carregou, atualizar valores imediatamente
                                if (webViewLoaded) {
                                    Log.d(TAG, "Updating prize values immediately");
                                    webView.evaluateJavascript("if(typeof setPrizeValues === 'function') setPrizeValues(" + pendingPrizeValues + ")", null);
                                } else {
                                    Log.d(TAG, "WebView not loaded yet, values will be set in onPageFinished");
                                }
                            } catch (Exception e) {
                                Log.e(TAG, "Error processing prize_values: " + e.getMessage());
                            }

                            // Atualizar contador (SEMPRE, independente de prize_values)
                            if (webViewLoaded) {
                                Log.d(TAG, "Updating counter immediately");
                                updateSpinsCounter();
                                // Loading is now controlled by JavaScript // Esconder loading após carregar dados
                            } else {
                                Log.d(TAG, "WebView not loaded yet, counter will be updated in onPageFinished");
                            }
                        } else {
                            // Se der erro, mostrar 0 giros
                            Log.e(TAG, "API response invalid - isSuccess: " + apiResponse.isSuccess() + ", hasData: " + apiResponse.hasData());
                            spinsRemaining = 0;
                            updateSpinsCounter();
                        }
                    } catch (Exception e) {
                        Log.e(TAG, "Exception processing API response: " + e.getMessage(), e);
                        spinsRemaining = 0;
                        updateSpinsCounter();
                    }
                });
            }

            @Override
            public void onError(String error) {
                runOnUiThread(() -> {
                    spinsRemaining = 0;
                    updateSpinsCounter();
                    showToast("Erro ao carregar giros: " + error);
                });
            }
        });
    }



    /**
     * Atualiza o contador de giros no HTML
     */
    private void updateSpinsCounter() {
        Log.d(TAG, "updateSpinsCounter called - webViewLoaded: " + webViewLoaded + ", spins: " + spinsRemaining + ", today: " + spinsToday + ", max: " + maxDailySpins);
        if (webViewLoaded) {
            String js = "updateSpinsCounter(" + spinsRemaining + ", " + spinsToday + ", " + maxDailySpins + ")";
            Log.d(TAG, "Executing JS: " + js);
            webView.evaluateJavascript(js, null);
        } else {
            Log.d(TAG, "WebView not loaded, skipping update");
        }
    }

    /**
     * Executa a animação de girar a roleta
     * Agora usa API do servidor que decide o prêmio e valida giros
     */
    private void spinWheel() {
        if (isSpinning || spinsRemaining <= 0) return;

        isSpinning = true;

        // Chamar API do servidor para executar giro
        apiClient.executeSpin(new ApiClient.ApiCallback() {
            @Override
            public void onSuccess(JSONObject response) {
                runOnUiThread(() -> {
                    try {
                        ApiResponse apiResponse = new ApiResponse(response);

                        if (apiResponse.isSuccess() && apiResponse.hasData()) {
                            JSONObject data = apiResponse.getData();

                            // Extrair dados do giro
                            int prizeValue = data.getInt("prize_value");
                            int prizeIndex = data.getInt("prize_index");
                            int spinsRemainingFromServer = data.getInt("spins_remaining");
                            int newBalance = data.getInt("new_balance");

                            // Atualizar timestamp do servidor se disponível
                            long serverTimestamp = data.optLong("server_timestamp", 0);
                            if (serverTimestamp > 0) {
                                NetworkTimeManager.getInstance(SpinWheelActivity.this).updateServerTime(serverTimestamp);
                            }

                            // Atualizar giros restantes E incrementar spins_today
                            spinsRemaining = spinsRemainingFromServer;
                            spinsToday++; // Incrementar contador de giros hoje

                            // Chamar função JavaScript para animar usando o VALOR do prêmio
                            Log.d(TAG, "Calling spin with prize_value: " + prizeValue);
                            webView.evaluateJavascript("spin(" + prizeValue + ")", null);

                            // Atualizar saldo no SessionManager
                            SessionManager sessionManager = SessionManager.getInstance(SpinWheelActivity.this);
                            sessionManager.updateUserBalance(newBalance);
                            Log.d(TAG, "Saldo atualizado para: " + newBalance);

                            // Mostrar mensagem de sucesso
                            showToast("Você ganhou " + prizeValue + " pontos!");

                            // Atualizar contador após animação (agora com spinsToday atualizado)
                            new Handler(Looper.getMainLooper()).postDelayed(() -> {
                                isSpinning = false;
                                updateSpinsCounter();
                            }, 5000); // 5 segundos para completar animação + bounce

                        } else {
                            isSpinning = false;
                            showToast(apiResponse.getError());
                        }

                    } catch (Exception e) {
                        isSpinning = false;
                        showToast("Erro ao processar giro");
                    }
                });
            }

            @Override
            public void onError(String error) {
                runOnUiThread(() -> {
                    isSpinning = false;
                    showToast("Erro HTTP 500: " + error);
                });
            }
        });
    }

    /**
     * Helper para log (Toast removido)
     */
    private void showToast(String message) {
        Log.d(TAG, message);
    }

    /**
     * Interface JavaScript para comunicação com WebView
     */
    public class WebAppInterface {

        @JavascriptInterface
        public void onSpinRequested() {
            runOnUiThread(() -> {
                if (spinsRemaining > 0) {
                    spinWheel();
                } else {
                    showToast("Você não tem mais giros disponíveis hoje!");
                }
            });
        }

        @JavascriptInterface
        public void onSpinComplete(int points) {
            // Não faz nada - a lógica agora é toda no servidor
            // O callback de sucesso da API já atualiza tudo
        }

        @JavascriptInterface
        public void onBackPressed() {
            runOnUiThread(() -> finish());
        }

        @JavascriptInterface
        public String getUserId() {
            SessionManager sessionManager = SessionManager.getInstance(SpinWheelActivity.this);
            String userId = sessionManager.getUserId();
            Log.d(TAG, "getUserId() chamado - ID: " + userId);
            return userId != null ? userId : "";
        }

        @JavascriptInterface
        public String getEmail() {
            SessionManager sessionManager = SessionManager.getInstance(SpinWheelActivity.this);
            String email = sessionManager.getEmail();
            Log.d(TAG, "getEmail() chamado - Email: " + email);
            return email != null ? email : "";
        }

        @JavascriptInterface
        public void openAdWebView() {
            Log.d(TAG, "openAdWebView() chamado - Abrindo bot do Telegram @young_money2_bot");
            runOnUiThread(() -> {
                try {
                    // Obter userId para passar como parâmetro start ao bot
                    SessionManager sessionManager = SessionManager.getInstance(SpinWheelActivity.this);
                    String userId = sessionManager.getUserId();
                    String startParam = (userId != null && !userId.isEmpty()) ? userId : "";

                    // Tentar abrir diretamente no app do Telegram
                    String telegramAppUrl = "tg://resolve?domain=young_money2_bot" +
                            (startParam.isEmpty() ? "" : "&start=" + startParam);
                    Intent telegramIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(telegramAppUrl));
                    telegramIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);

                    // Verificar se o Telegram está instalado
                    if (telegramIntent.resolveActivity(getPackageManager()) != null) {
                        Log.d(TAG, "✅ Telegram encontrado - Abrindo bot via app");
                        startActivity(telegramIntent);
                    } else {
                        // Fallback: abrir via URL web do Telegram
                        Log.d(TAG, "⚠️ Telegram não encontrado - Abrindo via navegador");
                        String webUrl = "https://t.me/young_money2_bot" +
                                (startParam.isEmpty() ? "" : "?start=" + startParam);
                        Intent browserIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(webUrl));
                        browserIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                        startActivity(browserIntent);
                    }
                } catch (Exception e) {
                    Log.e(TAG, "❌ Erro ao abrir Telegram: " + e.getMessage());
                    // Fallback final: abrir via navegador
                    try {
                        String webUrl = "https://t.me/young_money2_bot";
                        Intent browserIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(webUrl));
                        browserIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                        startActivity(browserIntent);
                    } catch (Exception ex) {
                        Log.e(TAG, "❌ Erro fatal ao abrir Telegram: " + ex.getMessage());
                    }
                }
            });
        }
    }

    // Método addPointsToUser removido - a API /api/v1/spin.php já adiciona os pontos automaticamente

    @Override
    protected void onResume() {
        super.onResume();
        Log.d(TAG, "🔄 onResume - Verificando status da tarefa novamente...");
        // Verificar novamente quando voltar para a tela
        checkTaskCompletionStatus();
    }

    @Override
    public void onBackPressed() {
        super.onBackPressed();
        finish();
    }
}
