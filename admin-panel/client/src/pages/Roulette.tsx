import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { toast } from "sonner";
import { Dices, Settings, History, Save, Trophy } from "lucide-react";

export default function Roulette() {
  const { data: settings, isLoading: loadingSettings, refetch: refetchSettings } = trpc.roulette.settings.useQuery();
  const { data: spinHistory, isLoading: loadingHistory } = trpc.roulette.spinHistory.useQuery({ limit: 50 });
  
  const updateSetting = trpc.roulette.updateSetting.useMutation({
    onSuccess: () => {
      toast.success("Configuração atualizada!");
      refetchSettings();
    },
    onError: (error) => toast.error(`Erro: ${error.message}`),
  });

  const [editValues, setEditValues] = useState<Record<string, string>>({});

  const handleSave = (key: string, value: string) => {
    updateSetting.mutate({ key, value });
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Dices className="h-8 w-8 text-primary" />
        <div>
          <h1 className="text-3xl font-bold">Roleta</h1>
          <p className="text-muted-foreground">Configure prêmios e veja histórico de giros</p>
        </div>
      </div>

      <Tabs defaultValue="settings" className="space-y-4">
        <TabsList>
          <TabsTrigger value="settings" className="flex items-center gap-2">
            <Settings className="h-4 w-4" />
            Configurações
          </TabsTrigger>
          <TabsTrigger value="history" className="flex items-center gap-2">
            <History className="h-4 w-4" />
            Histórico de Giros
          </TabsTrigger>
        </TabsList>

        <TabsContent value="settings" className="space-y-4">
          {loadingSettings ? (
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
                <Card key={i}>
                  <CardHeader className="pb-2">
                    <Skeleton className="h-5 w-24" />
                  </CardHeader>
                  <CardContent>
                    <Skeleton className="h-10 w-full" />
                  </CardContent>
                </Card>
              ))}
            </div>
          ) : (
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              {settings?.map((setting: any) => (
                <Card key={setting.id}>
                  <CardHeader className="pb-2">
                    <CardTitle className="flex items-center gap-2 text-sm">
                      {setting.setting_key.includes('prize') ? (
                        <Trophy className="h-4 w-4 text-yellow-500" />
                      ) : (
                        <Settings className="h-4 w-4" />
                      )}
                      {setting.setting_key}
                    </CardTitle>
                    <CardDescription className="text-xs">{setting.description}</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="flex gap-2">
                      <Input
                        value={editValues[setting.setting_key] ?? setting.setting_value}
                        onChange={(e) => setEditValues({ ...editValues, [setting.setting_key]: e.target.value })}
                        className="flex-1"
                        type="number"
                      />
                      <Button
                        size="icon"
                        onClick={() => handleSave(setting.setting_key, editValues[setting.setting_key] ?? setting.setting_value)}
                        disabled={updateSetting.isPending}
                      >
                        <Save className="h-4 w-4" />
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </TabsContent>

        <TabsContent value="history">
          <Card>
            <CardHeader>
              <CardTitle>Histórico de Giros</CardTitle>
              <CardDescription>Últimos 50 giros realizados</CardDescription>
            </CardHeader>
            <CardContent>
              {loadingHistory ? (
                <div className="space-y-2">
                  {[1, 2, 3, 4, 5].map((i) => (
                    <Skeleton key={i} className="h-12 w-full" />
                  ))}
                </div>
              ) : spinHistory?.length === 0 ? (
                <p className="text-center text-muted-foreground py-8">Nenhum giro registrado</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>ID</TableHead>
                      <TableHead>Usuário</TableHead>
                      <TableHead>Tipo de Prêmio</TableHead>
                      <TableHead>Valor</TableHead>
                      <TableHead>Data</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {spinHistory?.map((spin: any) => (
                      <TableRow key={spin.id}>
                        <TableCell className="font-mono">{spin.id}</TableCell>
                        <TableCell>{spin.user_name || `Usuário #${spin.user_id}`}</TableCell>
                        <TableCell>{spin.prize_type}</TableCell>
                        <TableCell className="font-semibold text-green-600">
                          {Number(spin.prize_value).toLocaleString('pt-BR')} pts
                        </TableCell>
                        <TableCell>{new Date(spin.created_at).toLocaleString('pt-BR')}</TableCell>
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
