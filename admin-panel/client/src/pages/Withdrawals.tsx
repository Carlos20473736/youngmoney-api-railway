import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Check, X, Clock, Loader2, Image, Pencil } from "lucide-react";
import { toast } from "sonner";

export default function Withdrawals() {
  const [selectedTab, setSelectedTab] = useState("pending");
  const [selectedWithdrawal, setSelectedWithdrawal] = useState<any>(null);
  const [showApproveDialog, setShowApproveDialog] = useState(false);
  const [showRejectDialog, setShowRejectDialog] = useState(false);
  const [showEditReceiptDialog, setShowEditReceiptDialog] = useState(false);
  const [receiptUrl, setReceiptUrl] = useState("");

  const { data: pendingWithdrawals, refetch: refetchPending, isLoading: loadingPending } = trpc.withdrawals.list.useQuery({ status: "pending" });
  const { data: approvedWithdrawals, refetch: refetchApproved, isLoading: loadingApproved } = trpc.withdrawals.list.useQuery({ status: "approved" });
  const { data: rejectedWithdrawals, refetch: refetchRejected, isLoading: loadingRejected } = trpc.withdrawals.list.useQuery({ status: "rejected" });
  const { data: stats } = trpc.withdrawals.stats.useQuery();

  const updateStatusMutation = trpc.withdrawals.updateStatus.useMutation({
    onSuccess: () => {
      toast.success("Atualizado com sucesso");
      setShowApproveDialog(false);
      setShowRejectDialog(false);
      setShowEditReceiptDialog(false);
      setSelectedWithdrawal(null);
      setReceiptUrl("");
      refetchPending();
      refetchApproved();
      refetchRejected();
    },
    onError: (error: any) => {
      toast.error(`Erro: ${error.message}`);
    },
  });

  const handleApprove = () => {
    if (!selectedWithdrawal) return;
    updateStatusMutation.mutate({ 
      id: selectedWithdrawal.id, 
      status: "approved",
      receiptUrl: receiptUrl || undefined
    });
  };

  const handleReject = () => {
    if (!selectedWithdrawal) return;
    updateStatusMutation.mutate({ id: selectedWithdrawal.id, status: "rejected" });
  };

  const handleUpdateReceipt = () => {
    if (!selectedWithdrawal) return;
    updateStatusMutation.mutate({ 
      id: selectedWithdrawal.id, 
      status: "approved",
      receiptUrl: receiptUrl
    });
  };

  const formatCurrency = (value: number | string) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(num || 0);
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

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "pending":
        return <Badge variant="outline" className="bg-yellow-100 text-yellow-800"><Clock className="w-3 h-3 mr-1" />Pendente</Badge>;
      case "approved":
        return <Badge variant="outline" className="bg-green-100 text-green-800"><Check className="w-3 h-3 mr-1" />Aprovado</Badge>;
      case "rejected":
        return <Badge variant="outline" className="bg-red-100 text-red-800"><X className="w-3 h-3 mr-1" />Rejeitado</Badge>;
      default:
        return <Badge variant="outline">{status}</Badge>;
    }
  };

  const renderWithdrawalTable = (withdrawals: any[] | undefined, isLoading: boolean, showActions = false, showEditReceipt = false) => {
    if (isLoading) {
      return (
        <div className="flex justify-center py-8">
          <Loader2 className="w-8 h-8 animate-spin text-muted-foreground" />
        </div>
      );
    }

    if (!withdrawals || withdrawals.length === 0) {
      return (
        <div className="text-center py-8 text-muted-foreground">
          Nenhum saque encontrado
        </div>
      );
    }

    return (
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>ID</TableHead>
            <TableHead>Usuario</TableHead>
            <TableHead>Valor</TableHead>
            <TableHead>PIX</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Data</TableHead>
            <TableHead>Comprovante</TableHead>
            <TableHead>Acoes</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {withdrawals.map((withdrawal: any) => (
            <TableRow key={withdrawal.id}>
              <TableCell className="font-mono">#{withdrawal.id}</TableCell>
              <TableCell>
                <div>
                  <div className="font-medium">{withdrawal.user_name || "N/A"}</div>
                  <div className="text-sm text-muted-foreground">{withdrawal.user_email || "N/A"}</div>
                </div>
              </TableCell>
              <TableCell className="font-semibold">{formatCurrency(withdrawal.amount)}</TableCell>
              <TableCell className="font-mono text-sm">{withdrawal.pix_key || "N/A"}</TableCell>
              <TableCell>{getStatusBadge(withdrawal.status)}</TableCell>
              <TableCell>{formatDate(withdrawal.created_at)}</TableCell>
              <TableCell>
                {withdrawal.receipt_url ? (
                  <a 
                    href={withdrawal.receipt_url} 
                    target="_blank" 
                    rel="noopener noreferrer"
                    className="flex items-center gap-1 text-blue-600 hover:text-blue-800"
                  >
                    <Image className="w-4 h-4" />
                    Ver
                  </a>
                ) : (
                  <span className="text-muted-foreground text-sm">-</span>
                )}
              </TableCell>
              <TableCell>
                <div className="flex gap-2">
                  {showActions && (
                    <>
                      <Button
                        size="sm"
                        variant="outline"
                        className="text-green-600 hover:bg-green-50"
                        onClick={() => {
                          setSelectedWithdrawal(withdrawal);
                          setReceiptUrl("");
                          setShowApproveDialog(true);
                        }}
                      >
                        <Check className="w-4 h-4 mr-1" />
                        Aprovar
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        className="text-red-600 hover:bg-red-50"
                        onClick={() => {
                          setSelectedWithdrawal(withdrawal);
                          setShowRejectDialog(true);
                        }}
                      >
                        <X className="w-4 h-4 mr-1" />
                        Rejeitar
                      </Button>
                    </>
                  )}
                  {showEditReceipt && (
                    <Button
                      size="sm"
                      variant="outline"
                      className="text-blue-600 hover:bg-blue-50"
                      onClick={() => {
                        setSelectedWithdrawal(withdrawal);
                        setReceiptUrl(withdrawal.receipt_url || "");
                        setShowEditReceiptDialog(true);
                      }}
                    >
                      <Pencil className="w-4 h-4 mr-1" />
                      Editar URL
                    </Button>
                  )}
                </div>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    );
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Gerenciamento de Saques</h1>
        <p className="text-muted-foreground">Aprove ou rejeite solicitacoes de saque via PIX</p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Pendentes</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <Clock className="w-5 h-5 text-yellow-500" />
              <span className="text-2xl font-bold">{stats?.pending?.count || 0}</span>
            </div>
            <p className="text-sm text-muted-foreground mt-1">
              Total: {formatCurrency(stats?.pending?.total || 0)}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Aprovados</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <Check className="w-5 h-5 text-green-500" />
              <span className="text-2xl font-bold">{stats?.approved?.count || 0}</span>
            </div>
            <p className="text-sm text-muted-foreground mt-1">
              Total: {formatCurrency(stats?.approved?.total || 0)}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Rejeitados</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <X className="w-5 h-5 text-red-500" />
              <span className="text-2xl font-bold">{stats?.rejected?.count || 0}</span>
            </div>
            <p className="text-sm text-muted-foreground mt-1">
              Total: {formatCurrency(stats?.rejected?.total || 0)}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Card>
        <CardHeader>
          <CardTitle>Solicitacoes de Saque</CardTitle>
          <CardDescription>Gerencie as solicitacoes de saque dos usuarios</CardDescription>
        </CardHeader>
        <CardContent>
          <Tabs value={selectedTab} onValueChange={setSelectedTab}>
            <TabsList>
              <TabsTrigger value="pending">
                Pendentes ({pendingWithdrawals?.length || 0})
              </TabsTrigger>
              <TabsTrigger value="approved">
                Aprovados ({approvedWithdrawals?.length || 0})
              </TabsTrigger>
              <TabsTrigger value="rejected">
                Rejeitados ({rejectedWithdrawals?.length || 0})
              </TabsTrigger>
            </TabsList>

            <TabsContent value="pending" className="mt-4">
              {renderWithdrawalTable(pendingWithdrawals, loadingPending, true, false)}
            </TabsContent>

            <TabsContent value="approved" className="mt-4">
              {renderWithdrawalTable(approvedWithdrawals, loadingApproved, false, true)}
            </TabsContent>

            <TabsContent value="rejected" className="mt-4">
              {renderWithdrawalTable(rejectedWithdrawals, loadingRejected, false, false)}
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>

      {/* Approve Dialog */}
      <Dialog open={showApproveDialog} onOpenChange={setShowApproveDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Aprovar Saque</DialogTitle>
            <DialogDescription>
              Confirme a aprovacao do saque e adicione o comprovante PIX.
            </DialogDescription>
          </DialogHeader>
          
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="receiptUrl">URL do Comprovante PIX (link direto da imagem)</Label>
              <Input
                id="receiptUrl"
                placeholder="https://i.ibb.co/xxxxx/comprovante.png"
                value={receiptUrl}
                onChange={(e) => setReceiptUrl(e.target.value)}
              />
              <p className="text-sm text-muted-foreground">
                Use o link direto da imagem (termina em .png ou .jpg). No ImgBB, copie o "Direct link".
              </p>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setShowApproveDialog(false)}>
              Cancelar
            </Button>
            <Button 
              onClick={handleApprove} 
              disabled={updateStatusMutation.isPending}
              className="bg-green-600 hover:bg-green-700"
            >
              {updateStatusMutation.isPending ? (
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
              ) : (
                <Check className="w-4 h-4 mr-2" />
              )}
              Aprovar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Edit Receipt Dialog */}
      <Dialog open={showEditReceiptDialog} onOpenChange={setShowEditReceiptDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Editar URL do Comprovante</DialogTitle>
            <DialogDescription>
              Atualize a URL do comprovante PIX para o saque #{selectedWithdrawal?.id}
            </DialogDescription>
          </DialogHeader>
          
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="editReceiptUrl">URL do Comprovante PIX</Label>
              <Input
                id="editReceiptUrl"
                placeholder="https://i.ibb.co/xxxxx/comprovante.png"
                value={receiptUrl}
                onChange={(e) => setReceiptUrl(e.target.value)}
              />
              <p className="text-sm text-muted-foreground">
                Use o link direto da imagem (termina em .png ou .jpg). No ImgBB, copie o "Direct link".
              </p>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setShowEditReceiptDialog(false)}>
              Cancelar
            </Button>
            <Button 
              onClick={handleUpdateReceipt} 
              disabled={updateStatusMutation.isPending}
              className="bg-blue-600 hover:bg-blue-700"
            >
              {updateStatusMutation.isPending ? (
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
              ) : (
                <Pencil className="w-4 h-4 mr-2" />
              )}
              Salvar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Reject Dialog */}
      <Dialog open={showRejectDialog} onOpenChange={setShowRejectDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Rejeitar Saque</DialogTitle>
            <DialogDescription>
              Tem certeza que deseja rejeitar este saque? Esta acao nao pode ser desfeita.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowRejectDialog(false)}>
              Cancelar
            </Button>
            <Button 
              onClick={handleReject} 
              disabled={updateStatusMutation.isPending}
              variant="destructive"
            >
              {updateStatusMutation.isPending ? (
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
              ) : (
                <X className="w-4 h-4 mr-2" />
              )}
              Rejeitar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
