import { useState, useEffect, useCallback } from 'react';

/*
 * Hook para gerenciar autenticação via YMID
 * O app Android envia o YMID via URL: site.com/auth?ymid=123456
 * O site salva no localStorage para reconhecer o usuário
 */

const YMID_STORAGE_KEY = 'youngmoney_ymid';

interface UserStats {
  success: boolean;
  ymid: string;
  total_impressions: number;
  total_clicks: number;
  total_revenue: string;
  session_expired: boolean;
  time_remaining: number;
}

interface UseYMIDReturn {
  ymid: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  stats: UserStats | null;
  error: string | null;
  setYMID: (ymid: string) => void;
  clearYMID: () => void;
  refreshStats: () => Promise<void>;
}

const API_BASE_URL = 'https://monetag-postback-server-production.up.railway.app/api/stats/user';

export function useYMID(): UseYMIDReturn {
  const [ymid, setYmidState] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [stats, setStats] = useState<UserStats | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Carregar YMID do localStorage ao iniciar
  useEffect(() => {
    const storedYMID = localStorage.getItem(YMID_STORAGE_KEY);
    if (storedYMID) {
      setYmidState(storedYMID);
    }
    setIsLoading(false);
  }, []);

  // Salvar YMID no localStorage
  const setYMID = useCallback((newYmid: string) => {
    localStorage.setItem(YMID_STORAGE_KEY, newYmid);
    setYmidState(newYmid);
    setError(null);
  }, []);

  // Limpar YMID
  const clearYMID = useCallback(() => {
    localStorage.removeItem(YMID_STORAGE_KEY);
    setYmidState(null);
    setStats(null);
    setError(null);
  }, []);

  // Buscar estatísticas do usuário
  const refreshStats = useCallback(async () => {
    if (!ymid) {
      setError('YMID não encontrado');
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`${API_BASE_URL}/${ymid}`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error(`Erro HTTP: ${response.status}`);
      }

      const data: UserStats = await response.json();
      
      if (data.success) {
        setStats(data);
      } else {
        setError('Falha ao carregar estatísticas');
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erro desconhecido');
    } finally {
      setIsLoading(false);
    }
  }, [ymid]);

  // Buscar stats quando YMID mudar
  useEffect(() => {
    if (ymid) {
      refreshStats();
    }
  }, [ymid, refreshStats]);

  return {
    ymid,
    isLoading,
    isAuthenticated: !!ymid,
    stats,
    error,
    setYMID,
    clearYMID,
    refreshStats,
  };
}
