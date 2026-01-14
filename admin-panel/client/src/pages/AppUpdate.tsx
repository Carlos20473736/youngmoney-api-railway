import { useState, useEffect } from "react";
import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "sonner";
import { 
  Download, 
  Save, 
  Loader2, 
  Smartphone, 
  Link, 
  ExternalLink,
  AlertTriangle,
  FileText,
  RefreshCw
} from "lucide-react";

interface UpdateSettings {
  app_update_enabled: string;
  app_update_min_version: string;
  app_update_download_url: string;
  app_update_secondary_url: string;
  app_update_force: string;
  app_update_release_notes: string;
}

export default function AppUpdate() {
  const { data: allSettings, isLoading, refetch } = trpc.settings.list.useQuery();
  const updateSetting = trpc.settings.update.useMutation({
    onSuccess: () => {
      refetch();
    },
    onError: (error) => {
      toast.error(`Erro ao atualizar: ${error.message}`);
    },
  });

  const [settings, setSettings] = useState<UpdateSettings>({
    app_update_enabled: "0",
    app_update_min_version: "1.0.0",
    app_update_download_url: "",
    app_update_secondary_url: "",
    app_update_force: "0",
    app_update_release_notes: "",
  });

  const [isSaving, setIsSaving] = useState(false);

  // Carregar configurações existentes
  useEffect(() => {
    if (allSettings) {
      const updateSettings: Partial<UpdateSettings> = {};
      allSettings.forEach((setting: any) => {
        if (setting.setting_key.startsWith("app_update_")) {
          updateSettings[setting.setting_key as keyof UpdateSettings] = setting.setting_value;
        }
      });
      setSettings((prev) => ({ ...prev, ...updateSettings }));
    }
  }, [allSettings]);

  const handleSaveAll = async () => {
    setIsSaving(true);
    try {
      // Salvar todas as configurações
      const settingsToSave = [
        { key: "app_update_enabled", value: settings.app_update_enabled },
        { key: "app_update_min_version", value: settings.app_update_min_version },
        { key: "app_update_download_url", value: settings.app_update_download_url },
        { key: "app_update_secondary_url", value: settings.app_update_secondary_url },
        { key: "app_update_force", value: settings.app_update_force },
        { key: "app_update_release_notes", value: settings.app_update_release_notes },
      ];

      for (const setting of settingsToSave) {
        await updateSetting.mutateAsync(setting);
      }

      toast.success("Configurações de atualização salvas com sucesso!");
    } catch (error) {
      toast.error("Erro ao salvar configurações");
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-3">
          <Download className="h-8 w-8 text-primary" />
          <div>
            <h1 className="text-3xl font-bold">Atualização do App</h1>
            <p className="text-muted-foreground">Carregando configurações...</p>
          </div>
        </div>
        <div className="grid gap-6">
          {[1, 2, 3].map((i) => (
            <Card key={i}>
              <CardHeader>
                <Skeleton className="h-6 w-48" />
                <Skeleton className="h-4 w-64" />
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
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Download className="h-8 w-8 text-primary" />
          <div>
            <h1 className="text-3xl font-bold">Atualização do App</h1>
            <p className="text-muted-foreground">
              Configure as opções de atualização forçada do aplicativo
            </p>
          </div>
        </div>
        <Button onClick={handleSaveAll} disabled={isSaving} size="lg">
          {isSaving ? (
            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
          ) : (
            <Save className="h-4 w-4 mr-2" />
          )}
          Salvar Todas as Configurações
        </Button>
      </div>

      {/* Status Card */}
      <Card className={settings.app_update_enabled === "1" ? "border-green-500" : "border-gray-300"}>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Smartphone className="h-5 w-5" />
            Status da Atualização
          </CardTitle>
          <CardDescription>
            Quando habilitado, o app mostrará o diálogo de atualização para usuários com versão inferior
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex items-center justify-between p-4 bg-muted rounded-lg">
            <div className="flex items-center gap-3">
              <div className={`w-3 h-3 rounded-full ${settings.app_update_enabled === "1" ? "bg-green-500" : "bg-gray-400"}`} />
              <span className="font-medium">
                {settings.app_update_enabled === "1" ? "Atualização Habilitada" : "Atualização Desabilitada"}
              </span>
            </div>
            <Switch
              checked={settings.app_update_enabled === "1"}
              onCheckedChange={(checked) =>
                setSettings({ ...settings, app_update_enabled: checked ? "1" : "0" })
              }
            />
          </div>
        </CardContent>
      </Card>

      {/* Version Settings */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <RefreshCw className="h-5 w-5" />
            Configurações de Versão
          </CardTitle>
          <CardDescription>
            Defina a versão mínima requerida e se a atualização deve ser forçada
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="min_version">Versão Mínima Requerida</Label>
              <Input
                id="min_version"
                value={settings.app_update_min_version}
                onChange={(e) =>
                  setSettings({ ...settings, app_update_min_version: e.target.value })
                }
                placeholder="Ex: 44.0 ou 2.1.0"
              />
              <p className="text-xs text-muted-foreground">
                Usuários com versão inferior a esta serão notificados para atualizar
              </p>
            </div>

            <div className="space-y-2">
              <Label>Forçar Atualização</Label>
              <div className="flex items-center justify-between p-3 border rounded-lg">
                <div className="flex items-center gap-2">
                  <AlertTriangle className={`h-4 w-4 ${settings.app_update_force === "1" ? "text-orange-500" : "text-gray-400"}`} />
                  <span className="text-sm">
                    {settings.app_update_force === "1" 
                      ? "Usuário não pode ignorar" 
                      : "Usuário pode ignorar"}
                  </span>
                </div>
                <Switch
                  checked={settings.app_update_force === "1"}
                  onCheckedChange={(checked) =>
                    setSettings({ ...settings, app_update_force: checked ? "1" : "0" })
                  }
                />
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Download URLs */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Link className="h-5 w-5" />
            Links de Download
          </CardTitle>
          <CardDescription>
            Configure os links para download do aplicativo
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Primary URL - Play Store */}
          <div className="space-y-2">
            <Label htmlFor="download_url" className="flex items-center gap-2">
              <span className="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Principal</span>
              URL da Play Store
            </Label>
            <div className="flex gap-2">
              <Input
                id="download_url"
                value={settings.app_update_download_url}
                onChange={(e) =>
                  setSettings({ ...settings, app_update_download_url: e.target.value })
                }
                placeholder="https://play.google.com/store/apps/details?id=com.seuapp"
                className="flex-1"
              />
              {settings.app_update_download_url && (
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => window.open(settings.app_update_download_url, "_blank")}
                >
                  <ExternalLink className="h-4 w-4" />
                </Button>
              )}
            </div>
            <p className="text-xs text-muted-foreground">
              Link principal para a Play Store (abre o app da Play Store no celular)
            </p>
          </div>

          {/* Secondary URL - Direct Download */}
          <div className="space-y-2">
            <Label htmlFor="secondary_url" className="flex items-center gap-2">
              <span className="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Secundário</span>
              URL de Download Direto (APK)
            </Label>
            <div className="flex gap-2">
              <Input
                id="secondary_url"
                value={settings.app_update_secondary_url}
                onChange={(e) =>
                  setSettings({ ...settings, app_update_secondary_url: e.target.value })
                }
                placeholder="https://seusite.com/download/app-v44.apk"
                className="flex-1"
              />
              {settings.app_update_secondary_url && (
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => window.open(settings.app_update_secondary_url, "_blank")}
                >
                  <ExternalLink className="h-4 w-4" />
                </Button>
              )}
            </div>
            <p className="text-xs text-muted-foreground">
              Link alternativo que abre no navegador para download direto do APK
            </p>
          </div>

          {/* Info Box */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 className="font-medium text-blue-900 mb-2">Como funciona:</h4>
            <ul className="text-sm text-blue-800 space-y-1">
              <li>• <strong>URL Principal:</strong> Abre a Play Store para atualização oficial</li>
              <li>• <strong>URL Secundária:</strong> Abre o navegador para download direto do APK</li>
              <li>• O app mostrará ambas as opções no diálogo de atualização</li>
            </ul>
          </div>
        </CardContent>
      </Card>

      {/* Release Notes */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <FileText className="h-5 w-5" />
            Notas da Versão
          </CardTitle>
          <CardDescription>
            Descreva as novidades e melhorias desta atualização
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Textarea
            value={settings.app_update_release_notes}
            onChange={(e) =>
              setSettings({ ...settings, app_update_release_notes: e.target.value })
            }
            placeholder="Ex: Nova versão com melhorias de desempenho, correção de bugs e novos recursos..."
            rows={4}
          />
          <p className="text-xs text-muted-foreground mt-2">
            Este texto será exibido no diálogo de atualização do app
          </p>
        </CardContent>
      </Card>

      {/* Preview */}
      <Card>
        <CardHeader>
          <CardTitle>Pré-visualização da Resposta da API</CardTitle>
          <CardDescription>
            Esta é a resposta que a API retornará quando o app verificar atualizações
          </CardDescription>
        </CardHeader>
        <CardContent>
          <pre className="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm">
{JSON.stringify({
  success: true,
  data: {
    update_required: true,
    update_enabled: settings.app_update_enabled === "1",
    current_version: "exemplo: 43.0",
    min_version: settings.app_update_min_version,
    download_url: settings.app_update_download_url || "(não configurado)",
    secondary_download_url: settings.app_update_secondary_url || "(não configurado)",
    force_update: settings.app_update_force === "1",
    release_notes: settings.app_update_release_notes || "(não configurado)"
  }
}, null, 2)}
          </pre>
        </CardContent>
      </Card>

      {/* Save Button (bottom) */}
      <div className="flex justify-end">
        <Button onClick={handleSaveAll} disabled={isSaving} size="lg">
          {isSaving ? (
            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
          ) : (
            <Save className="h-4 w-4 mr-2" />
          )}
          Salvar Todas as Configurações
        </Button>
      </div>
    </div>
  );
}
