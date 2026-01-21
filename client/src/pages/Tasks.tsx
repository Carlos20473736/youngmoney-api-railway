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

  // Carregar YMID e EMAIL do localStorage
  useEffect(() => {
    const storedYMID = localStorage.getItem(YMID_STORAGE_KEY);
    if (!storedYMID) {
      setLocation('/');
      return;
    }
    setYmid(storedYMID);
    
    // Buscar email do localStorage primeiro, depois tentar API
    const storedEmail = localStorage.getItem(EMAIL_STORAGE_KEY);
    if (storedEmail) {
      setUserEmail(storedEmail);
      console.log('[EMAIL] Email obtido do localStorage:', storedEmail);
    } else {
      // Fallback: buscar email do profile
      fetchUserProfile();
    }
  }, [setLocation]);

  // Carregar SDK do Monetag
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

    // Configurar interceptadores de postback
    setupPostbackInterceptors();
    
    // Configurar detector de anúncios
    setupAdDetectionObserver();
    
    loadMonetagSDK();
  }, [ymid]);

  // Buscar email do usuário (fallback se não estiver no localStorage)
  const fetchUserProfile = async () => {
    // Verificar localStorage primeiro
    const storedEmail = localStorage.getItem(EMAIL_STORAGE_KEY);
    if (storedEmail) {
      setUserEmail(storedEmail);
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
          localStorage.setItem(EMAIL_STORAGE_KEY, email);
          console.log('[PROFILE] Email obtido da API:', email);
        }
      }
    } catch (err) {
      console.error('[PROFILE] Erro ao buscar email:', err);
    }
  };

  // Configurar interceptadores de postback do Monetag
  const setupPostbackInterceptors = () => {
    // Interceptar fetch
    const originalFetch = window.fetch;
    window.fetch = function(...args: Parameters<typeof fetch>) {
      const url = args[0];
      if (typeof url === 'string' && url.includes('youngmoney-api-railway')) {
        if (url.includes('%7Bymid%7D') || url.includes('{ymid}')) {
          console.log('[BLOQUEIO FETCH] Postback do Monetag bloqueado:', url);
          const eventType = url.includes('event_type=click') || url.includes('event_type%3Dclick') ? 'click' : 'impression';
          sendPostbackToNewServer(eventType);
          return Promise.resolve(new Response('', { status: 200 }));
        }
      }
      return originalFetch.apply(window, args);
    };

    console.log('[INTERCEPTOR] Interceptadores de postback configurados');
  };

  // Configurar detector de anúncios Monetag
  const setupAdDetectionObserver = () => {
    const detectMonetagAd = (): boolean => {
      const adIndicators = [
        'iframe[src*="monetag"]',
        'iframe[src*="libtl"]',
        'iframe[src*="ad"]',
        '[class*="monetag"]',
        '[id*="monetag"]',
        'div[style*="z-index: 2147483647"]',
        'div[style*="z-index:2147483647"]',
        'iframe[style*="z-index"]',
        'div[id^="container-"]'
      ];

      for (const selector of adIndicators) {
        try {
          const elements = document.querySelectorAll(selector);
          if (elements.length > 0) {
            console.log('[DETECTOR] Anúncio detectado via seletor:', selector);
            return true;
          }
        } catch (e) {
          // Ignorar erros de seletor inválido
        }
      }

      // Verificar iframes
      const iframes = document.querySelectorAll('iframe');
      for (const iframe of iframes) {
        const src = iframe.src || '';
        const style = iframe.getAttribute('style') || '';
        if (src.includes('monetag') || src.includes('libtl') || src.includes('ad') || style.includes('z-index')) {
          console.log('[DETECTOR] Iframe de anúncio detectado:', src);
          return true;
        }
      }

      return false;
    };

    let adDetected = false;
    const observer = new MutationObserver(() => {
      if (adDetected) return;

      if (detectMonetagAd()) {
        console.log('[OBSERVER] Anúncio Monetag detectado!');
        
        // Verificar se usuário já tem cliques
        if (stats && stats.total_clicks >= 1) {
          adDetected = true;
          console.log('[OBSERVER] Usuário tem cliques. Mostrando overlay automático!');
          if (!overlayCreatedRef.current) {
            createFloatingOverlay();
          }
        } else {
          console.log('[OBSERVER] Usuário ainda não tem cliques. Overlay não será exibido.');
          setTimeout(() => { adDetected = false; }, 2000);
        }
      }
    });

    observer.observe(document.documentElement, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['style', 'class', 'src']
    });

    console.log('[OBSERVER] Observador de anúncios ativado');
  };

  // Criar overlay flutuante de 15 segundos
  const createFloatingOverlay = () => {
    if (overlayCreatedRef.current) {
      console.log('[OVERLAY] Overlay já existe, ignorando...');
      return;
    }

    overlayCreatedRef.current = true;
    setShowOverlay(true);
    setOverlayCountdown(OVERLAY_DURATION);

    console.log('[OVERLAY] Criando overlay de', OVERLAY_DURATION, 'segundos...');

    // Iniciar contador regressivo
    overlayIntervalRef.current = setInterval(() => {
      setOverlayCountdown(prev => {
        const newCount = prev - 1;
        console.log('[OVERLAY] Contador:', newCount, 'segundos restantes');
        
        if (newCount <= 0) {
          if (overlayIntervalRef.current) {
            clearInterval(overlayIntervalRef.current);
          }
          console.log('[OVERLAY] Contador finalizado! Reiniciando página...');
          window.location.reload();
        }
        
        return newCount;
      });
    }, 1000);
  };

  // Enviar postback para novo servidor
  const sendPostbackToNewServer = async (eventType: string) => {
    console.log('[POSTBACK] Enviando', eventType, 'para novo servidor');

    // Se for clique, mostrar overlay imediatamente
    if (eventType === 'click') {
      console.log('[POSTBACK] CLIQUE DETECTADO! Mostrando overlay de 15 segundos...');
      if (!overlayCreatedRef.current) {
        createFloatingOverlay();
      }
    }

    const params = new URLSearchParams({
      event_type: eventType,
      zone_id: ZONE_ID,
      ymid: ymid || 'unknown',
      user_email: userEmail,
      estimated_price: eventType === 'click' ? '0.0045' : '0.0023'
    });

    const url = `${POSTBACK_URL}?${params.toString()}`;
    console.log('[POSTBACK] URL completa:', url);

    try {
      const response = await fetch(url, { method: 'GET', mode: 'cors' });
      const data = await response.json();
      console.log('[POSTBACK]', eventType, 'enviado com sucesso:', data);
      
      // Atualizar estatísticas após 500ms
      setTimeout(() => fetchStats(), 500);
    } catch (err) {
      console.error('[POSTBACK] Erro ao enviar', eventType, ':', err);
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
      
      // Enviar postback manual de impression
      sendPostbackToNewServer('impression');

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

  // Calcular progresso do círculo do overlay
  const circumference = 283; // 2 * PI * 45
  const overlayProgress = (OVERLAY_DURATION - overlayCountdown) / OVERLAY_DURATION;
  const strokeDashoffset = circumference * (1 - overlayProgress);

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
      
      {/* Overlay de 15 segundos */}
      {showOverlay && (
        <div 
          className="fixed inset-0 z-[2147483647] flex items-center justify-center animate-in fade-in duration-300"
          style={{ background: 'rgba(0, 0, 0, 0.1)', pointerEvents: 'auto' }}
          onClick={(e) => { e.stopPropagation(); e.preventDefault(); }}
        >
          <div 
            className="p-9 rounded-2xl text-center max-w-[90%] w-[380px] animate-in zoom-in-95 duration-400"
            style={{ 
              background: 'linear-gradient(135deg, rgba(26, 26, 46, 0.6) 0%, rgba(22, 33, 62, 0.6) 100%)',
              boxShadow: '0 10px 40px rgba(0,0,0,0.3), 0 0 20px rgba(139, 92, 246, 0.2)',
              border: '2px solid rgba(139, 92, 246, 0.3)',
              opacity: 0.85
            }}
          >
            <div className="w-10 h-10 mx-auto mb-4 relative">
              <svg viewBox="0 0 100 100" className="w-full h-full" style={{ transform: 'rotate(-90deg)' }}>
                <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(139, 92, 246, 0.2)" strokeWidth="8" />
                <circle 
                  cx="50" cy="50" r="45" 
                  fill="none" 
                  stroke="#00ddff" 
                  strokeWidth="8" 
                  strokeLinecap="round"
                  strokeDasharray={circumference}
                  strokeDashoffset={strokeDashoffset}
                  style={{ transition: 'stroke-dashoffset 1s linear' }}
                />
              </svg>
              <span 
                className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-sm font-bold"
                style={{ fontFamily: "'Orbitron', monospace", color: '#00ddff' }}
              >
                {overlayCountdown}
              </span>
            </div>
            <p className="text-sm text-gray-400" style={{ fontFamily: "'Inter', sans-serif" }}>
              Realizando tarefa automaticamente...
            </p>
          </div>
        </div>
      )}
      
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
