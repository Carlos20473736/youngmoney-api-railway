import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "sonner";
import { Settings, Save, Clock, DollarSign, Gamepad2 } from "lucide-react";

export default function SystemSettings() {
  const { data: settings, isLoading, refetch } = trpc.settings.list.useQuery();
  const updateSetting = trpc.settings.update.useMutation({
    onSuccess: () => {
      toast.success("Configuração atualizada com sucesso!");
      refetch();
    },
    onError: (error) => {
      toast.error(`Erro ao atualizar: ${error.message}`);
    },
  });

  const [editValues, setEditValues] = useState<Record<string, string>>({});

  const handleSave = (key: string, value: string) => {
    updateSetting.mutate({ key, value });
  };

  const getSettingIcon = (key: string) => {
    if (key.includes('time') || key.includes('reset')) return <Clock className="h-4 w-4" />;
    if (key.includes('withdraw') || key.includes('min') || key.includes('max')) return <DollarSign className="h-4 w-4" />;
    if (key.includes('spin')) return <Gamepad2 className="h-4 w-4" />;
    return <Settings className="h-4 w-4" />;
  };

  const getSettingDescription = (key: string) => {
    const descriptions: Record<string, string> = {
      'reset_time': 'Horário de reset diário do sistema',
      'quick_withdraw_values': 'Valores rápidos para saque (JSON array)',
      'max_daily_spins': 'Número máximo de giros diários na roleta',
      'min_withdrawal': 'Valor mínimo para saque (R$)',
      'max_withdrawal': 'Valor máximo para saque (R$)',
      'last_reset_datetime': 'Data/hora do último reset do sistema',
    };
    return descriptions[key] || 'Configuração do sistema';
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-3">
          <Settings className="h-8 w-8 text-primary" />
          <div>
            <h1 className="text-3xl font-bold">Configurações do Sistema</h1>
            <p className="text-muted-foreground">Gerencie as configurações globais do aplicativo</p>
          </div>
        </div>
        <div className="grid gap-4 md:grid-cols-2">
          {[1, 2, 3, 4].map((i) => (
            <Card key={i}>
              <CardHeader>
                <Skeleton className="h-5 w-32" />
                <Skeleton className="h-4 w-48" />
              </CardHeader>
              <CardContent>
                <Skeleton className="h-10 w-full" />
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Settings className="h-8 w-8 text-primary" />
        <div>
          <h1 className="text-3xl font-bold">Configurações do Sistema</h1>
          <p className="text-muted-foreground">Gerencie as configurações globais do aplicativo</p>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        {settings?.map((setting: any) => (
          <Card key={setting.id}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-lg">
                {getSettingIcon(setting.setting_key)}
                {setting.setting_key}
              </CardTitle>
              <CardDescription>{getSettingDescription(setting.setting_key)}</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="flex gap-2">
                <Input
                  value={editValues[setting.setting_key] ?? setting.setting_value}
                  onChange={(e) => setEditValues({ ...editValues, [setting.setting_key]: e.target.value })}
                  className="flex-1"
                />
                <Button
                  size="icon"
                  onClick={() => handleSave(setting.setting_key, editValues[setting.setting_key] ?? setting.setting_value)}
                  disabled={updateSetting.isPending}
                >
                  <Save className="h-4 w-4" />
                </Button>
              </div>
              <p className="text-xs text-muted-foreground mt-2">
                Atualizado: {new Date(setting.updated_at).toLocaleString('pt-BR')}
              </p>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
