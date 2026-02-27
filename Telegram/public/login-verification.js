// ============================================
// VERIFICAÇÃO DE LOGIN - GRANINHA BOT
// ============================================
// Este script verifica o ID do usuário após login
// e redireciona de volta se o sistema foi resetado
// ============================================

(function() {
    'use strict';
    
    const API_BASE_URL = 'https://monetag-postback-server-production.up.railway.app/api/stats/user/';
    const CHECK_INTERVAL = 20 * 60 * 1000; // Verificar a cada 20 minutos
    let verificationInterval = null;
    let currentUserId = null;
    
    // Função para obter o ID do usuário da tela
    function getUserIdFromScreen() {
        // Procurar por elementos que contenham "ID:" no texto
        const allElements = document.querySelectorAll('*');
        for (let el of allElements) {
            const text = el.textContent || el.innerText || '';
            // Procurar padrão "ID: XXXX" ou "ID:XXXX"
            const match = text.match(/ID:\s*(\d+)/);
            if (match && match[1]) {
                // Verificar se é o elemento direto (não um pai com muito texto)
                if (el.children.length === 0 || text.trim().startsWith('ID:')) {
                    return match[1];
                }
            }
        }
        // Fallback: tentar pegar da URL também
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('ymid') || urlParams.get('id') || urlParams.get('user_id') || null;
    }
    
    // Função para verificar se o sistema foi resetado
    async function checkSystemReset(userId) {
        try {
            const response = await fetch(API_BASE_URL + userId);
            const data = await response.json();
            
            console.log('[LOGIN VERIFICATION] Dados da API para ID ' + userId + ':', data);
            
            if (data.success) {
                const impressions = parseInt(data.total_impressions) || 0;
                const clicks = parseInt(data.total_clicks) || 0;
                
                // Verificar se usuário já completou missões antes
                const hasCompletedBefore = localStorage.getItem('graninha_completed_' + userId);
                
                // Se impressões E cliques forem zero E usuário já completou antes, sistema foi resetado
                if (impressions === 0 && clicks === 0 && hasCompletedBefore === 'true') {
                    // Limpar flag de conclusão para permitir novo ciclo
                    localStorage.removeItem('graninha_completed_' + userId);
                    return {
                        isReset: true,
                        message: 'Sistema resetado! Suas impressões e cliques estão zerados.'
                    };
                }
                
                // Se completou as missões (20/20 e 2/2), marcar como completado
                if (impressions >= 20 && clicks >= 2) {
                    localStorage.setItem('graninha_completed_' + userId, 'true');
                }
                
                return {
                    isReset: false,
                    impressions: impressions,
                    clicks: clicks,
                    userId: userId
                };
            } else {
                return {
                    isReset: false, // Não resetar se API falhar
                    error: 'API retornou erro'
                };
            }
        } catch (error) {
            console.error('[LOGIN VERIFICATION] Erro ao verificar API:', error);
            return {
                isReset: false, // Não resetar se houver erro de rede
                error: error.message
            };
        }
    }
    
    // Função para mostrar overlay de sistema resetado
    function showResetOverlay(message) {
        // Parar verificação
        if (verificationInterval) {
            clearInterval(verificationInterval);
            verificationInterval = null;
        }
        
        // Criar overlay
        const overlay = document.createElement('div');
        overlay.id = 'reset-overlay';
        overlay.style.cssText = `
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(0, 0, 0, 0.85) !important;
            z-index: 2147483647 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        `;
        
        const messageBox = document.createElement('div');
        messageBox.style.cssText = `
            background: #ffffff !important;
            padding: 40px !important;
            border-radius: 16px !important;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important;
            text-align: center !important;
            max-width: 90% !important;
            width: 400px !important;
        `;
        
        messageBox.innerHTML = `
            <div style="width: 70px; height: 70px; margin: 0 auto 20px; background: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <svg width="35" height="35" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <h2 style="margin: 0 0 15px 0; color: #1f2937; font-size: 24px; font-weight: 700; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">Sistema Resetado</h2>
            <p style="margin: 0 0 25px 0; color: #6b7280; font-size: 15px; line-height: 1.6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                ${message}
            </p>
            <div style="
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                color: white;
                padding: 20px 40px;
                border-radius: 12px;
                font-size: 32px;
                font-weight: 700;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            ">
                <div style="font-size: 14px; font-weight: 500; margin-bottom: 8px; opacity: 0.9;">Redirecionando em</div>
                <div id="countdown" style="font-size: 48px; font-weight: 800;">5</div>
            </div>
        `;
        
        overlay.appendChild(messageBox);
        document.body.appendChild(overlay);
        
        // Countdown de 5 segundos
        let timeLeft = 5;
        const countdownElement = document.getElementById('countdown');
        
        const countdownInterval = setInterval(function() {
            timeLeft--;
            if (countdownElement) {
                countdownElement.textContent = timeLeft;
            }
            
            if (timeLeft <= 0) {
                clearInterval(countdownInterval);
                // Limpar dados de sessão
                sessionStorage.clear();
                localStorage.removeItem('graninha_user_id');
                // Recarregar a página para fazer novo login
                window.location.reload();
            }
        }, 1000);
    }
    
    // Função para iniciar verificação periódica
    function startPeriodicVerification(userId) {
        currentUserId = userId;
        
        console.log('[LOGIN VERIFICATION] Iniciando verificação periódica para ID:', userId);
        console.log('[LOGIN VERIFICATION] Intervalo de verificação:', CHECK_INTERVAL / 1000 / 60, 'minutos');
        
        // Verificação periódica
        verificationInterval = setInterval(async function() {
            console.log('[LOGIN VERIFICATION] Verificação periódica...');
            
            const result = await checkSystemReset(currentUserId);
            
            if (result.isReset) {
                showResetOverlay(result.message);
            } else {
                console.log('[LOGIN VERIFICATION] Sistema OK - Impressões:', result.impressions, 'Cliques:', result.clicks);
            }
        }, CHECK_INTERVAL);
    }
    
    // Observar quando o ID aparecer na tela
    function waitForUserId() {
        const observer = new MutationObserver(function() {
            const userId = getUserIdFromScreen();
            
            if (userId && userId !== currentUserId) {
                console.log('[LOGIN VERIFICATION] ID detectado na tela:', userId);
                
                // Verificar imediatamente
                checkSystemReset(userId).then(function(result) {
                    if (result.isReset) {
                        showResetOverlay(result.message);
                    } else {
                        console.log('[LOGIN VERIFICATION] Verificação inicial OK');
                        // Iniciar verificação periódica
                        startPeriodicVerification(userId);
                        
                        // Salvar ID para uso em outras páginas
                        sessionStorage.setItem('graninha_user_id', userId);
                    }
                });
            }
        });
        
        observer.observe(document.body || document.documentElement, {
            childList: true,
            subtree: true,
            characterData: true
        });
        
        // Também verificar imediatamente se já existe
        const existingId = getUserIdFromScreen();
        if (existingId) {
            console.log('[LOGIN VERIFICATION] ID já existe na tela:', existingId);
            checkSystemReset(existingId).then(function(result) {
                if (result.isReset) {
                    showResetOverlay(result.message);
                } else {
                    startPeriodicVerification(existingId);
                    sessionStorage.setItem('graninha_user_id', existingId);
                }
            });
        }
    }
    
    // Iniciar quando DOM estiver pronto
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        waitForUserId();
    } else {
        document.addEventListener('DOMContentLoaded', waitForUserId);
    }
    
    console.log('[LOGIN VERIFICATION] Sistema de verificação de reset iniciado');
    
})();
