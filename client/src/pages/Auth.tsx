import { useEffect, useState } from 'react';
import { useLocation, useSearch } from 'wouter';
import { Loader2, CheckCircle2, XCircle } from 'lucide-react';
import StarField from '@/components/StarField';

/*
 * Design: Glassmorphism Cosmos
 * Página de autenticação - recebe YMID via URL do app Android
 * URL: /auth?ymid=123456
 */

const YMID_STORAGE_KEY = 'youngmoney_ymid';

export default function Auth() {
  const [, setLocation] = useLocation();
  const search = useSearch();
  const [status, setStatus] = useState<'loading' | 'success' | 'error'>('loading');
  const [message, setMessage] = useState('Autenticando...');

  useEffect(() => {
    const params = new URLSearchParams(search);
    const ymid = params.get('ymid');

    if (ymid && ymid.trim()) {
      // Salvar YMID no localStorage
      localStorage.setItem(YMID_STORAGE_KEY, ymid.trim());
      setStatus('success');
      setMessage('Conta vinculada com sucesso!');
      
      // Redirecionar para tasks após 1.5s
      setTimeout(() => {
        setLocation('/tasks');
      }, 1500);
    } else {
      setStatus('error');
      setMessage('YMID não fornecido. Abra este link pelo app.');
    }
  }, [search, setLocation]);

  return (
    <div className="min-h-screen flex flex-col items-center justify-center p-4 relative overflow-hidden">
      <StarField />
      
      <div className="glass-card p-8 max-w-sm w-full text-center relative z-10 animate-float">
        {status === 'loading' && (
          <>
            <Loader2 className="w-16 h-16 mx-auto mb-4 text-primary animate-spin" />
            <h1 
              className="text-xl font-semibold mb-2"
              style={{ fontFamily: 'var(--font-display)' }}
            >
              {message}
            </h1>
            <p className="text-muted-foreground text-sm">
              Aguarde um momento...
            </p>
          </>
        )}

        {status === 'success' && (
          <>
            <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-500/20 flex items-center justify-center">
              <CheckCircle2 className="w-10 h-10 text-emerald-400" />
            </div>
            <h1 
              className="text-xl font-semibold mb-2 text-emerald-400"
              style={{ fontFamily: 'var(--font-display)' }}
            >
              {message}
            </h1>
            <p className="text-muted-foreground text-sm">
              Redirecionando para suas tarefas...
            </p>
          </>
        )}

        {status === 'error' && (
          <>
            <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/20 flex items-center justify-center">
              <XCircle className="w-10 h-10 text-red-400" />
            </div>
            <h1 
              className="text-xl font-semibold mb-2 text-red-400"
              style={{ fontFamily: 'var(--font-display)' }}
            >
              Erro na autenticação
            </h1>
            <p className="text-muted-foreground text-sm mb-4">
              {message}
            </p>
            <button
              onClick={() => setLocation('/')}
              className="glow-button text-sm"
            >
              Voltar ao início
            </button>
          </>
        )}
      </div>
    </div>
  );
}
