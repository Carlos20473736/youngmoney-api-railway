import { useState, useEffect } from "react";
import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { toast } from "sonner";
import { Shield, Smartphone, Globe, Store, AlertTriangle, CheckCircle2 } from "lucide-react";

export default function AllowedInstallers() {
  const { data: settings, isLoading: settingsLoading, refetch: refetchSettings } = trpc.settings.list.useQuery();
  
  const [allowAnyInstaller, setAllowAnyInstaller] = useState(false);
  const [isUpdating, setIsUpdating] = useState(false);

  const updateSetting = trpc.settings.update.useMutation({
    onSuccess: () => {
      toast.success("Configuração atualizada com sucesso!");
      refetchSettings();
      setIsUpdating(false);
    },
    onError: (error) => {
      toast.error(`Erro: ${error.message}`);
      setIsUpdating(false);
    },
  });

  // Sincronizar estado local com dados do servidor
  useEffect(() => {
    if (settings) {
      const allowAnySetting = settings.find((s: any) => s.setting_key === 'allow_any_installer');
      if (allowAnySetting) {
        const value = allowAnySetting.setting_value?.toLowerCase();
        setAllowAnyInstaller(value === 'true' || value === '1' || value === 'yes' || value === 'on');
      }
    }
  }, [settings]);

  const handleToggleAllowAny = () => {
    const newValue = !allowAnyInstaller;
    setAllowAnyInstaller(newValue);
    setIsUpdating(true);
    
    updateSetting.mutate({
      key: 'allow_any_installer',
      value: newValue ? 'true' : 'false',
    });
  };

  if (settingsLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-12 w-64" />
        <Skeleton className="h-48 w-full" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Smartphone className="h-8 w-8 text-primary" />
        <div>
          <h1 className="text-3xl font-bold">Controle de Instalação</h1>
          <p className="text-muted-foreground">Configure de onde o app pode ser instalado</p>
        </div>
      </div>

      {/* Card principal com toggle */}
      <Card className={`border-2 ${allowAnyInstaller ? 'border-yellow-500 bg-yellow-500/5' : 'border-green-500 bg-green-500/5'}`}>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              {allowAnyInstaller ? (
                <Globe className="h-8 w-8 text-yellow-500" />
              ) : (
                <Store className="h-8 w-8 text-green-500" />
              )}
              <div>
                <CardTitle className="text-xl">
                  {allowAnyInstaller ? 'Instalação de Qualquer Lugar' : 'Apenas Play Store'}
                </CardTitle>
                <CardDescription>
                  {allowAnyInstaller 
                    ? 'O app pode ser instalado de qualquer fonte (APK, lojas alternativas, etc)'
                    : 'O app só funciona se instalado pela Google Play Store'}
                </CardDescription>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <span className={`text-sm font-medium ${allowAnyInstaller ? 'text-yellow-600' : 'text-muted-foreground'}`}>
                {allowAnyInstaller ? 'ATIVADO' : 'DESATIVADO'}
              </span>
              <Switch
                checked={allowAnyInstaller}
                onCheckedChange={handleToggleAllowAny}
                disabled={isUpdating}
                className="data-[state=checked]:bg-yellow-500"
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {allowAnyInstaller ? (
            <Alert variant="destructive" className="border-yellow-500 bg-yellow-500/10">
              <AlertTriangle className="h-4 w-4 text-yellow-600" />
              <AlertTitle className="text-yellow-700">Modo Aberto Ativado</AlertTitle>
              <AlertDescription className="text-yellow-600">
                O app aceitará instalações de qualquer fonte. Isso permite que usuários instalem 
                o APK diretamente, sem precisar da Play Store. Use com cuidado.
              </AlertDescription>
            </Alert>
          ) : (
            <Alert className="border-green-500 bg-green-500/10">
              <CheckCircle2 className="h-4 w-4 text-green-600" />
              <AlertTitle className="text-green-700">Modo Seguro Ativado</AlertTitle>
              <AlertDescription className="text-green-600">
                O app só funcionará se for instalado pela Google Play Store (com.android.vending). 
                Instalações via APK ou outras lojas serão bloqueadas.
              </AlertDescription>
            </Alert>
          )}
        </CardContent>
      </Card>

      {/* Card informativo */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Shield className="h-5 w-5" />
            Como funciona?
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="p-4 rounded-lg border bg-muted/50">
              <div className="flex items-center gap-2 mb-2">
                <Store className="h-5 w-5 text-green-500" />
                <h3 className="font-semibold">Apenas Play Store (Recomendado)</h3>
              </div>
              <ul className="text-sm text-muted-foreground space-y-1">
                <li>• Mais seguro contra pirataria</li>
                <li>• Usuários precisam baixar da Play Store</li>
                <li>• Bloqueia APKs compartilhados</li>
                <li>• Ideal para apps em produção</li>
              </ul>
            </div>
            
            <div className="p-4 rounded-lg border bg-muted/50">
              <div className="flex items-center gap-2 mb-2">
                <Globe className="h-5 w-5 text-yellow-500" />
                <h3 className="font-semibold">Qualquer Lugar</h3>
              </div>
              <ul className="text-sm text-muted-foreground space-y-1">
                <li>• Permite instalação via APK</li>
                <li>• Útil para testes e desenvolvimento</li>
                <li>• Permite lojas alternativas</li>
                <li>• Menos controle sobre distribuição</li>
              </ul>
            </div>
          </div>
          
          <div className="pt-4 border-t">
            <h4 className="font-medium mb-2">Detalhes técnicos:</h4>
            <ul className="text-sm text-muted-foreground space-y-1">
              <li>• O app Android verifica o <code className="bg-muted px-1 rounded">installerPackageName</code> ao iniciar</li>
              <li>• Se "Apenas Play Store": aceita apenas <code className="bg-muted px-1 rounded">com.android.vending</code></li>
              <li>• Se "Qualquer Lugar": aceita qualquer valor (retorna <code className="bg-muted px-1 rounded">["*"]</code>)</li>
              <li>• A verificação é feita via endpoint <code className="bg-muted px-1 rounded">/api/v1/security/allowed-installers.php</code></li>
            </ul>
          </div>
        </CardContent>
      </Card>

      {/* Status atual */}
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Status Atual da API</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-center gap-4">
            <div className="flex-1">
              <p className="text-sm text-muted-foreground">Endpoint:</p>
              <code className="text-xs bg-muted px-2 py-1 rounded">
                /api/v1/security/allowed-installers.php
              </code>
            </div>
            <div className="flex-1">
              <p className="text-sm text-muted-foreground">Resposta atual:</p>
              <code className="text-xs bg-muted px-2 py-1 rounded">
                {allowAnyInstaller ? '["*"]' : '["com.android.vending"]'}
              </code>
            </div>
            <div className="flex-1">
              <p className="text-sm text-muted-foreground">allow_any:</p>
              <code className={`text-xs px-2 py-1 rounded ${allowAnyInstaller ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}`}>
                {allowAnyInstaller ? 'true' : 'false'}
              </code>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
