import { useState } from "react";
import { useRoute, useLocation } from "wouter";
import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ArrowLeft, Loader2, Coins, Wallet, User, Mail, Phone, Calendar, History, TrendingUp, TrendingDown } from "lucide-react";
import { toast } from "sonner";

export default function UserDetail() {
  const [, params] = useRoute("/users/:id");
  const [, setLocation] = useLocation();
  const userId = parseInt(params?.id || "0");

  const [showEditPoints, setShowEditPoints] = useState(false);
  const [showEditBalance, setShowEditBalance] = useState(false);
  const [newPoints, setNewPoints] = useState("");
  const [newBalance, setNewBalance] = useState("");

  const { data: user, isLoading, refetch } = trpc.appUsers.getById.useQuery({ id: userId });
  const { data: transactions } = trpc.pointTransactions.list.useQuery({ userId, limit: 50 });
  const { data: pointsHistory } = trpc.pointTransactions.userHistory.useQuery({ userId, limit: 100 });

  const updatePointsMutation = trpc.appUsers.updatePoints.useMutation({
    onSuccess: () => {
      toast.success("Pontos atualizados com sucesso");
      setShowEditPoints(false);
      refetch();
    },
    onError: (error: any) => {
      toast.error(`Erro: ${error.message}`);
    },
  });

  const updateBalanceMutation = trpc.appUsers.updateBalance.useMutation({
    onSuccess: () => {
      toast.success("Saldo atualizado com sucesso");
      setShowEditBalance(false);
      refetch();
    },
    onError: (error: any) => {
      toast.error(`Erro: ${error.message}`);
    },
  });

  const handleUpdatePoints = () => {
    const points = parseInt(newPoints);
    if (isNaN(points)) {
      toast.error("Quantidade inválida");
      return;
    }
    updatePointsMutation.mutate({ id: userId, points });
  };

  const handleUpdateBalance = () => {
    const balance = parseFloat(newBalance);
    if (isNaN(balance)) {
      toast.error("Valor inválido");
      return;
    }
    updateBalanceMutation.mutate({ id: userId, balance });
  };

  const formatCurrency = (value: number | string) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(num || 0);
  };

  const formatNumber = (value: number | string) => {
    const num = typeof value === 'string' ? parseInt(value) : value;
    return new Intl.NumberFormat("pt-BR").format(num || 0);
  };

  const formatDate = (date: string | Date) => {
    if (!date) return "-";
    const d = new Date(date);
    return d.toLocaleDateString("pt-BR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <Loader2 className="w-8 h-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  if (!user) {
    return (
      <div className="space-y-6">
        <Button variant="ghost" onClick={() => setLocation("/users")}>
          <ArrowLeft className="w-4 h-4 mr-2" />
          Voltar
        </Button>
        <Card>
          <CardContent className="py-8 text-center text-muted-foreground">
            Usuário não encontrado
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Button variant="ghost" onClick={() => setLocation("/users")}>
          <ArrowLeft className="w-4 h-4 mr-2" />
          Voltar
        </Button>
        <div>
          <h1 className="text-3xl font-bold">{user.name || "Usuário"}</h1>
          <p className="text-muted-foreground">ID #{user.id}</p>
        </div>
      </div>

      {/* User Info Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Coins className="w-4 h-4" />
              Pontos
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{formatNumber(user.points)}</div>
            <Button 
              variant="link" 
              className="p-0 h-auto text-sm"
              onClick={() => {
                setNewPoints(String(user.points || 0));
                setShowEditPoints(true);
              }}
            >
              Editar pontos
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Wallet className="w-4 h-4" />
              Saldo
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{formatCurrency(user.balance)}</div>
            <Button 
              variant="link" 
              className="p-0 h-auto text-sm"
              onClick={() => {
                setNewBalance(String(user.balance || 0));
                setShowEditBalance(true);
              }}
            >
              Editar saldo
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Mail className="w-4 h-4" />
              Email
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-lg font-medium truncate">{user.email || "N/A"}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Calendar className="w-4 h-4" />
              Último Login
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-lg font-medium">{formatDate(user.last_login_at)}</div>
          </CardContent>
        </Card>
      </div>

      {/* User Details */}
      <Card>
        <CardHeader>
          <CardTitle>Informações do Usuário</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <Label className="text-muted-foreground">Nome</Label>
              <p className="font-medium">{user.name || "N/A"}</p>
            </div>
            <div>
              <Label className="text-muted-foreground">Username</Label>
              <p className="font-medium">{user.username ? `@${user.username}` : "N/A"}</p>
            </div>
            <div>
              <Label className="text-muted-foreground">Email</Label>
              <p className="font-medium">{user.email || "N/A"}</p>
            </div>
            <div>
              <Label className="text-muted-foreground">Device ID</Label>
              <p className="font-mono text-sm">{user.device_id || "N/A"}</p>
            </div>
            <div>
              <Label className="text-muted-foreground">Código de Convite</Label>
              <p className="font-mono">{user.invite_code || "N/A"}</p>
            </div>
            <div>
              <Label className="text-muted-foreground">Convidado Por</Label>
              <p className="font-mono">{user.invited_by || "N/A"}</p>
            </div>
            <div>
              <Label className="text-muted-foreground">Pontos Diários</Label>
              <p className="font-medium">{formatNumber(user.daily_points)}</p>
            </div>
            <div>
              <Label className="text-muted-foreground">Impressões Monetag</Label>
              <p className="font-medium">{formatNumber(user.monetag_impressions)}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Tabs for Transactions and Points History */}
      <Tabs defaultValue="history" className="w-full">
        <TabsList className="grid w-full grid-cols-2">
          <TabsTrigger value="history" className="flex items-center gap-2">
            <History className="w-4 h-4" />
            Histórico de Pontos
          </TabsTrigger>
          <TabsTrigger value="transactions" className="flex items-center gap-2">
            <Coins className="w-4 h-4" />
            Transações
          </TabsTrigger>
        </TabsList>

        {/* Points History Tab */}
        <TabsContent value="history">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <History className="w-5 h-5" />
                Histórico de Pontos
              </CardTitle>
              <CardDescription>Registro detalhado de todas as movimentações de pontos do usuário</CardDescription>
            </CardHeader>
            <CardContent>
              {!pointsHistory || pointsHistory.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  Nenhum histórico de pontos encontrado
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>ID</TableHead>
                        <TableHead>Tipo</TableHead>
                        <TableHead>Pontos</TableHead>
                        <TableHead>Saldo Anterior</TableHead>
                        <TableHead>Saldo Após</TableHead>
                        <TableHead>Fonte</TableHead>
                        <TableHead>Descrição</TableHead>
                        <TableHead>Data</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {pointsHistory.map((item: any) => (
                        <TableRow key={item.id}>
                          <TableCell className="font-mono">#{item.id}</TableCell>
                          <TableCell>
                            <Badge 
                              variant={item.type === 'credit' || item.type === 'add' || item.points > 0 ? 'default' : 'destructive'}
                              className="flex items-center gap-1 w-fit"
                            >
                              {item.type === 'credit' || item.type === 'add' || item.points > 0 ? (
                                <TrendingUp className="w-3 h-3" />
                              ) : (
                                <TrendingDown className="w-3 h-3" />
                              )}
                              {item.type || (item.points > 0 ? 'credit' : 'debit')}
                            </Badge>
                          </TableCell>
                          <TableCell className={`font-bold ${item.type === 'credit' || item.type === 'add' || item.points > 0 ? 'text-green-600' : 'text-red-600'}`}>
                            {item.type === 'credit' || item.type === 'add' || item.points > 0 ? '+' : ''}{formatNumber(item.points || item.amount || 0)}
                          </TableCell>
                          <TableCell className="text-muted-foreground">
                            {item.balance_before !== undefined ? formatNumber(item.balance_before) : '-'}
                          </TableCell>
                          <TableCell className="font-medium">
                            {item.balance_after !== undefined ? formatNumber(item.balance_after) : '-'}
                          </TableCell>
                          <TableCell>
                            <Badge variant="outline">{item.source || item.action || 'N/A'}</Badge>
                          </TableCell>
                          <TableCell className="max-w-[200px] truncate" title={item.description || item.reason || '-'}>
                            {item.description || item.reason || '-'}
                          </TableCell>
                          <TableCell className="whitespace-nowrap">{formatDate(item.created_at)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Transactions Tab */}
        <TabsContent value="transactions">
          <Card>
            <CardHeader>
              <CardTitle>Histórico de Transações</CardTitle>
              <CardDescription>Últimas transações de pontos do usuário</CardDescription>
            </CardHeader>
            <CardContent>
              {!transactions || transactions.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  Nenhuma transação encontrada
                </div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>ID</TableHead>
                      <TableHead>Tipo</TableHead>
                      <TableHead>Pontos</TableHead>
                      <TableHead>Descrição</TableHead>
                      <TableHead>Data</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {transactions.map((tx: any) => (
                      <TableRow key={tx.id}>
                        <TableCell className="font-mono">#{tx.id}</TableCell>
                        <TableCell>
                          <Badge variant={tx.type === 'credit' ? 'default' : 'destructive'}>
                            {tx.type === 'credit' ? '+' : '-'}
                          </Badge>
                        </TableCell>
                        <TableCell className="font-medium">{formatNumber(tx.points || tx.amount)}</TableCell>
                        <TableCell>{tx.description || tx.source || "N/A"}</TableCell>
                        <TableCell>{formatDate(tx.created_at)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Edit Points Dialog */}
      <Dialog open={showEditPoints} onOpenChange={setShowEditPoints}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Editar Pontos</DialogTitle>
            <DialogDescription>
              Defina a nova quantidade de pontos para este usuário
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label>Nova quantidade de pontos</Label>
              <Input
                type="number"
                value={newPoints}
                onChange={(e) => setNewPoints(e.target.value)}
                placeholder="Ex: 1000"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowEditPoints(false)}>
              Cancelar
            </Button>
            <Button onClick={handleUpdatePoints} disabled={updatePointsMutation.isPending}>
              {updatePointsMutation.isPending ? (
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
              ) : null}
              Salvar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Edit Balance Dialog */}
      <Dialog open={showEditBalance} onOpenChange={setShowEditBalance}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Editar Saldo</DialogTitle>
            <DialogDescription>
              Defina o novo saldo para este usuário
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label>Novo saldo (R$)</Label>
              <Input
                type="number"
                step="0.01"
                value={newBalance}
                onChange={(e) => setNewBalance(e.target.value)}
                placeholder="Ex: 100.00"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowEditBalance(false)}>
              Cancelar
            </Button>
            <Button onClick={handleUpdateBalance} disabled={updateBalanceMutation.isPending}>
              {updateBalanceMutation.isPending ? (
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
              ) : null}
              Salvar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
