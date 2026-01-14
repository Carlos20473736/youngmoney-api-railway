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
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from "@/components/ui/alert-dialog";
import { toast } from "sonner";
import { ShieldBan, Plus, Trash2, Smartphone } from "lucide-react";

export default function Blacklist() {
  const { data: devices, isLoading, refetch } = trpc.blacklist.list.useQuery({ limit: 100 });
  
  const addToBlacklist = trpc.blacklist.add.useMutation({
    onSuccess: () => {
      toast.success("Dispositivo adicionado à blacklist!");
      refetch();
      setOpen(false);
      setForm({ deviceId: "", reason: "" });
    },
    onError: (error) => toast.error(`Erro: ${error.message}`),
  });

  const removeFromBlacklist = trpc.blacklist.remove.useMutation({
    onSuccess: () => {
      toast.success("Dispositivo removido da blacklist!");
      refetch();
    },
    onError: (error) => toast.error(`Erro: ${error.message}`),
  });

  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ deviceId: "", reason: "" });

  const handleAdd = () => {
    if (!form.deviceId || !form.reason) {
      toast.error("Preencha todos os campos");
      return;
    }
    addToBlacklist.mutate(form);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <ShieldBan className="h-8 w-8 text-red-500" />
          <div>
            <h1 className="text-3xl font-bold">Blacklist de Dispositivos</h1>
            <p className="text-muted-foreground">Gerencie dispositivos bloqueados</p>
          </div>
        </div>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild>
            <Button className="flex items-center gap-2">
              <Plus className="h-4 w-4" />
              Adicionar Dispositivo
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Bloquear Dispositivo</DialogTitle>
              <DialogDescription>Adicione um dispositivo à blacklist</DialogDescription>
            </DialogHeader>
            <div className="space-y-4">
              <div>
                <Label>ID do Dispositivo</Label>
                <Input
                  value={form.deviceId}
                  onChange={(e) => setForm({ ...form, deviceId: e.target.value })}
                  placeholder="Ex: abc123def456"
                />
              </div>
              <div>
                <Label>Motivo do Bloqueio</Label>
                <Textarea
                  value={form.reason}
                  onChange={(e) => setForm({ ...form, reason: e.target.value })}
                  placeholder="Descreva o motivo do bloqueio"
                />
              </div>
              <Button onClick={handleAdd} disabled={addToBlacklist.isPending} className="w-full">
                {addToBlacklist.isPending ? "Adicionando..." : "Bloquear Dispositivo"}
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Smartphone className="h-5 w-5" />
            Dispositivos Bloqueados
          </CardTitle>
          <CardDescription>Total: {devices?.length || 0} dispositivos</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-2">
              {[1, 2, 3, 4, 5].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : devices?.length === 0 ? (
            <p className="text-center text-muted-foreground py-8">Nenhum dispositivo bloqueado</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>Device ID</TableHead>
                  <TableHead>Motivo</TableHead>
                  <TableHead>Data do Bloqueio</TableHead>
                  <TableHead>Ações</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {devices?.map((device: any) => (
                  <TableRow key={device.id}>
                    <TableCell className="font-mono">{device.id}</TableCell>
                    <TableCell className="font-mono text-sm">{device.device_id}</TableCell>
                    <TableCell className="max-w-xs truncate">{device.reason}</TableCell>
                    <TableCell>{new Date(device.created_at).toLocaleString('pt-BR')}</TableCell>
                    <TableCell>
                      <AlertDialog>
                        <AlertDialogTrigger asChild>
                          <Button variant="destructive" size="sm">
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                          <AlertDialogHeader>
                            <AlertDialogTitle>Remover da Blacklist?</AlertDialogTitle>
                            <AlertDialogDescription>
                              O dispositivo {device.device_id} será desbloqueado e poderá acessar o app novamente.
                            </AlertDialogDescription>
                          </AlertDialogHeader>
                          <AlertDialogFooter>
                            <AlertDialogCancel>Cancelar</AlertDialogCancel>
                            <AlertDialogAction onClick={() => removeFromBlacklist.mutate({ id: device.id })}>
                              Desbloquear
                            </AlertDialogAction>
                          </AlertDialogFooter>
                        </AlertDialogContent>
                      </AlertDialog>
                    </TableCell>
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
