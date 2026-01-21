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
 * Inclui sistema de anúncios Monetag com overlay de 15 segundos
 */

const YMID_STORAGE_KEY = 'youngmoney_ymid';
const API_BASE_URL = 'https://monetag-postback-server-production.up.railway.app/api/stats/user';

// Configuração Monetag
const ZONE_ID = '10325249';
const SDK_FUNC = 'show_10325249';
const SCRIPT_SRC = 'https://libtl.com/sdk.js';

// Servidor de Postback
const POSTBACK_SERVER = 'https://monetag-postback-server-production.up.railway.app';
const POSTBACK_URL = `${POSTBACK_SERVER}/api/postback`;

// Young Money API
const YOUNGMONEY_API = 'https://youngmoney-api-railway-production.up.railway.app';

// Requisitos para completar a tarefa (valores padrão, serão atualizados pela API)
let REQUIRED_IMPRESSIONS = 5;
const REQUIRED_CLICKS = 1;

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
    Android?: {
      getUserId?: () => string;
      getEmail?: () => string;
      closeActivity?: () => void;
      onTaskCompleted?: () => void;
    };
    AndroidInterface?: {
      getUserId?: () => string;
      getEmail?: () => string;
      closeActivity?: () => void;
      onTaskCompleted?: () => void;
    };
    Telegram?: {
      WebApp?: {
        initDataUnsafe?: {
          user?: {
            id: number;
          };
        };
        close?: () => void;
      };
    };
    MontagOverlay?: {
      show: () => void;
      hide: () => void;
    };
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
  const [requiredImpressionsLoaded, setRequiredImpressionsLoaded] = useState(false);
  const [showOverlay, setShowOverlay] = useState(false);
  const [overlayCountdown, setOverlayCountdown] = useState(15);
  const overlayIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const overlayCreatedRef = useRef(false);

  // ========================================
  // SISTEMA DE OVERLAY FLUTUANTE DE 15 SEGUNDOS
  // ========================================
  const OVERLAY_DURATION = 15;

  const createFloatingOverlay = useCallback(() => {
    if (overlayCreatedRef.current) {
      console.log('[OVERLAY] Overlay já existe, ignorando...');
      return;
    }

    overlayCreatedRef.current = true;
    setShowOverlay(true);
    setOverlayCountdown(OVERLAY_DURATION);

    console.log('[OVERLAY] Criando overlay de ' + OVERLAY_DURATION + ' segundos...');

    overlayIntervalRef.current = setInterval(() => {
      setOverlayCountdown((prev) => {
        if (prev <= 1) {
          // Remover overlay
          if (overlayIntervalRef.current) {
            clearInterval(overlayIntervalRef.current);
          }
          setShowOverlay(false);
          overlayCreatedRef.current = false;
          console.log('[OVERLAY] Overlay removido, recarregando página...');
          window.location.reload();
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
  }, []);

  const removeOverlay = useCallback(() => {
    if (overlayIntervalRef.current) {
      clearInterval(overlayIntervalRef.current);
    }
    setShowOverlay(false);
    overlayCreatedRef.current = false;
  }, []);

  // Expor funções para uso externo
  useEffect(() => {
    window.MontagOverlay = {
      show: createFloatingOverlay,
      hide: removeOverlay
    };
  }, [createFloatingOverlay, removeOverlay]);

  // ========================================
  // INTERCEPTAR POSTBACKS DO MONETAG
  // ========================================
  useEffect(() => {
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

    // Interceptar XMLHttpRequest
    const originalXHROpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function(method: string, url: string | URL, ...rest: any[]) {
      const urlStr = url.toString();
      if (urlStr.includes('youngmoney-api-railway')) {
        if (urlStr.includes('%7Bymid%7D') || urlStr.includes('{ymid}')) {
          console.log('[BLOQUEIO XHR] Postback do Monetag bloqueado:', urlStr);
          const eventType = urlStr.includes('event_type=click') || urlStr.includes('event_type%3Dclick') ? 'click' : 'impression';
          sendPostbackToNewServer(eventType);
          return originalXHROpen.call(this, method, 'about:blank', ...rest);
        }
      }
      return originalXHROpen.call(this, method, url, ...rest);
    };

    return () => {
      window.fetch = originalFetch;
      XMLHttpRequest.prototype.open = originalXHROpen;
    };
  }, [ymid, userEmail]);

  // ========================================
  // ENVIAR POSTBACK PARA NOVO SERVIDOR
  // ========================================
  const sendPostbackToNewServer = useCallback((eventType: string) => {
    console.log('[POSTBACK] Enviando ' + eventType + ' para novo servidor');

    // Se for clique, mostrar overlay de 15 segundos
    if (eventType === 'click') {
      console.log('[POSTBACK] CLIQUE DETECTADO! Mostrando overlay de 15 segundos...');
      createFloatingOverlay();
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

    fetch(url, { method: 'GET', mode: 'cors' })
      .then(response => response.json())
      .then(data => {
        console.log('[POSTBACK] ' + eventType + ' enviado com sucesso:', data);
        // Atualizar estatísticas
        setTimeout(() => fetchStats(), 500);
      })
      .catch(err => {
        console.error('[POSTBACK] Erro ao enviar ' + eventType + ':', err);
      });
  }, [ymid, userEmail, createFloatingOverlay]);

  // ========================================
  // CARREGAR SDK DO MONETAG
  // ========================================
  useEffect(() => {
    const loadMonetagSDK = () => {
      if (document.querySelector(`script[src="${SCRIPT_SRC}"]`)) {
        console.log('[SDK] Script já carregado');
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
      };
      document.head.appendChild(script);
      console.log('[SDK] Carregando Monetag...');
    };

    loadMonetagSDK();
  }, []);

  // ========================================
  // BUSCAR REQUISITOS DE IMPRESSÕES DA API
  // ========================================
  const fetchRequiredImpressions = useCallback(async () => {
    try {
      console.log('[REQUIREMENTS] Buscando número de impressões necessárias da API...');
      const url = `${YOUNGMONEY_API}/monetag/progress.php?user_id=${ymid || 1}`;
      const response = await fetch(url);
      const data = await response.json();

      if (data.success && data.data) {
        REQUIRED_IMPRESSIONS = data.data.required_impressions || 5;
        setRequiredImpressionsLoaded(true);
        console.log('[REQUIREMENTS] Impressões necessárias:', REQUIRED_IMPRESSIONS);
      }
    } catch (error) {
      console.error('[REQUIREMENTS] Erro ao buscar requisitos:', error);
    }
  }, [ymid]);

  // Carregar YMID do localStorage ou Android/Telegram
  useEffect(() => {
    // Tentar obter do Android
    if (window.Android || window.AndroidInterface) {
      try {
        const androidInterface = window.Android || window.AndroidInterface;
        const rawUserId = androidInterface?.getUserId?.();
        const rawEmail = androidInterface?.getEmail?.();
        
        if (rawUserId) {
          setYmid(rawUserId);
          localStorage.setItem(YMID_STORAGE_KEY, rawUserId);
        }
        if (rawEmail) {
          setUserEmail(rawEmail);
        }
        console.log('[INIT] Dados do Android:', { rawUserId, rawEmail });
      } catch (e) {
        console.error('[INIT] Erro ao obter dados do Android:', e);
      }
    }

    // Tentar obter do Telegram
    if (window.Telegram?.WebApp?.initDataUnsafe?.user?.id) {
      const telegramId = window.Telegram.WebApp.initDataUnsafe.user.id.toString();
      setYmid(telegramId);
      localStorage.setItem(YMID_STORAGE_KEY, telegramId);
      console.log('[INIT] Dados do Telegram:', telegramId);
    }

    // Fallback para localStorage
    const storedYMID = localStorage.getItem(YMID_STORAGE_KEY);
    if (!storedYMID && !ymid) {
      // Gerar ID temporário se não houver nenhum
      const tempId = 'guest_' + Date.now();
      setYmid(tempId);
      localStorage.setItem(YMID_STORAGE_KEY, tempId);
    } else if (storedYMID && !ymid) {
      setYmid(storedYMID);
    }
  }, []);

  // Buscar requisitos quando YMID carregar
  useEffect(() => {
    if (ymid) {
      fetchRequiredImpressions();
    }
  }, [ymid, fetchRequiredImpressions]);

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
      
      if (data.success || data.ymid) {
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

  // ========================================
  // CLIQUE NO BOTÃO DE ANÚNCIO
  // ========================================
  const handleAdClick = useCallback(async () => {
    if (isProcessing) return;

    setIsProcessing(true);

    // Verificar se SDK está pronto
    if (typeof window[SDK_FUNC] !== 'function') {
      console.warn('[AD] SDK não está pronto');
      setIsProcessing(false);
      toast.warning('Aguarde o carregamento...');
      return;
    }

    console.log('[AD] Chamando SDK com ymid:', ymid);

    try {
      await window[SDK_FUNC]({
        ymid: ymid,
        requestVar: userEmail
      });

      console.log('[AD] Anúncio exibido pelo Monetag');
      // Enviar postback manual de impression
      sendPostbackToNewServer('impression');

      setTimeout(() => {
        setIsProcessing(false);
        fetchStats();
      }, 2000);
    } catch (err) {
      console.error('[AD] Erro:', err);
      setIsProcessing(false);
    }
  }, [ymid, userEmail, isProcessing, sendPostbackToNewServer, fetchStats]);

  // Logout
  const handleLogout = () => {
    localStorage.removeItem(YMID_STORAGE_KEY);
    toast.success('Desconectado com sucesso');
    setLocation('/');
  };

  // Voltar
  const goBack = () => {
    if (window.Android?.closeActivity) {
      window.Android.closeActivity();
    } else if (window.history.length > 1) {
      window.history.back();
    } else {
      setLocation('/');
    }
  };

  // Calcular progresso
  const impressionsProgress = stats 
    ? Math.min((stats.total_impressions / REQUIRED_IMPRESSIONS) * 100, 100) 
    : 0;
  const clicksProgress = stats 
    ? Math.min((stats.total_clicks / REQUIRED_CLICKS) * 100, 100) 
    : 0;
  
  // Verificar se tarefa está concluída
  const isTaskComplete = requiredImpressionsLoaded && stats 
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
      
      {/* Overlay de 15 segundos */}
      {showOverlay && (
        <div 
          className="fixed inset-0 z-[2147483647] flex items-center justify-center"
          style={{ 
            background: 'rgba(0, 0, 0, 0.1)',
            pointerEvents: 'auto'
          }}
          onClick={(e) => {
            e.stopPropagation();
            e.preventDefault();
          }}
        >
          <div 
            className="glass-card p-8 text-center"
            style={{
              background: 'rgba(10, 14, 39, 0.95)',
              border: '2px solid rgba(139, 92, 246, 0.5)',
              borderRadius: '1rem',
              boxShadow: '0 0 30px rgba(139, 92, 246, 0.3)'
            }}
          >
            <div 
              className="text-6xl font-bold mb-4"
              style={{
                background: 'linear-gradient(135deg, #8b5cf6 0%, #00ddff 100%)',
                WebkitBackgroundClip: 'text',
                WebkitTextFillColor: 'transparent'
              }}
            >
              {overlayCountdown}
            </div>
            <p className="text-white text-lg mb-2">Aguarde o anúncio...</p>
            <p className="text-gray-400 text-sm">
              A página será atualizada automaticamente
            </p>
            <div className="mt-4 w-full bg-gray-700 rounded-full h-2">
              <div 
                className="h-2 rounded-full transition-all duration-1000"
                style={{
                  width: `${((OVERLAY_DURATION - overlayCountdown) / OVERLAY_DURATION) * 100}%`,
                  background: 'linear-gradient(90deg, #8b5cf6 0%, #00ddff 100%)'
                }}
              />
            </div>
          </div>
        </div>
      )}
      
      {/* Header */}
      <header className="relative z-10 p-4">
        <div className="glass-card px-4 py-3 flex items-center justify-between">
          <button
            onClick={goBack}
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
