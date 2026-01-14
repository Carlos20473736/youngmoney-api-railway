import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from "@/components/ui/alert-dialog";
import { toast } from "sonner";
import { Shield, Users, AlertTriangle, LogOut, Activity } from "lucide-react";

export default function Security() {
  const { data: sessions, isLoading: loadingSessions, refetch: refetchSessions } = trpc.sessions.list.useQuery({ limit: 100 });
  const { data: violations, isLoading: loadingViolations } = trpc.security.violations.useQuery({ limit: 100 });
  
  const terminateSession = trpc.sessions.terminate.useMutation({
    onSuccess: () => {
      toast.success("Sessão encerrada!");
      refetchSessions();
    },
    onError: (error) => toast.error(`Erro: ${error.message}`),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Shield className="h-8 w-8 text-primary" />
        <div>
          <h1 className="text-3xl font-bold">Segurança</h1>
          <p className="text-muted-foreground">Monitore sessões ativas e violações de segurança</p>
        </div>
      </div>

      <Tabs defaultValue="sessions" className="space-y-4">
        <TabsList>
          <TabsTrigger value="sessions" className="flex items-center gap-2">
            <Users className="h-4 w-4" />
            Sessões Ativas ({sessions?.length || 0})
          </TabsTrigger>
          <TabsTrigger value="violations" className="flex items-center gap-2">
            <AlertTriangle className="h-4 w-4" />
            Violações ({violations?.length || 0})
          </TabsTrigger>
        </TabsList>

        <TabsContent value="sessions">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Activity className="h-5 w-5" />
                Sessões Ativas
              </CardTitle>
              <CardDescription>Usuários conectados ao sistema</CardDescription>
            </CardHeader>
            <CardContent>
              {loadingSessions ? (
                <div className="space-y-2">
                  {[1, 2, 3, 4, 5].map((i) => (
                    <Skeleton key={i} className="h-12 w-full" />
                  ))}
                </div>
              ) : sessions?.length === 0 ? (
                <p className="text-center text-muted-foreground py-8">Nenhuma sessão ativa</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>ID</TableHead>
                      <TableHead>Usuário</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Última Atividade</TableHead>
                      <TableHead>Ações</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {sessions?.map((session: any) => (
                      <TableRow key={session.id}>
                        <TableCell className="font-mono">{session.id}</TableCell>
                        <TableCell>{session.user_name || `Usuário #${session.user_id}`}</TableCell>
                        <TableCell>
                          <Badge variant="default" className="bg-green-500">Ativo</Badge>
                        </TableCell>
                        <TableCell>{session.last_activity ? new Date(session.last_activity).toLocaleString('pt-BR') : '-'}</TableCell>
                        <TableCell>
                          <AlertDialog>
                            <AlertDialogTrigger asChild>
                              <Button variant="destructive" size="sm">
                                <LogOut className="h-4 w-4 mr-1" />
                                Encerrar
                              </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                              <AlertDialogHeader>
                                <AlertDialogTitle>Encerrar Sessão?</AlertDialogTitle>
                                <AlertDialogDescription>
                                  O usuário será desconectado e precisará fazer login novamente.
                                </AlertDialogDescription>
                              </AlertDialogHeader>
                              <AlertDialogFooter>
                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                <AlertDialogAction onClick={() => terminateSession.mutate({ id: session.id })}>
                                  Encerrar Sessão
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
        </TabsContent>

        <TabsContent value="violations">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <AlertTriangle className="h-5 w-5 text-red-500" />
                Violações de Segurança
              </CardTitle>
              <CardDescription>Atividades suspeitas detectadas</CardDescription>
            </CardHeader>
            <CardContent>
              {loadingViolations ? (
                <div className="space-y-2">
                  {[1, 2, 3, 4, 5].map((i) => (
                    <Skeleton key={i} className="h-12 w-full" />
                  ))}
                </div>
              ) : violations?.length === 0 ? (
                <p className="text-center text-muted-foreground py-8">Nenhuma violação registrada</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>ID</TableHead>
                      <TableHead>Usuário</TableHead>
                      <TableHead>Tipo</TableHead>
                      <TableHead>Detalhes</TableHead>
                      <TableHead>Data</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {violations?.map((violation: any) => (
                      <TableRow key={violation.id}>
                        <TableCell className="font-mono">{violation.id}</TableCell>
                        <TableCell>{violation.user_name || `#${violation.user_id}`}</TableCell>
                        <TableCell>
                          <Badge variant="destructive">{violation.violation_type}</Badge>
                        </TableCell>
                        <TableCell className="max-w-xs truncate">{violation.details}</TableCell>
                        <TableCell>{new Date(violation.created_at).toLocaleString('pt-BR')}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
