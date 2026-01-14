import { useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { ScrollText, Loader2 } from "lucide-react";

export default function AuditLogs() {
  const [isLoading] = useState(false);
  
  // Logs de auditoria serão implementados quando houver tabela no banco
  const logs: any[] = [];

  const getActionBadge = (action: string) => {
    const actionMap: Record<string, { variant: "default" | "destructive" | "outline" | "secondary", color: string }> = {
      create_user: { variant: "default", color: "text-blue-600" },
      update_user: { variant: "outline", color: "text-purple-600" },
      ban_user: { variant: "destructive", color: "text-red-600" },
      unban_user: { variant: "default", color: "text-green-600" },
      add_points: { variant: "default", color: "text-green-600" },
      remove_points: { variant: "destructive", color: "text-red-600" },
      withdrawal_approved: { variant: "default", color: "text-green-600" },
      withdrawal_rejected: { variant: "destructive", color: "text-red-600" },
      withdrawal_completed: { variant: "secondary", color: "text-blue-600" },
      update_setting: { variant: "outline", color: "text-yellow-600" },
    };

    const config = actionMap[action] || { variant: "outline" as const, color: "text-gray-600" };
    return <Badge variant={config.variant} className={config.color}>{action.replace(/_/g, " ")}</Badge>;
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

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Logs de Auditoria</h1>
        <p className="text-muted-foreground mt-2">Histórico de ações administrativas</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ScrollText className="h-5 w-5" />
            Atividades Recentes
          </CardTitle>
          <CardDescription>Registro de todas as ações realizadas no painel</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center py-8">
              <Loader2 className="w-8 h-8 animate-spin text-muted-foreground" />
            </div>
          ) : logs.length === 0 ? (
            <div className="text-center py-12 text-muted-foreground">
              <ScrollText className="w-12 h-12 mx-auto mb-4 opacity-50" />
              <p className="text-lg font-medium">Nenhum log de auditoria encontrado</p>
              <p className="text-sm mt-2">As ações administrativas serão registradas aqui</p>
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>Ação</TableHead>
                  <TableHead>Tipo</TableHead>
                  <TableHead>Alvo</TableHead>
                  <TableHead>Detalhes</TableHead>
                  <TableHead>Data</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {logs.map((log: any) => (
                  <TableRow key={log.id}>
                    <TableCell className="font-mono">#{log.id}</TableCell>
                    <TableCell>{getActionBadge(log.action)}</TableCell>
                    <TableCell>{log.target_type || "-"}</TableCell>
                    <TableCell className="font-mono">{log.target_id || "-"}</TableCell>
                    <TableCell className="max-w-xs truncate">{log.details || "-"}</TableCell>
                    <TableCell>{formatDate(log.created_at)}</TableCell>
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
