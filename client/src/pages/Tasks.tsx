import { useEffect, useState, useCallback, useRef } from 'react';
import { useLocation } from 'wouter';
import { 
  Eye, 
  MousePointer2, 
  RefreshCw, 
  LogOut, 
  Play,
  CheckCircle2,
  Clock,
  Loader2,
  ArrowLeft
} from 'lucide-react';
import StarField from '@/components/StarField';
import { toast } from 'sonner';

/*
 * Design: Glassmorphism Cosmos
 * Dashboard de tarefas - mostra progresso de impressões e cliques
 * Integra com API Monetag para estatísticas em tempo real
 * Inclui sistema de overlay de 15 segundos para anúncios
 */

const YMID_STORAGE_KEY = 'youngmoney_ymid';
const EMAIL_STORAGE_KEY = 'youngmoney_email';
const API_BASE_URL = 'https://monetag-postback-server-production.up.railway.app/api/stats/user';
const POSTBACK_SERVER = 'https://monetag-postback-server-production.up.railway.app';
const POSTBACK_URL = `${POSTBACK_SERVER}/api/postback`;
const YOUNGMONEY_API = 'https://youngmoney-api-railway-production.up.railway.app';

// Configuração do Monetag SDK
const ZONE_ID = '10325249';
const SDK_FUNC = 'show_10325249';
const SCRIPT_SRC = 'https://libtl.com/sdk.js';

// Requisitos para completar a tarefa
const REQUIRED_IMPRESSIONS = 5;
const REQUIRED_CLICKS = 1;

// Duração do overlay em segundos
const OVERLAY_DURATION = 15;

interface UserStats {
  success: boolean;
  ymid: string;
  total_impressions: number;
  total_clicks: number;
  total_revenue: string;
  session_expired: boolean;
  time_remaining: number;
}

// Declaração global para o SDK do Monetag
declare global {
  interface Window {
    [key: string]: any;
  }
}

export default function Tasks() {
  const [, setLocation] = useLocation();
  const [ymid, setYmid] = useState<string | null>(null);
  const [userEmail, setUserEmail] = useState<string>('unknown@youngmoney.com');
  const [stats, setStats] = useState<UserStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [sdkLoaded, setSdkLoaded] = useState(false);
  
  // Estado do overlay
  const [showOverlay, setShowOverlay] = useState(false);
  const [overlayCountdown, setOverlayCountdown] = useState(OVERLAY_DURATION);
  const overlayIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const overlayCreatedRef = useRef(false);

  // Refs para armazenar ymid e email para uso nos interceptadores
  const ymidRef = useRef<string | null>(null);
  const emailRef = useRef<string>('unknown@youngmoney.com');

  // Carregar YMID e EMAIL do localStorage
  useEffect(() => {
    const storedYMID = localStorage.getItem(YMID_STORAGE_KEY);
    if (!storedYMID) {
      setLocation('/');
      return;
    }
    setYmid(storedYMID);
    ymidRef.current = storedYMID;
    
    // Buscar email do localStorage primeiro, depois tentar API
    const storedEmail = localStorage.getItem(EMAIL_STORAGE_KEY);
    if (storedEmail) {
      setUserEmail(storedEmail);
      emailRef.current = storedEmail;
      console.log('[EMAIL] Email obtido do localStorage:', storedEmail);
    } else {
      // Fallback: buscar email do profile
      fetchUserProfile();
    }
  }, [setLocation]);

  // ========================================
  // FUNÇÃO PARA CRIAR O OVERLAY FLUTUANTE (VANILLA JS - IGUAL AO HTML)
  // ========================================
  const createFloatingOverlayVanilla = useCallback(() => {
    if (overlayCreatedRef.current) {
      console.log('[OVERLAY] Overlay já existe, ignorando...');
      return;
    }

    overlayCreatedRef.current = true;
    console.log('[OVERLAY] Anúncio detectado! Criando overlay flutuante de ' + OVERLAY_DURATION + ' segundos...');

    // Criar overlay TRANSPARENTE que BLOQUEIA CLIQUES e fica acima de TUDO (incluindo anúncios Monetag)
    const overlay = document.createElement('div');
    overlay.id = 'monetag-block-overlay';
    overlay.style.cssText = `
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      background: rgba(0, 0, 0, 0.1) !important;
      z-index: 2147483647 !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      animation: fadeInOverlay 0.3s ease !important;
      pointer-events: auto !important;
    `;

    // Bloquear TODOS os eventos no overlay para impedir cliques nos anúncios
    ['click', 'mousedown', 'mouseup', 'touchstart', 'touchend', 'touchmove', 'contextmenu', 'pointerdown', 'pointerup'].forEach(function(eventType) {
      overlay.addEventListener(eventType, function(e) {
        e.stopPropagation();
        e.preventDefault();
        console.log('[OVERLAY] Evento ' + eventType + ' bloqueado - anúncio protegido');
        return false;
      }, true);
    });

    // Mensagem do overlay com contador
    const message = document.createElement('div');
    message.style.cssText = `
      background: linear-gradient(135deg, rgba(26, 26, 46, 0.6) 0%, rgba(22, 33, 62, 0.6) 100%) !important;
      padding: 35px 40px !important;
      border-radius: 20px !important;
      box-shadow: 0 10px 40px rgba(0,0,0,0.3), 0 0 20px rgba(139, 92, 246, 0.2) !important;
      text-align: center !important;
      max-width: 90% !important;
      width: 380px !important;
      animation: scaleInOverlay 0.4s ease !important;
      pointer-events: auto !important;
      border: 2px solid rgba(139, 92, 246, 0.3) !important;
      opacity: 0.85 !important;
    `;

    message.innerHTML = `
      <div style="width: 40px; height: 40px; margin: 0 auto 15px; position: relative;">
        <svg viewBox="0 0 100 100" style="width: 100%; height: 100%; transform: rotate(-90deg);">
          <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(139, 92, 246, 0.2)" stroke-width="8"></circle>
          <circle id="overlay-progress-circle" cx="50" cy="50" r="45" fill="none" stroke="#00ddff" stroke-width="8" stroke-linecap="round" stroke-dasharray="283" stroke-dashoffset="0" style="transition: stroke-dashoffset 1s linear;"></circle>
        </svg>
        <span id="overlay-timer" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-family: 'Orbitron', monospace; font-size: 14px; font-weight: 700; color: #00ddff;">${OVERLAY_DURATION}</span>
      </div>
      <p style="margin: 0; color: #a0aec0; font-size: 14px; font-family: 'Inter', sans-serif;">Realizando tarefa automaticamente...</p>
    `;

    overlay.appendChild(message);

    // Adicionar ao HTML (não ao body) para ficar acima de TUDO
    document.documentElement.appendChild(overlay);

    // Adicionar animações CSS
    if (!document.getElementById('overlay-animations-style')) {
      const style = document.createElement('style');
      style.id = 'overlay-animations-style';
      style.textContent = `
        @keyframes fadeInOverlay {
          from { opacity: 0; }
          to { opacity: 1; }
        }
        @keyframes scaleInOverlay {
          from { opacity: 0; transform: scale(0.9) translateY(20px); }
          to { opacity: 1; transform: scale(1) translateY(0); }
        }
        @keyframes fadeOutOverlay {
          from { opacity: 1; }
          to { opacity: 0; }
        }
        #monetag-block-overlay {
          z-index: 2147483647 !important;
          position: fixed !important;
        }
      `;
      document.head.appendChild(style);
    }

    // FUNÇÃO PARA MANTER OVERLAY NO TOPO (acima dos iframes do Monetag)
    const keepOverlayOnTop = () => {
      const overlayEl = document.getElementById('monetag-block-overlay');
      if (!overlayEl) return;

      // Garantir que está no final do documento
      if (overlayEl.parentNode !== document.documentElement || overlayEl !== document.documentElement.lastElementChild) {
        document.documentElement.appendChild(overlayEl);
      }

      // Forçar z-index máximo
      overlayEl.style.zIndex = '2147483647';
    };

    // Executar a cada 100ms para garantir que fica no topo
    const topInterval = setInterval(keepOverlayOnTop, 100);

    // Iniciar contador regressivo
    const circumference = 283; // 2 * PI * 45
    let countdown = OVERLAY_DURATION;

    const countdownInterval = setInterval(() => {
      countdown--;

      // Atualizar número do timer
      const timerEl = document.getElementById('overlay-timer');
      const progressCircle = document.getElementById('overlay-progress-circle');

      if (timerEl) timerEl.textContent = String(countdown);

      // Atualizar progresso do círculo
      if (progressCircle) {
        const progress = (OVERLAY_DURATION - countdown) / OVERLAY_DURATION;
        progressCircle.style.strokeDashoffset = String(circumference * (1 - progress));
      }

      console.log('[OVERLAY] Contador: ' + countdown + ' segundos restantes');

      if (countdown <= 0) {
        clearInterval(countdownInterval);
        clearInterval(topInterval);

        console.log('[OVERLAY] Contador finalizado! Reiniciando página...');

        // REINICIAR A PÁGINA AUTOMATICAMENTE
        window.location.reload();
      }
    }, 1000);

    console.log('[OVERLAY] Overlay criado com sucesso!');
  }, []);

  // Carregar SDK do Monetag e configurar interceptadores
  useEffect(() => {
    if (!ymid) return;
    
    const loadMonetagSDK = () => {
      // Verificar se já existe
      if (document.querySelector(`script[src="${SCRIPT_SRC}"]`)) {
        console.log('[SDK] Monetag SDK já carregado');
        setSdkLoaded(true);
        return;
      }

      const script = document.createElement('script');
      script.src = SCRIPT_SRC;
      script.setAttribute('data-zone', ZONE_ID);
      script.setAttribute('data-sdk', SDK_FUNC);
      script.async = true;
      
      script.onload = () => {
        console.log('[SDK] Monetag SDK carregado com sucesso');
        setSdkLoaded(true);
      };
      
      script.onerror = () => {
        console.error('[SDK] Erro ao carregar Monetag SDK');
        toast.error('Erro ao carregar sistema de anúncios');
      };
      
      document.head.appendChild(script);
      console.log('[SDK] Carregando Monetag SDK...');
    };

    // ========================================
    // EXPOR FUNÇÃO DE OVERLAY GLOBALMENTE
    // ========================================
    window.MontagOverlay = {
      show: createFloatingOverlayVanilla,
      hide: () => {
        const overlay = document.getElementById('monetag-block-overlay');
        if (overlay) {
          overlay.remove();
          overlayCreatedRef.current = false;
        }
      }
    };

    // ========================================
    // FUNÇÃO PARA ENVIAR POSTBACK PARA NOVO SERVIDOR
    // ========================================
    const sendPostbackToNewServer = (eventType: string) => {
      console.log('[POSTBACK] Enviando ' + eventType + ' para novo servidor');

      // 🎯 SE FOR CLIQUE, MOSTRAR OVERLAY DE 15 SEGUNDOS IMEDIATAMENTE!
      if (eventType === 'click') {
        console.log('[POSTBACK] 🎯 CLIQUE DETECTADO! Mostrando overlay de 15 segundos...');
        if (!overlayCreatedRef.current) {
          createFloatingOverlayVanilla();
        }
      }

      const params = new URLSearchParams({
        event_type: eventType,
        zone_id: ZONE_ID,
        ymid: ymidRef.current || 'unknown',
        user_email: emailRef.current,
        estimated_price: eventType === 'click' ? '0.0045' : '0.0023'
      });

      const url = `${POSTBACK_URL}?${params.toString()}`;
      console.log('[POSTBACK] URL completa:', url);

      fetch(url, { method: 'GET', mode: 'cors' })
        .then(response => response.json())
        .then(data => {
          console.log('[POSTBACK] ' + eventType + ' enviado com sucesso:', data);
        })
        .catch(err => {
          console.error('[POSTBACK] Erro ao enviar ' + eventType + ':', err);
        });
    };

    // ========================================
    // 1. INTERCEPTAR fetch()
    // ========================================
    const originalFetch = window.fetch;
    window.fetch = function(...args: Parameters<typeof fetch>) {
      const url = args[0];
      if (typeof url === 'string' && url.includes('youngmoney-api-railway')) {
        // Verificar se é postback do Monetag (com macros literais)
        if (url.includes('%7Bymid%7D') || url.includes('{ymid}')) {
          console.log('[BLOQUEIO FETCH] 🚫 Postback do Monetag bloqueado:', url);

          // Detectar tipo de evento (impression ou click)
          const eventType = url.includes('event_type=click') || url.includes('event_type%3Dclick') ? 'click' : 'impression';
          console.log('[BLOQUEIO FETCH] Tipo detectado:', eventType);

          // Enviar para novo servidor
          console.log(`[BLOQUEIO FETCH] ✅ ${eventType} detectado! Enviando para novo servidor...`);
          sendPostbackToNewServer(eventType);

          return Promise.resolve(new Response('', { status: 200 }));
        }
      }
      return originalFetch.apply(window, args);
    };

    // ========================================
    // 2. INTERCEPTAR XMLHttpRequest
    // ========================================
    const originalXHROpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function(method: string, url: string | URL, ...rest: any[]) {
      const urlStr = typeof url === 'string' ? url : url.toString();
      if (urlStr.includes('youngmoney-api-railway')) {
        // Verificar se é postback do Monetag (com macros literais)
        if (urlStr.includes('%7Bymid%7D') || urlStr.includes('{ymid}')) {
          console.log('[BLOQUEIO XHR] 🚫 Postback do Monetag bloqueado:', urlStr);

          // Detectar tipo de evento (impression ou click)
          const eventType = urlStr.includes('event_type=click') || urlStr.includes('event_type%3Dclick') ? 'click' : 'impression';
          console.log('[BLOQUEIO XHR] Tipo detectado:', eventType);

          // Enviar para novo servidor
          console.log(`[BLOQUEIO XHR] ✅ ${eventType} detectado! Enviando para novo servidor...`);
          sendPostbackToNewServer(eventType);

          // Redirecionar para URL vazia
          return originalXHROpen.call(this, method, 'about:blank', ...rest);
        }
      }
      return originalXHROpen.call(this, method, url, ...rest);
    };

    // ========================================
    // 3. INTERCEPTAR Image (pixel tracking)
    // ========================================
    const originalImage = window.Image;
    window.Image = function() {
      const img = new originalImage();
      const originalSrcDescriptor = Object.getOwnPropertyDescriptor(HTMLImageElement.prototype, 'src');
      if (originalSrcDescriptor && originalSrcDescriptor.set) {
        const originalSrcSetter = originalSrcDescriptor.set;
        Object.defineProperty(img, 'src', {
          set: function(value: string) {
            if (typeof value === 'string' && value.includes('youngmoney-api-railway')) {
              // Verificar se é postback do Monetag (com macros literais)
              if (value.includes('%7Bymid%7D') || value.includes('{ymid}')) {
                console.log('[BLOQUEIO IMG] 🚫 Postback do Monetag bloqueado:', value);

                // Detectar tipo de evento (impression ou click)
                const eventType = value.includes('event_type=click') || value.includes('event_type%3Dclick') ? 'click' : 'impression';
                console.log('[BLOQUEIO IMG] Tipo detectado:', eventType);

                // Enviar para novo servidor
                console.log(`[BLOQUEIO IMG] ✅ ${eventType} detectado! Enviando para novo servidor...`);
                sendPostbackToNewServer(eventType);

                return; // Não define o src
              }
            }
            originalSrcSetter.call(this, value);
          },
          get: function() {
            return this.getAttribute('src');
          }
        });
      }
      return img;
    } as any;

    console.log('[INTERCEPTOR] Interceptadores de postback configurados (fetch, XHR, Image)');

    loadMonetagSDK();
  }, [ymid, createFloatingOverlayVanilla]);

  // Buscar email do usuário (fallback se não estiver no localStorage)
  const fetchUserProfile = async () => {
    // Verificar localStorage primeiro
    const storedEmail = localStorage.getItem(EMAIL_STORAGE_KEY);
    if (storedEmail) {
      setUserEmail(storedEmail);
      emailRef.current = storedEmail;
      console.log('[EMAIL] Email já existe no localStorage:', storedEmail);
      return;
    }
    
    try {
      const response = await fetch(`${YOUNGMONEY_API}/profile`, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'include'
      });
      
      if (response.ok) {
        const data = await response.json();
        const email = data.email || data.user?.email || data.data?.email;
        if (email) {
          setUserEmail(email);
          emailRef.current = email;
          localStorage.setItem(EMAIL_STORAGE_KEY, email);
          console.log('[PROFILE] Email obtido da API:', email);
        }
      }
    } catch (err) {
      console.error('[PROFILE] Erro ao buscar email:', err);
    }
  };

  // Clique no botão de anúncio
  const handleAdClick = async () => {
    if (isProcessing) return;

    setIsProcessing(true);
    console.log('[AD] Botão de anúncio clicado');

    // Gerar click_id único
    const clickId = 'click_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    console.log('[AD] Click ID:', clickId);

    // Verificar se SDK está pronto
    if (typeof window[SDK_FUNC] !== 'function') {
      console.warn('[AD] SDK não está pronto');
      setIsProcessing(false);
      toast.warning('Aguarde o carregamento do sistema de anúncios...');
      return;
    }

    console.log('[AD] Chamando SDK com ymid:', ymid, '| email:', userEmail);

    try {
      // Mostrar anúncio com ymid e requestVar
      await window[SDK_FUNC]({
        ymid: ymid,
        requestVar: userEmail
      });

      console.log('[AD] Anúncio exibido pelo Monetag');

      // Aguardar e depois parar o processamento
      setTimeout(() => {
        setIsProcessing(false);
        fetchStats();
      }, 2000);
    } catch (err) {
      console.error('[AD] Erro:', err);
      setIsProcessing(false);
      toast.error('Erro ao exibir anúncio');
    }
  };

  // Buscar estatísticas
  const fetchStats = useCallback(async (showToast = false) => {
    if (!ymid) return;

    if (showToast) {
      setIsRefreshing(true);
    }

    try {
      const response = await fetch(`${API_BASE_URL}/${ymid}`, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
      });

      if (!response.ok) {
        throw new Error(`Erro HTTP: ${response.status}`);
      }

      const data: UserStats = await response.json();
      
      if (data.success) {
        setStats(data);
        setError(null);
        if (showToast) {
          toast.success('Estatísticas atualizadas!');
        }
      } else {
        setError('Falha ao carregar estatísticas');
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erro desconhecido');
      if (showToast) {
        toast.error('Erro ao atualizar estatísticas');
      }
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, [ymid]);

  // Buscar stats quando YMID carregar
  useEffect(() => {
    if (ymid) {
      fetchStats();
      // Auto-refresh a cada 5 segundos
      const interval = setInterval(() => fetchStats(), 5000);
      return () => clearInterval(interval);
    }
  }, [ymid, fetchStats]);

  // Logout
  const handleLogout = () => {
    localStorage.removeItem(YMID_STORAGE_KEY);
    toast.success('Desconectado com sucesso');
    setLocation('/');
  };

  // Calcular progresso
  const impressionsProgress = stats 
    ? Math.min((stats.total_impressions / REQUIRED_IMPRESSIONS) * 100, 100) 
    : 0;
  const clicksProgress = stats 
    ? Math.min((stats.total_clicks / REQUIRED_CLICKS) * 100, 100) 
    : 0;
  const isTaskComplete = stats 
    ? stats.total_impressions >= REQUIRED_IMPRESSIONS && stats.total_clicks >= REQUIRED_CLICKS 
    : false;

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <StarField />
        <div className="glass-card p-8 text-center relative z-10">
          <Loader2 className="w-12 h-12 mx-auto mb-4 text-primary animate-spin" />
          <p className="text-muted-foreground">Carregando suas tarefas...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex flex-col relative overflow-hidden">
      <StarField />
      
      {/* Header */}
      <header className="relative z-10 p-4">
        <div className="glass-card px-4 py-3 flex items-center justify-between">
          <button
            onClick={() => setLocation('/')}
            className="p-2 rounded-lg hover:bg-white/5 transition-colors"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          
          <h1 
            className="text-lg font-semibold"
            style={{ fontFamily: 'var(--font-display)' }}
          >
            Minhas Tarefas
          </h1>
          
          <button
            onClick={handleLogout}
            className="p-2 rounded-lg hover:bg-white/5 transition-colors text-red-400"
            title="Sair"
          >
            <LogOut className="w-5 h-5" />
          </button>
        </div>
      </header>

      {/* Main Content */}
      <main className="flex-1 p-4 relative z-10">
        {error ? (
          <div className="glass-card p-6 text-center">
            <p className="text-red-400 mb-4">{error}</p>
            <button
              onClick={() => fetchStats(true)}
              className="glow-button"
            >
              Tentar novamente
            </button>
          </div>
        ) : (
          <div className="space-y-4 max-w-md mx-auto">
            {/* Task Card */}
            <div className="glass-card p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 
                  className="text-lg font-semibold"
                  style={{ fontFamily: 'var(--font-display)' }}
                >
                  Complete a tarefa abaixo para desbloquear a roleta e ganhar prêmios!
                </h2>
                <button
                  onClick={() => fetchStats(true)}
                  disabled={isRefreshing}
                  className="p-2 rounded-lg hover:bg-white/5 transition-colors disabled:opacity-50"
                >
                  <RefreshCw className={`w-5 h-5 ${isRefreshing ? 'animate-spin' : ''}`} />
                </button>
              </div>

              {/* Requirements */}
              <div className="space-y-2 mb-6 text-sm text-muted-foreground">
                <p>• Assista <span className="text-foreground font-semibold">{REQUIRED_IMPRESSIONS} anúncios</span> (impressões)</p>
                <p>• Clique em <span className="text-foreground font-semibold">{REQUIRED_CLICKS} anúncio</span></p>
              </div>

              {/* Progress Bars */}
              <div className="space-y-4">
                {/* Impressions */}
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      <Eye className="w-4 h-4 text-primary" />
                      <span className="text-sm">Anúncios Assistidos</span>
                    </div>
                    <span 
                      className="text-sm font-mono font-semibold"
                      style={{ fontFamily: 'var(--font-mono)' }}
                    >
                      {stats?.total_impressions || 0}/{REQUIRED_IMPRESSIONS}
                    </span>
                  </div>
                  <div className="progress-glow h-3">
                    <div 
                      className="progress-glow-fill"
                      style={{ width: `${impressionsProgress}%` }}
                    />
                  </div>
                </div>

                {/* Clicks */}
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      <MousePointer2 className="w-4 h-4 text-cyan-400" />
                      <span className="text-sm">Cliques Realizados</span>
                    </div>
                    <span 
                      className="text-sm font-mono font-semibold"
                      style={{ fontFamily: 'var(--font-mono)' }}
                    >
                      {stats?.total_clicks || 0}/{REQUIRED_CLICKS}
                    </span>
                  </div>
                  <div className="progress-glow h-3">
                    <div 
                      className="progress-glow-fill"
                      style={{ 
                        width: `${clicksProgress}%`,
                        background: 'linear-gradient(90deg, oklch(0.789 0.154 211.53) 0%, oklch(0.696 0.17 162.48) 100%)'
                      }}
                    />
                  </div>
                </div>
              </div>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-2 gap-4">
              <div className="stat-card">
                <div className="stat-value">{stats?.total_impressions || 0}</div>
                <div className="stat-label">Impressões</div>
              </div>
              <div className="stat-card">
                <div className="stat-value" style={{ 
                  background: 'linear-gradient(135deg, oklch(0.789 0.154 211.53) 0%, oklch(0.696 0.17 162.48) 100%)',
                  WebkitBackgroundClip: 'text',
                  WebkitTextFillColor: 'transparent',
                  backgroundClip: 'text'
                }}>
                  {stats?.total_clicks || 0}
                </div>
                <div className="stat-label">Cliques</div>
              </div>
            </div>

            {/* Action Button */}
            <div className="pt-4">
              {isTaskComplete ? (
                <button className="glow-button w-full flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-cyan-500">
                  <CheckCircle2 className="w-5 h-5" />
                  <span style={{ fontFamily: 'var(--font-display)' }}>
                    TAREFA CONCLUÍDA!
                  </span>
                </button>
              ) : (
                <button 
                  className="glow-button w-full flex items-center justify-center gap-2"
                  onClick={handleAdClick}
                  disabled={isProcessing || !sdkLoaded}
                >
                  {isProcessing ? (
                    <>
                      <Loader2 className="w-5 h-5 animate-spin" />
                      <span style={{ fontFamily: 'var(--font-display)' }}>
                        CARREGANDO...
                      </span>
                    </>
                  ) : !sdkLoaded ? (
                    <>
                      <Loader2 className="w-5 h-5 animate-spin" />
                      <span style={{ fontFamily: 'var(--font-display)' }}>
                        CARREGANDO ANÚNCIOS...
                      </span>
                    </>
                  ) : (
                    <>
                      <Play className="w-5 h-5" />
                      <span style={{ fontFamily: 'var(--font-display)' }}>
                        ASSISTIR ANÚNCIO
                      </span>
                    </>
                  )}
                </button>
              )}
            </div>

            {/* Status Info */}
            <div className="glass-card p-4 flex items-center gap-3">
              <Clock className="w-5 h-5 text-muted-foreground" />
              <div className="text-sm">
                <p className="text-muted-foreground">
                  YMID: <span className="text-foreground font-mono" style={{ fontFamily: 'var(--font-mono)' }}>{ymid}</span>
                </p>
                {stats?.session_expired && (
                  <p className="text-amber-400 text-xs mt-1">Sessão expirada - faça login novamente no app</p>
                )}
              </div>
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
