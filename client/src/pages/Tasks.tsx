import { useEffect, useState, useCallback } from 'react';
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
 */

const YMID_STORAGE_KEY = 'youngmoney_ymid';
const API_BASE_URL = 'https://monetag-postback-server-production.up.railway.app/api/stats/user';

// Requisitos para completar a tarefa
const REQUIRED_IMPRESSIONS = 5;
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

export default function Tasks() {
  const [, setLocation] = useLocation();
  const [ymid, setYmid] = useState<string | null>(null);
  const [stats, setStats] = useState<UserStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Carregar YMID do localStorage
  useEffect(() => {
    const storedYMID = localStorage.getItem(YMID_STORAGE_KEY);
    if (!storedYMID) {
      setLocation('/');
      return;
    }
    setYmid(storedYMID);
  }, [setLocation]);

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
                  onClick={() => toast.info('Abra o app para assistir anúncios')}
                >
                  <Play className="w-5 h-5" />
                  <span style={{ fontFamily: 'var(--font-display)' }}>
                    ASSISTIR ANÚNCIO
                  </span>
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
