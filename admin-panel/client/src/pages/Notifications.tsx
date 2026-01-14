import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { toast } from "sonner";
import { Bell, Send, Megaphone, AlertCircle, Info, CheckCircle, Coins } from "lucide-react";

export default function Notifications() {
  const { data: notifications, isLoading, refetch } = trpc.notifications.list.useQuery({ limit: 100 });
  
  const createNotification = trpc.notifications.create.useMutation({
    onSuccess: () => {
      toast.success("Notificação enviada!");
      refetch();
      setOpen(false);
      setForm({ userId: "", title: "", message: "", type: "info", points: "" });
    },
    onError: (error) => toast.error(`Erro: ${error.message}`),
  });

  const broadcastNotification = trpc.notifications.broadcast.useMutation({
    onSuccess: () => {
      toast.success("Notificação broadcast enviada para todos os usuários!");
      refetch();
      setBroadcastOpen(false);
      setBroadcastForm({ title: "", message: "", type: "info", points: "" });
    },
    onError: (error) => toast.error(`Erro: ${error.message}`),
  });

  const [open, setOpen] = useState(false);
  const [broadcastOpen, setBroadcastOpen] = useState(false);
  const [form, setForm] = useState({ userId: "", title: "", message: "", type: "info", points: "" });
  const [broadcastForm, setBroadcastForm] = useState({ title: "", message: "", type: "info", points: "" });

  const handleSend = () => {
    if (!form.userId || !form.title || !form.message) {
      toast.error("Preencha todos os campos obrigatórios");
      return;
    }
    createNotification.mutate({
      userId: parseInt(form.userId),
      title: form.title,
      message: form.message,
      type: form.type,
      points: form.points ? parseInt(form.points) : 0,
    });
  };

  const handleBroadcast = () => {
    if (!broadcastForm.title || !broadcastForm.message) {
      toast.error("Preencha todos os campos obrigatórios");
      return;
    }
    broadcastNotification.mutate({
      title: broadcastForm.title,
      message: broadcastForm.message,
      type: broadcastForm.type,
      points: broadcastForm.points ? parseInt(broadcastForm.points) : 0,
    });
  };

  const getTypeBadge = (type: string) => {
    const variants: Record<string, "default" | "secondary" | "destructive" | "outline"> = {
      'success': 'default',
      'warning': 'secondary',
      'error': 'destructive',
      'info': 'outline',
    };
    return <Badge variant={variants[type] || 'outline'}>{type}</Badge>;
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Bell className="h-8 w-8 text-primary" />
          <div>
            <h1 className="text-3xl font-bold">Notificações</h1>
            <p className="text-muted-foreground">Envie notificações e pontos para usuários</p>
          </div>
        </div>
        <div className="flex gap-2">
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
              <Button className="flex items-center gap-2">
                <Send className="h-4 w-4" />
                Enviar para Usuário
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Enviar Notificação</DialogTitle>
                <DialogDescription>Envie uma notificação para um usuário específico (com pontos opcionais)</DialogDescription>
              </DialogHeader>
              <div className="space-y-4">
                <div>
                  <Label>ID do Usuário *</Label>
                  <Input
                    type="number"
                    value={form.userId}
                    onChange={(e) => setForm({ ...form, userId: e.target.value })}
                    placeholder="Ex: 31"
                  />
                </div>
                <div>
                  <Label>Título *</Label>
                  <Input
                    value={form.title}
                    onChange={(e) => setForm({ ...form, title: e.target.value })}
                    placeholder="Título da notificação"
                  />
                </div>
                <div>
                  <Label>Mensagem *</Label>
                  <Textarea
                    value={form.message}
                    onChange={(e) => setForm({ ...form, message: e.target.value })}
                    placeholder="Conteúdo da notificação"
                  />
                </div>
                <div>
                  <Label>Tipo</Label>
                  <Select value={form.type} onValueChange={(v) => setForm({ ...form, type: v })}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="info">Info</SelectItem>
                      <SelectItem value="success">Sucesso</SelectItem>
                      <SelectItem value="warning">Aviso</SelectItem>
                      <SelectItem value="error">Erro</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label className="flex items-center gap-2">
                    <Coins className="h-4 w-4 text-yellow-500" />
                    Pontos a Adicionar (opcional)
                  </Label>
                  <Input
                    type="number"
                    value={form.points}
                    onChange={(e) => setForm({ ...form, points: e.target.value })}
                    placeholder="Ex: 100"
                    min="0"
                  />
                  <p className="text-xs text-muted-foreground mt-1">
                    Deixe em branco ou 0 para não adicionar pontos
                  </p>
                </div>
                <Button onClick={handleSend} disabled={createNotification.isPending} className="w-full">
                  {createNotification.isPending ? "Enviando..." : "Enviar Notificação"}
                </Button>
              </div>
            </DialogContent>
          </Dialog>

          <Dialog open={broadcastOpen} onOpenChange={setBroadcastOpen}>
            <DialogTrigger asChild>
              <Button variant="outline" className="flex items-center gap-2">
                <Megaphone className="h-4 w-4" />
                Broadcast
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Broadcast para Todos</DialogTitle>
                <DialogDescription>Envie uma notificação para todos os usuários (com pontos opcionais)</DialogDescription>
              </DialogHeader>
              <div className="space-y-4">
                <div>
                  <Label>Título *</Label>
                  <Input
                    value={broadcastForm.title}
                    onChange={(e) => setBroadcastForm({ ...broadcastForm, title: e.target.value })}
                    placeholder="Título da notificação"
                  />
                </div>
                <div>
                  <Label>Mensagem *</Label>
                  <Textarea
                    value={broadcastForm.message}
                    onChange={(e) => setBroadcastForm({ ...broadcastForm, message: e.target.value })}
                    placeholder="Conteúdo da notificação"
                  />
                </div>
                <div>
                  <Label>Tipo</Label>
                  <Select value={broadcastForm.type} onValueChange={(v) => setBroadcastForm({ ...broadcastForm, type: v })}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="info">Info</SelectItem>
                      <SelectItem value="success">Sucesso</SelectItem>
                      <SelectItem value="warning">Aviso</SelectItem>
                      <SelectItem value="error">Erro</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label className="flex items-center gap-2">
                    <Coins className="h-4 w-4 text-yellow-500" />
                    Pontos a Adicionar para Cada Usuário (opcional)
                  </Label>
                  <Input
                    type="number"
                    value={broadcastForm.points}
                    onChange={(e) => setBroadcastForm({ ...broadcastForm, points: e.target.value })}
                    placeholder="Ex: 50"
                    min="0"
                  />
                  <p className="text-xs text-muted-foreground mt-1">
                    Cada usuário receberá esta quantidade de pontos
                  </p>
                </div>
                <Button onClick={handleBroadcast} disabled={broadcastNotification.isPending} className="w-full">
                  {broadcastNotification.isPending ? "Enviando..." : "Enviar para Todos"}
                </Button>
              </div>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Histórico de Notificações</CardTitle>
          <CardDescription>Últimas 100 notificações enviadas</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-2">
              {[1, 2, 3, 4, 5].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : notifications?.length === 0 ? (
            <p className="text-center text-muted-foreground py-8">Nenhuma notificação enviada</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>Usuário</TableHead>
                  <TableHead>Título</TableHead>
                  <TableHead>Mensagem</TableHead>
                  <TableHead>Tipo</TableHead>
                  <TableHead>Lida</TableHead>
                  <TableHead>Data</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {notifications?.map((notif: any) => (
                  <TableRow key={notif.id}>
                    <TableCell className="font-mono">{notif.id}</TableCell>
                    <TableCell>{notif.user_name || `#${notif.user_id}`}</TableCell>
                    <TableCell className="font-medium">{notif.title}</TableCell>
                    <TableCell className="max-w-xs truncate">{notif.message}</TableCell>
                    <TableCell>{getTypeBadge(notif.type)}</TableCell>
                    <TableCell>
                      {notif.is_read ? (
                        <Badge variant="default">Lida</Badge>
                      ) : (
                        <Badge variant="secondary">Não lida</Badge>
                      )}
                    </TableCell>
                    <TableCell>{new Date(notif.created_at).toLocaleString('pt-BR')}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
