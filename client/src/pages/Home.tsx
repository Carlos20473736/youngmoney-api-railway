import { useEffect } from 'react';
import { useLocation } from 'wouter';
import { 
  Smartphone, 
  Gift, 
  TrendingUp, 
  Shield,
  Sparkles,
  ExternalLink
} from 'lucide-react';
import StarField from '@/components/StarField';

/*
 * Design: Glassmorphism Cosmos
 * Landing page - redireciona automaticamente se já autenticado
 * Sem login manual - apenas via URL do app
 */

const YMID_STORAGE_KEY = 'youngmoney_ymid';

export default function Home() {
  const [, setLocation] = useLocation();

  // Verificar se já está autenticado
  useEffect(() => {
    const storedYMID = localStorage.getItem(YMID_STORAGE_KEY);
    if (storedYMID) {
      setLocation('/tasks');
    }
  }, [setLocation]);

  const features = [
    {
      icon: Gift,
      title: 'Ganhe Recompensas',
      description: 'Complete tarefas simples e acumule pontos para trocar por prêmios',
      color: 'text-amber-400',
      bgColor: 'bg-amber-400/10'
    },
    {
      icon: TrendingUp,
      title: 'Acompanhe seu Progresso',
      description: 'Veja suas estatísticas em tempo real e acompanhe suas conquistas',
      color: 'text-emerald-400',
      bgColor: 'bg-emerald-400/10'
    },
    {
      icon: Shield,
      title: 'Seguro e Confiável',
      description: 'Seus dados estão protegidos e suas recompensas garantidas',
      color: 'text-cyan-400',
      bgColor: 'bg-cyan-400/10'
    }
  ];

  return (
    <div className="min-h-screen flex flex-col relative overflow-hidden">
      <StarField />
      
      {/* Hero Section */}
      <main className="flex-1 flex flex-col items-center justify-center p-4 relative z-10">
        <div className="max-w-md w-full space-y-8">
          {/* Logo & Title */}
          <div className="text-center space-y-4">
            <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-primary to-cyan-500 shadow-lg shadow-primary/30 animate-float">
              <Sparkles className="w-10 h-10 text-white" />
            </div>
            
            <h1 
              className="text-4xl font-bold bg-gradient-to-r from-primary via-cyan-400 to-emerald-400 bg-clip-text text-transparent"
              style={{ fontFamily: 'var(--font-display)' }}
            >
              YoungMoney
            </h1>
            
            <p className="text-muted-foreground text-lg">
              Complete tarefas, ganhe recompensas
            </p>
          </div>

          {/* Features */}
          <div className="space-y-3">
            {features.map((feature, index) => (
              <div 
                key={index}
                className="glass-card glass-card-hover p-4 flex items-start gap-4"
                style={{ animationDelay: `${index * 100}ms` }}
              >
                <div className={`p-2.5 rounded-xl ${feature.bgColor}`}>
                  <feature.icon className={`w-5 h-5 ${feature.color}`} />
                </div>
                <div>
                  <h3 
                    className="font-semibold mb-1"
                    style={{ fontFamily: 'var(--font-display)' }}
                  >
                    {feature.title}
                  </h3>
                  <p className="text-sm text-muted-foreground">
                    {feature.description}
                  </p>
                </div>
              </div>
            ))}
          </div>

          {/* CTA - Acesso pelo App */}
          <div className="glass-card p-6 text-center">
            <Smartphone className="w-16 h-16 mx-auto mb-4 text-primary animate-pulse-glow" />
            <h2 
              className="text-xl font-semibold mb-2"
              style={{ fontFamily: 'var(--font-display)' }}
            >
              Acesse pelo App
            </h2>
            <p className="text-sm text-muted-foreground mb-4">
              Abra o app YoungMoney e clique em <span className="text-primary font-semibold">"Assistir Anúncio"</span> para acessar este site automaticamente com sua conta vinculada.
            </p>
            
            <div className="flex items-center justify-center gap-2 text-xs text-muted-foreground/70 mt-4">
              <ExternalLink className="w-3.5 h-3.5" />
              <span>O app abrirá este site com seu ID automaticamente</span>
            </div>
          </div>

          {/* Info Box */}
          <div className="glass-card p-4 border-l-4 border-primary/50">
            <p className="text-sm text-muted-foreground">
              <span className="text-foreground font-medium">Não tem o app?</span> Baixe o YoungMoney na Play Store para começar a ganhar recompensas.
            </p>
          </div>

          {/* Footer */}
          <p className="text-center text-xs text-muted-foreground pt-4">
            © 2024 YoungMoney. Todos os direitos reservados.
          </p>
        </div>
      </main>
    </div>
  );
}
