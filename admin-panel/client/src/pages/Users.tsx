import { useState, useEffect } from "react";
import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Eye, Search, Loader2, Users as UsersIcon, Coins, Wallet, Trash2, AlertTriangle, Mail, AtSign, Wifi, Clock, Activity } from "lucide-react";
import { toast } from "sonner";
import { useLocation } from "wouter";

export default function Users() {
  const [, setLocation] = useLocation();
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedUser, setSelectedUser] = useState<any>(null);
  const [showEditDialog, setShowEditDialog] = useState(false);
  const [showDeleteAllDialog, setShowDeleteAllDialog] = useState(false);
  const [deleteConfirmText, setDeleteConfirmText] = useState("");
  const [newPoints, setNewPoints] = useState("");
  const [newBalance, setNewBalance] = useState("");

  // Buscar TODOS os usuários (limite alto para pegar todos)
  const { data, isLoading, refetch } = trpc.appUsers.list.useQuery({ limit: 10000, offset: 0 });

  // Buscar estatísticas de usuários online (atualiza a cada 30 segundos)
  const { data: onlineStats, refetch: refetchOnline } = trpc.appUsers.onlineStats.useQuery(
    { minutesThreshold: 5 },
    { refetchInterval: 30000 } // Atualiza a cada 30 segundos
  );

  // Atualizar contagem online periodicamente
  useEffect(() => {
    const interval = setInterval(() => {
      refetchOnline();
    }, 30000);
    return () => clearInterval(interval);
  }, [refetchOnline]);

  const updatePointsMutation = trpc.appUsers.updatePoints.useMutation({
    onSuccess: () => {
      toast.success("Pontos atualizados com sucesso");
      setShowEditDialog(false);
      refetch();
    },
    onError: (error: any) => {
      toast.error(`Erro: ${error.message}`);
    },
  });

  const updateBalanceMutation = trpc.appUsers.updateBalance.useMutation({
    onSuccess: () => {
      toast.success("Saldo atualizado com sucesso");
      setShowEditDialog(false);
      refetch();
    },
    onError: (error: any) => {
      toast.error(`Erro: ${error.message}`);
    },
  });

  const deleteAllMutation = trpc.appUsers.deleteAll.useMutation({
    onSuccess: (data) => {
      toast.success(`${data.count} usuarios excluidos com sucesso`);
      setShowDeleteAllDialog(false);
      setDeleteConfirmText("");
      refetch();
    },
    onError: (error: any) => {
      toast.error(`Erro: ${error.message}`);
    },
  });

  const handleUpdatePoints = () => {
    if (!selectedUser || !newPoints) return;
    updatePointsMutation.mutate({ id: selectedUser.id, points: parseInt(newPoints) });
  };

  const handleUpdateBalance = () => {
    if (!selectedUser || !newBalance) return;
    updateBalanceMutation.mutate({ id: selectedUser.id, balance: parseFloat(newBalance) });
  };

  const handleDeleteAll = () => {
    if (deleteConfirmText !== "EXCLUIR TODOS") {
      toast.error("Digite 'EXCLUIR TODOS' para confirmar");
      return;
    }
    deleteAllMutation.mutate({ confirmText: deleteConfirmText });
  };

  // Filtro melhorado - busca por email e username (case insensitive)
  const filteredUsers = data?.users?.filter((user: any) => {
    if (!searchTerm.trim()) return true;
    
    const search = searchTerm.toLowerCase().trim();
    const email = (user.email || "").toLowerCase();
    const username = (user.username || "").toLowerCase();
    const name = (user.name || "").toLowerCase();
    const id = String(user.id);
    
    return email.includes(search) || 
           username.includes(search) || 
           name.includes(search) || 
           id.includes(search);
  }) || [];

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

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Gerenciamento de Usuarios</h1>
          <p className="text-muted-foreground">Visualize e gerencie os usuarios do aplicativo</p>
        </div>
        <Button 
          variant="destructive" 
          onClick={() => setShowDeleteAllDialog(true)}
          className="bg-red-600 hover:bg-red-700"
        >
          <Trash2 className="w-4 h-4 mr-2" />
          Excluir Todos
        </Button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Card de Usuários Online - NOVO */}
        <Card className="border-green-200 bg-gradient-to-br from-green-50 to-emerald-50">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-green-700 flex items-center gap-2">
              <div className="relative">
                <Wifi className="w-4 h-4" />
                <span className="absolute -top-1 -right-1 w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
              </div>
              Usuarios Online
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <span className="text-3xl font-bold text-green-600">
                {formatNumber(onlineStats?.onlineNow || 0)}
              </span>
              <Badge variant="outline" className="bg-green-100 text-green-700 text-xs">
                Agora
              </Badge>
            </div>
            <div className="mt-2 space-y-1">
              <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span className="flex items-center gap-1">
                  <Clock className="w-3 h-3" />
                  Ultima hora:
                </span>
                <span className="font-medium text-green-600">{formatNumber(onlineStats?.activeLastHour || 0)}</span>
              </div>
              <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span className="flex items-center gap-1">
                  <Activity className="w-3 h-3" />
                  Ativos hoje:
                </span>
                <span className="font-medium text-green-600">{formatNumber(onlineStats?.activeToday || 0)}</span>
              </div>
            </div>
            <p className="text-xs text-muted-foreground mt-2">
              Atualiza a cada 30s
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total de Usuarios</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <UsersIcon className="w-5 h-5 text-blue-500" />
              <span className="text-2xl font-bold">{formatNumber(data?.total || 0)}</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total de Pontos</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <Coins className="w-5 h-5 text-yellow-500" />
              <span className="text-2xl font-bold">
                {formatNumber(data?.users?.reduce((acc: number, u: any) => acc + (parseInt(u.points) || 0), 0) || 0)}
              </span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total em Saldo</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <Wallet className="w-5 h-5 text-green-500" />
              <span className="text-2xl font-bold">
                {formatCurrency(data?.users?.reduce((acc: number, u: any) => acc + (parseFloat(u.balance) || 0), 0) || 0)}
              </span>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Users Table */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>Usuarios</CardTitle>
              <CardDescription>
                Lista de todos os usuarios cadastrados 
                {searchTerm && ` - Mostrando ${filteredUsers.length} de ${data?.users?.length || 0}`}
              </CardDescription>
            </div>
            <div className="relative w-80">
              <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Buscar por email, username ou nome..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-8"
              />
            </div>
          </div>
          {searchTerm && (
            <div className="flex items-center gap-2 mt-2 text-sm text-muted-foreground">
              <Mail className="w-4 h-4" />
              <span>Buscando por: <strong className="text-foreground">{searchTerm}</strong></span>
              <Button 
                variant="ghost" 
                size="sm" 
                onClick={() => setSearchTerm("")}
                className="h-6 px-2"
              >
                Limpar
              </Button>
            </div>
          )}
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center py-8">
              <Loader2 className="w-8 h-8 animate-spin text-muted-foreground" />
            </div>
          ) : filteredUsers.length === 0 ? (
            <div className="text-center py-8 text-muted-foreground">
              {searchTerm ? (
                <div className="space-y-2">
                  <p>Nenhum usuario encontrado com "{searchTerm}"</p>
                  <p className="text-sm">Tente buscar por email completo ou parte do username</p>
                </div>
              ) : (
                "Nenhum usuario encontrado"
              )}
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>ID</TableHead>
                    <TableHead>Nome</TableHead>
                    <TableHead>
                      <div className="flex items-center gap-1">
                        <Mail className="w-4 h-4" />
                        Email
                      </div>
                    </TableHead>
                    <TableHead>
                      <div className="flex items-center gap-1">
                        <AtSign className="w-4 h-4" />
                        Username
                      </div>
                    </TableHead>
                    <TableHead>Pontos</TableHead>
                    <TableHead>Saldo</TableHead>
                    <TableHead>Codigo Convite</TableHead>
                    <TableHead>Acoes</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredUsers.map((user: any) => (
                    <TableRow key={user.id} className="hover:bg-muted/50">
                      <TableCell className="font-mono">#{user.id}</TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          {user.photo_url || user.profile_picture ? (
                            <img
                              src={user.photo_url || user.profile_picture}
                              alt={user.name}
                              className="w-8 h-8 rounded-full"
                            />
                          ) : (
                            <div className="w-8 h-8 rounded-full bg-muted flex items-center justify-center">
                              <UsersIcon className="w-4 h-4" />
                            </div>
                          )}
                          <div className="font-medium">{user.name || "N/A"}</div>
                        </div>
                      </TableCell>
                      <TableCell>
                        <span className={searchTerm && user.email?.toLowerCase().includes(searchTerm.toLowerCase()) ? "bg-yellow-100 px-1 rounded" : ""}>
                          {user.email || "N/A"}
                        </span>
                      </TableCell>
                      <TableCell>
                        <span className={searchTerm && user.username?.toLowerCase().includes(searchTerm.toLowerCase()) ? "bg-yellow-100 px-1 rounded" : ""}>
                          {user.username ? `@${user.username}` : "N/A"}
                        </span>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className="bg-yellow-50 text-yellow-700">
                          <Coins className="w-3 h-3 mr-1" />
                          {formatNumber(user.points)}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className="bg-green-50 text-green-700">
                          {formatCurrency(user.balance)}
                        </Badge>
                      </TableCell>
                      <TableCell className="font-mono text-sm">{user.invite_code || "N/A"}</TableCell>
                      <TableCell>
                        <div className="flex gap-2">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setLocation(`/users/${user.id}`)}
                          >
                            <Eye className="w-4 h-4 mr-1" />
                            Ver Detalhes
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                              setSelectedUser(user);
                              setNewPoints(String(user.points || 0));
                              setNewBalance(String(user.balance || 0));
                              setShowEditDialog(true);
                            }}
                          >
                            Editar
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
          {!isLoading && filteredUsers.length > 0 && (
            <div className="mt-4 text-sm text-muted-foreground text-center">
              Mostrando {filteredUsers.length} usuario(s) {searchTerm ? `de ${data?.users?.length || 0} total` : ""}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Edit User Dialog */}
      <Dialog open={showEditDialog} onOpenChange={setShowEditDialog}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Editar Usuario</DialogTitle>
            <DialogDescription>
              {selectedUser?.name || "Usuario"} - ID #{selectedUser?.id}
              {selectedUser?.email && (
                <div className="mt-1 text-xs">
                  <Mail className="w-3 h-3 inline mr-1" />
                  {selectedUser.email}
                </div>
              )}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label>Pontos</Label>
              <div className="flex gap-2">
                <Input
                  type="number"
                  value={newPoints}
                  onChange={(e) => setNewPoints(e.target.value)}
                  placeholder="Quantidade de pontos"
                />
                <Button 
                  onClick={handleUpdatePoints}
                  disabled={updatePointsMutation.isPending}
                >
                  {updatePointsMutation.isPending ? (
                    <Loader2 className="w-4 h-4 animate-spin" />
                  ) : (
                    "Salvar"
                  )}
                </Button>
              </div>
            </div>
            <div className="space-y-2">
              <Label>Saldo (R$)</Label>
              <div className="flex gap-2">
                <Input
                  type="number"
                  step="0.01"
                  value={newBalance}
                  onChange={(e) => setNewBalance(e.target.value)}
                  placeholder="Valor do saldo"
                />
                <Button 
                  onClick={handleUpdateBalance}
                  disabled={updateBalanceMutation.isPending}
                >
                  {updateBalanceMutation.isPending ? (
                    <Loader2 className="w-4 h-4 animate-spin" />
                  ) : (
                    "Salvar"
                  )}
                </Button>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowEditDialog(false)}>
              Fechar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete All Users Dialog */}
      <Dialog open={showDeleteAllDialog} onOpenChange={setShowDeleteAllDialog}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-red-600">
              <AlertTriangle className="w-5 h-5" />
              Excluir Todos os Usuarios
            </DialogTitle>
            <DialogDescription>
              Esta acao ira excluir TODOS os usuarios do sistema, incluindo seus saques, transacoes e dados relacionados. Esta acao NAO pode ser desfeita!
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
              <p className="text-sm text-red-800">
                <strong>Atencao:</strong> Serao excluidos {data?.total || 0} usuarios e todos os seus dados relacionados.
              </p>
            </div>
            <div className="space-y-2">
              <Label>Digite <strong>EXCLUIR TODOS</strong> para confirmar:</Label>
              <Input
                value={deleteConfirmText}
                onChange={(e) => setDeleteConfirmText(e.target.value)}
                placeholder="EXCLUIR TODOS"
                className="font-mono"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => {
              setShowDeleteAllDialog(false);
              setDeleteConfirmText("");
            }}>
              Cancelar
            </Button>
            <Button 
              variant="destructive"
              onClick={handleDeleteAll}
              disabled={deleteAllMutation.isPending || deleteConfirmText !== "EXCLUIR TODOS"}
            >
              {deleteAllMutation.isPending ? (
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
              ) : (
                <Trash2 className="w-4 h-4 mr-2" />
              )}
              Excluir Todos
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
