import { useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Settings as SettingsIcon, Save, Loader2 } from "lucide-react";
import { toast } from "sonner";

export default function Settings() {
  const [editingKey, setEditingKey] = useState<string | null>(null);
  const [editValue, setEditValue] = useState("");
  const [editDescription, setEditDescription] = useState("");
  const [isSaving, setIsSaving] = useState(false);
  const [savedSettings, setSavedSettings] = useState<Record<string, string>>({});

  const handleSave = async (key: string) => {
    if (!editValue.trim()) {
      toast.error("Valor não pode estar vazio");
      return;
    }
    setIsSaving(true);
    // Simular salvamento
    await new Promise(resolve => setTimeout(resolve, 500));
    setSavedSettings(prev => ({ ...prev, [key]: editValue }));
    toast.success("Configuração atualizada com sucesso");
    setEditingKey(null);
    setEditValue("");
    setEditDescription("");
    setIsSaving(false);
  };

  const defaultSettings = [
    { key: "points_per_referral", label: "Pontos por Convite", description: "Pontos dados ao usuário que convida", defaultValue: "500" },
    { key: "points_for_referred", label: "Pontos para Convidado", description: "Pontos dados ao usuário convidado", defaultValue: "500" },
    { key: "points_daily_checkin", label: "Pontos Check-in Diário", description: "Pontos por check-in diário", defaultValue: "100" },
    { key: "min_withdrawal_points", label: "Mínimo para Saque", description: "Pontos mínimos para solicitar saque", defaultValue: "1000" },
    { key: "max_withdrawal_points", label: "Máximo para Saque", description: "Pontos máximos por saque", defaultValue: "100000" },
    { key: "withdrawal_fee_percent", label: "Taxa de Saque (%)", description: "Porcentagem de taxa sobre saques", defaultValue: "10" },
    { key: "points_to_real_ratio", label: "Conversão Pontos/Real", description: "Quantos pontos equivalem a R$ 1,00", defaultValue: "100" },
    { key: "candy_crush_max_plays_daily", label: "Candy Crush - Jogadas/Dia", description: "Máximo de jogadas diárias", defaultValue: "5" },
    { key: "candy_crush_points_per_win", label: "Candy Crush - Pontos/Vitória", description: "Pontos por vitória", defaultValue: "50" },
    { key: "spin_wheel_max_spins_daily", label: "Roleta - Giros/Dia", description: "Máximo de giros diários", defaultValue: "3" },
    { key: "spin_wheel_min_prize", label: "Roleta - Prêmio Mínimo", description: "Prêmio mínimo da roleta", defaultValue: "10" },
    { key: "spin_wheel_max_prize", label: "Roleta - Prêmio Máximo", description: "Prêmio máximo da roleta", defaultValue: "1000" },
  ];

  const getSettingValue = (key: string) => {
    return savedSettings[key] || defaultSettings.find(d => d.key === key)?.defaultValue || "";
  };

  const getSettingDescription = (key: string) => {
    return defaultSettings.find(d => d.key === key)?.description || "";
  };

  const renderSettingCard = (title: string, description: string, settings: typeof defaultSettings) => (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {settings.map((setting) => {
          const currentValue = getSettingValue(setting.key);
          const isEditing = editingKey === setting.key;

          return (
            <div key={setting.key} className="flex items-center justify-between p-4 border rounded-lg">
              <div className="flex-1">
                <Label className="font-semibold">{setting.label}</Label>
                <p className="text-sm text-muted-foreground mt-1">{getSettingDescription(setting.key)}</p>
                {isEditing ? (
                  <div className="mt-3 space-y-2">
                    <Input
                      type="number"
                      value={editValue}
                      onChange={(e) => setEditValue(e.target.value)}
                      placeholder="Valor"
                    />
                    <Textarea
                      value={editDescription}
                      onChange={(e) => setEditDescription(e.target.value)}
                      placeholder="Descrição (opcional)"
                      rows={2}
                    />
                  </div>
                ) : (
                  <p className="text-2xl font-bold mt-2">{currentValue}</p>
                )}
              </div>
              <div className="ml-4">
                {isEditing ? (
                  <div className="flex gap-2">
                    <Button size="sm" onClick={() => handleSave(setting.key)} disabled={isSaving}>
                      {isSaving ? <Loader2 className="h-4 w-4 mr-1 animate-spin" /> : <Save className="h-4 w-4 mr-1" />}
                      Salvar
                    </Button>
                    <Button size="sm" variant="outline" onClick={() => setEditingKey(null)}>
                      Cancelar
                    </Button>
                  </div>
                ) : (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => {
                      setEditingKey(setting.key);
                      setEditValue(currentValue);
                      setEditDescription(getSettingDescription(setting.key));
                    }}
                  >
                    <SettingsIcon className="h-4 w-4 mr-1" />
                    Editar
                  </Button>
                )}
              </div>
            </div>
          );
        })}
      </CardContent>
    </Card>
  );

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Configurações do Sistema</h1>
        <p className="text-muted-foreground mt-2">Gerencie todas as configurações do Young Money</p>
      </div>

      <div className="grid gap-6">
        {renderSettingCard(
          "Sistema de Pontos",
          "Configure valores de recompensa e limites",
          defaultSettings.slice(0, 4)
        )}

        {renderSettingCard(
          "Sistema de Saques",
          "Configure limites e taxas de saque",
          defaultSettings.slice(4, 7)
        )}

        {renderSettingCard(
          "Configurações de Jogos",
          "Configure limites e recompensas dos jogos",
          defaultSettings.slice(7)
        )}
      </div>
    </div>
  );
}
