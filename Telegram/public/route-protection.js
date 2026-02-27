// ============================================
// PROTEÇÃO DE ROTAS - GRANINHA BOT
// ============================================
// Este script protege o acesso ao graninha-bot.html
// Exige que o ID do usuário esteja presente na URL
// ============================================

(function() {
    'use strict';
    
    const API_BASE_URL = 'https://monetag-postback-server-production.up.railway.app/api/stats/user/';
    
    // Função para obter o ID da URL
    function getIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        // Tentar diferentes parâmetros possíveis
        return urlParams.get('id') || urlParams.get('ymid') || urlParams.get('user_id') || null;
    }
    
    // Função para redirecionar para a página de login
    function redirectToLogin(message) {
        console.log('[ROUTE PROTECTION] ' + message);
        alert(message);
        window.location.href = '/index.html';
    }
    
    // Função para verificar se o sistema foi resetado (impressões e cliques zerados)
    async function checkSystemReset(userId) {
        try {
            const response = await fetch(API_BASE_URL + userId);
            const data = await response.json();
            
            console.log('[ROUTE PROTECTION] Dados da API:', data);
            
            if (data.success) {
                const impressions = parseInt(data.total_impressions) || 0;
                const clicks = parseInt(data.total_clicks) || 0;
                
                // Se impressões E cliques forem zero, sistema foi resetado
                if (impressions === 0 && clicks === 0) {
                    return {
                        isReset: true,
                        message: 'Sistema resetado! Suas impressões e cliques estão zerados. Faça login novamente.'
                    };
                }
                
                return {
                    isReset: false,
                    impressions: impressions,
                    clicks: clicks
                };
            } else {
                return {
                    isReset: true,
                    message: 'ID inválido ou não encontrado. Faça login novamente.'
                };
            }
        } catch (error) {
            console.error('[ROUTE PROTECTION] Erro ao verificar API:', error);
            return {
                isReset: true,
                message: 'Erro ao verificar sua conta. Tente novamente.'
            };
        }
    }
    
    // Função principal de proteção
    async function protectRoute() {
        // 1. Verificar se tem ID na URL
        const userId = getIdFromUrl();
        
        if (!userId) {
            redirectToLogin('Acesso negado! Você precisa acessar esta página através do login com seu ID.');
            return false;
        }
        
        console.log('[ROUTE PROTECTION] ID encontrado na URL:', userId);
        
        // 2. Verificar se o sistema foi resetado
        const resetCheck = await checkSystemReset(userId);
        
        if (resetCheck.isReset) {
            redirectToLogin(resetCheck.message);
            return false;
        }
        
        console.log('[ROUTE PROTECTION] Acesso autorizado! Impressões:', resetCheck.impressions, 'Cliques:', resetCheck.clicks);
        
        // 3. Armazenar o ID para uso posterior
        window.GRANINHA_USER_ID = userId;
        sessionStorage.setItem('graninha_user_id', userId);
        
        return true;
    }
    
    // Executar proteção imediatamente
    // Esconder o conteúdo até verificar
    document.documentElement.style.visibility = 'hidden';
    
    protectRoute().then(function(authorized) {
        if (authorized) {
            // Mostrar conteúdo se autorizado
            document.documentElement.style.visibility = 'visible';
        }
        // Se não autorizado, já foi redirecionado
    });
    
})();
