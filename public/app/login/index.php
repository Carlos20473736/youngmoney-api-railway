<?php
/**
 * App Link Redirect - Redireciona para o deep link do app
 * 
 * URL: https://youngmoney-api-railway-production.up.railway.app/app/login?token=XXX
 * Redireciona para: youngmoney://login?token=XXX
 */

$token = $_GET['token'] ?? '';

if (empty($token)) {
    // Se não houver token, redirecionar para página de login web
    header('Location: /public/web-login/');
    exit;
}

// Construir deep link
$deepLink = "youngmoney://login?token=" . urlencode($token);

// HTML com fallback para caso o app não esteja instalado
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abrindo Young Money...</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-align: center;
            padding: 20px;
        }
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 215, 0, 0.2);
            border-top-color: #ffd700;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        h1 { font-size: 24px; margin-bottom: 10px; }
        p { color: rgba(255, 255, 255, 0.7); margin-bottom: 20px; }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #ffd700 0%, #ffaa00 100%);
            color: #0a0e27;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            margin: 10px;
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .fallback { display: none; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="spinner"></div>
    <h1>Abrindo Young Money...</h1>
    <p>Aguarde enquanto abrimos o aplicativo</p>
    
    <div class="fallback" id="fallback">
        <p>O aplicativo não abriu automaticamente?</p>
        <a href="<?php echo htmlspecialchars($deepLink); ?>" class="btn">Abrir App</a>
        <br>
        <a href="/public/web-login/" class="btn btn-secondary">Fazer Login na Web</a>
    </div>
    
    <script>
        // Tentar abrir o app
        window.location.href = "<?php echo htmlspecialchars($deepLink); ?>";
        
        // Mostrar fallback após 3 segundos
        setTimeout(function() {
            document.getElementById('fallback').style.display = 'block';
        }, 3000);
    </script>
</body>
</html>
