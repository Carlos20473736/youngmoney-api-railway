/**
 * Sistema de Segurança de Sessão Única
 * Impede que o usuário abra múltiplas abas com a mesma URL
 */

class SessionSecurity {
    constructor(prefix = 'default') {
        this.tabId = this.generateTabId();
        this.prefix = prefix;
        this.sessionKey = `${prefix}_active_session`;
        this.heartbeatKey = `${prefix}_session_heartbeat`;
        this.heartbeatInterval = null;
        this.checkInterval = null;
        this.isActive = false;
        
        console.log('[SESSION SECURITY] Tab ID:', this.tabId);
    }

    /**
     * Gera um ID único para esta aba
     */
    generateTabId() {
        return `tab_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }

    /**
     * Inicia o sistema de segurança
     */
    async initialize() {
        console.log('[SESSION SECURITY] Inicializando sistema de segurança...');
        
        // Verificar se já existe uma sessão ativa
        const activeSession = this.getActiveSession();
        
        if (activeSession && activeSession.tabId !== this.tabId) {
            // Verificar se a sessão ativa ainda está viva
            const isAlive = this.isSessionAlive(activeSession);
            
            if (isAlive) {
                console.log('[SESSION SECURITY] ❌ Sessão já ativa em outra aba!');
                this.blockAccess('Você já tem esta página aberta em outra aba. Por favor, use apenas uma aba por vez.');
                return false;
            } else {
                console.log('[SESSION SECURITY] Sessão anterior expirou, assumindo controle...');
            }
        }
        
        // Registrar esta aba como sessão ativa
        this.registerSession();
        
        // Iniciar heartbeat (batimento cardíaco) para manter sessão ativa
        this.startHeartbeat();
        
        // Monitorar tentativas de abertura em outras abas
        this.monitorOtherTabs();
        
        // Limpar sessão ao fechar a aba
        this.setupCleanup();
        
        this.isActive = true;
        console.log('[SESSION SECURITY] ✅ Sessão iniciada com sucesso');
        return true;
    }

    /**
     * Registra esta aba como sessão ativa
     */
    registerSession() {
        const session = {
            tabId: this.tabId,
            timestamp: Date.now(),
            userId: localStorage.getItem('user_id'),
            userEmail: localStorage.getItem('user_email')
        };
        
        localStorage.setItem(this.sessionKey, JSON.stringify(session));
        this.updateHeartbeat();
        
        console.log('[SESSION SECURITY] Sessão registrada:', session);
    }

    /**
     * Obtém a sessão ativa atual
     */
    getActiveSession() {
        try {
            const sessionData = localStorage.getItem(this.sessionKey);
            return sessionData ? JSON.parse(sessionData) : null;
        } catch (error) {
            console.error('[SESSION SECURITY] Erro ao obter sessão:', error);
            return null;
        }
    }

    /**
     * Verifica se uma sessão ainda está viva
     */
    isSessionAlive(session) {
        if (!session) return false;
        
        try {
            const heartbeatData = localStorage.getItem(this.heartbeatKey);
            if (!heartbeatData) return false;
            
            const heartbeat = JSON.parse(heartbeatData);
            
            // Se o heartbeat é desta aba, está viva
            if (heartbeat.tabId === this.tabId) return true;
            
            // Se o último heartbeat foi há mais de 3 segundos, considerar morta
            const timeSinceLastBeat = Date.now() - heartbeat.timestamp;
            return timeSinceLastBeat < 3000;
        } catch (error) {
            console.error('[SESSION SECURITY] Erro ao verificar heartbeat:', error);
            return false;
        }
    }

    /**
     * Atualiza o heartbeat da sessão
     */
    updateHeartbeat() {
        const heartbeat = {
            tabId: this.tabId,
            timestamp: Date.now()
        };
        
        localStorage.setItem(this.heartbeatKey, JSON.stringify(heartbeat));
    }

    /**
     * Inicia o heartbeat periódico
     */
    startHeartbeat() {
        // Atualizar heartbeat a cada 1 segundo
        this.heartbeatInterval = setInterval(() => {
            if (this.isActive) {
                this.updateHeartbeat();
            }
        }, 1000);
        
        console.log('[SESSION SECURITY] Heartbeat iniciado');
    }

    /**
     * Monitora tentativas de abertura em outras abas
     */
    monitorOtherTabs() {
        // Verificar a cada 500ms se outra aba assumiu controle
        this.checkInterval = setInterval(() => {
            const activeSession = this.getActiveSession();
            
            if (activeSession && activeSession.tabId !== this.tabId) {
                // Outra aba assumiu controle
                console.log('[SESSION SECURITY] ⚠️ Outra aba assumiu controle!');
                this.blockAccess('Esta sessão foi movida para outra aba.');
            }
        }, 500);
        
        // Escutar eventos de storage (quando outra aba modifica localStorage)
        window.addEventListener('storage', (e) => {
            if (e.key === this.sessionKey) {
                const newSession = e.newValue ? JSON.parse(e.newValue) : null;
                
                if (newSession && newSession.tabId !== this.tabId) {
                    console.log('[SESSION SECURITY] ⚠️ Nova sessão detectada em outra aba!');
                    this.blockAccess('Você abriu esta página em outra aba. Por favor, use apenas uma aba por vez.');
                }
            }
        });
        
        console.log('[SESSION SECURITY] Monitoramento de outras abas ativado');
    }

    /**
     * Bloqueia o acesso à página
     */
    blockAccess(message) {
        this.isActive = false;
        
        // Parar heartbeat e monitoramento
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
        
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
        
        // Exibir mensagem de bloqueio
        document.body.innerHTML = `
            <div style="
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100vh;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                text-align: center;
                padding: 20px;
            ">
                <div style="
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                    border-radius: 20px;
                    padding: 40px;
                    max-width: 500px;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                ">
                    <div style="font-size: 64px; margin-bottom: 20px;">🔒</div>
                    <h1 style="margin: 0 0 20px 0; font-size: 28px;">Acesso Bloqueado</h1>
                    <p style="margin: 0 0 30px 0; font-size: 18px; opacity: 0.9;">
                        ${message}
                    </p>
                    <button onclick="window.location.href='/'" style="
                        background: white;
                        color: #667eea;
                        border: none;
                        padding: 15px 40px;
                        border-radius: 10px;
                        font-size: 16px;
                        font-weight: bold;
                        cursor: pointer;
                        transition: transform 0.2s;
                    " onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        Voltar ao Início
                    </button>
                </div>
            </div>
        `;
        
        console.log('[SESSION SECURITY] 🔒 Acesso bloqueado:', message);
    }

    /**
     * Configura limpeza ao fechar a aba
     */
    setupCleanup() {
        // Limpar sessão ao fechar a aba
        window.addEventListener('beforeunload', () => {
            if (this.isActive) {
                const activeSession = this.getActiveSession();
                
                // Só limpar se esta aba é a sessão ativa
                if (activeSession && activeSession.tabId === this.tabId) {
                    localStorage.removeItem(this.sessionKey);
                    localStorage.removeItem(this.heartbeatKey);
                    console.log('[SESSION SECURITY] Sessão limpa ao fechar aba');
                }
            }
        });
        
        // Limpar ao perder visibilidade por muito tempo
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                console.log('[SESSION SECURITY] Página oculta');
            } else {
                console.log('[SESSION SECURITY] Página visível');
                
                // Verificar se ainda somos a sessão ativa
                const activeSession = this.getActiveSession();
                if (activeSession && activeSession.tabId !== this.tabId) {
                    this.blockAccess('Esta sessão expirou. Por favor, recarregue a página.');
                }
            }
        });
    }

    /**
     * Verifica se a sessão está ativa
     */
    isSessionActive() {
        return this.isActive;
    }

    /**
     * Destrói a sessão
     */
    destroy() {
        this.isActive = false;
        
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
        }
        
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
        
        const activeSession = this.getActiveSession();
        if (activeSession && activeSession.tabId === this.tabId) {
            localStorage.removeItem(this.sessionKey);
            localStorage.removeItem(this.heartbeatKey);
        }
        
        console.log('[SESSION SECURITY] Sessão destruída');
    }
}

// Exportar para uso global
window.SessionSecurity = SessionSecurity;
