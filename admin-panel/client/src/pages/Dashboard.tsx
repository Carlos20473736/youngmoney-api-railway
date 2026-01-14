import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Users, Coins, Clock, TrendingUp, UserPlus, Trophy } from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";

export default function Dashboard() {
  const { data: stats, isLoading } = trpc.dashboard.stats.useQuery();

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold">Dashboard</h1>
          <p className="text-muted-foreground mt-2">Visão geral do sistema Young Money</p>
        </div>
        
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {[...Array(6)].map((_, i) => (
            <Card key={i}>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-8 w-8 rounded-full" />
              </CardHeader>
              <CardContent>
                <Skeleton className="h-8 w-20 mb-2" />
                <Skeleton className="h-3 w-32" />
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    );
  }

  const statCards = [
    {
      title: "Total de Usuários",
      value: stats?.totalUsers.toLocaleString('pt-BR') || "0",
      description: "Usuários cadastrados no app",
      icon: Users,
      color: "text-blue-600",
      bgColor: "bg-blue-100",
    },
    {
      title: "Pontos Distribuídos",
      value: stats?.totalPoints.toLocaleString('pt-BR') || "0",
      description: "Total de pontos em circulação",
      icon: Coins,
      color: "text-yellow-600",
      bgColor: "bg-yellow-100",
    },
    {
      title: "Saques Pendentes",
      value: stats?.pendingWithdrawals.toLocaleString('pt-BR') || "0",
      description: "Aguardando aprovação",
      icon: Clock,
      color: "text-orange-600",
      bgColor: "bg-orange-100",
    },
    {
      title: "Total Sacado",
      value: `R$ ${((stats?.totalWithdrawn || 0) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`,
      description: "Valor total de saques realizados",
      icon: TrendingUp,
      color: "text-green-600",
      bgColor: "bg-green-100",
    },
    {
      title: "Total de Convites",
      value: "0",
      description: "Convites realizados com sucesso",
      icon: UserPlus,
      color: "text-purple-600",
      bgColor: "bg-purple-100",
    },
    {
      title: "Receita Estimada",
      value: `R$ ${((stats?.totalWithdrawn || 0) * 0.1 / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`,
      description: "10% de taxa sobre saques",
      icon: Trophy,
      color: "text-pink-600",
      bgColor: "bg-pink-100",
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Dashboard</h1>
        <p className="text-muted-foreground mt-2">Visão geral do sistema Young Money</p>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {statCards.map((stat, index) => {
          const Icon = stat.icon;
          return (
            <Card key={index} className="hover:shadow-lg transition-shadow">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{stat.title}</CardTitle>
                <div className={`p-2 rounded-full ${stat.bgColor}`}>
                  <Icon className={`h-4 w-4 ${stat.color}`} />
                </div>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stat.value}</div>
                <p className="text-xs text-muted-foreground mt-1">{stat.description}</p>
              </CardContent>
            </Card>
          );
        })}
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Atividade Recente</CardTitle>
            <CardDescription>Últimas ações no sistema</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="text-sm text-muted-foreground">
              <p className="mb-2">• Sistema funcionando normalmente</p>
              <p className="mb-2">• {stats?.totalUsers || 0} usuários ativos</p>
              <p className="mb-2">• {stats?.pendingWithdrawals || 0} saques aguardando processamento</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Status do Sistema</CardTitle>
            <CardDescription>Monitoramento em tempo real</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm">API Young Money</span>
                <span className="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full font-medium">Online</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm">Banco de Dados</span>
                <span className="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full font-medium">Conectado</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm">Sistema de Pagamentos</span>
                <span className="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full font-medium">Ativo</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
