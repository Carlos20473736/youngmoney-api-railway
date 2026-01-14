import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Trophy, Medal, Award, Pencil } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

export default function Ranking() {
  const utils = trpc.useUtils();
  const { data: ranking, isLoading } = trpc.ranking.list.useQuery({ limit: 10000 });
  
  const [editDialogOpen, setEditDialogOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<{ id: number; name: string; dailyPoints: number } | null>(null);
  const [newPoints, setNewPoints] = useState("");
  const [operation, setOperation] = useState<"set" | "add" | "subtract">("set");
  const [searchEmail, setSearchEmail] = useState("");

  const updateDailyPointsMutation = trpc.ranking.updateDailyPoints.useMutation({
    onSuccess: () => {
      toast.success("Pontos diários atualizados com sucesso!");
      utils.ranking.list.invalidate();
      setEditDialogOpen(false);
      setSelectedUser(null);
      setNewPoints("");
      setOperation("set");
    },
    onError: (error) => {
      toast.error(error.message || "Erro ao atualizar pontos diários.");
    },
  });

  const getPositionIcon = (position: number) => {
    if (position === 1) return <Trophy className="h-5 w-5 text-yellow-500" />;
    if (position === 2) return <Medal className="h-5 w-5 text-gray-400" />;
    if (position === 3) return <Award className="h-5 w-5 text-amber-700" />;
    return <span className="text-muted-foreground font-semibold">{position}º</span>;
  };

  const getPrizeAmount = (position: number) => {
    if (position === 1) return 20.0;
    if (position === 2) return 10.0;
    if (position === 3) return 5.0;
    if (position >= 4 && position <= 10) return 1.0;
    return 0;
  };

  const handleEditClick = (user: { id: number; name: string; daily_points: number }) => {
    setSelectedUser({ id: user.id, name: user.name, dailyPoints: user.daily_points || 0 });
    setNewPoints(String(user.daily_points || 0));
    setOperation("set");
    setEditDialogOpen(true);
  };

  const handleSavePoints = () => {
    if (!selectedUser || !newPoints) return;
    
    const pointsValue = parseInt(newPoints, 10);
    if (isNaN(pointsValue) || pointsValue < 0) {
      toast.error("Digite um valor válido para os pontos.");
      return;
    }

    updateDailyPointsMutation.mutate({
      id: selectedUser.id,
      points: pointsValue,
      operation,
    });
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Ranking de Usuários</h1>
        <p className="text-muted-foreground mt-2">Top usuários por pontuação diária</p>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        {ranking && ranking.length >= 3 && (
          <>
            <Card className="border-yellow-200 bg-yellow-50">
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="text-lg">🥇 1º Lugar</CardTitle>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      onClick={() => handleEditClick(ranking[0])}
                    >
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Trophy className="h-8 w-8 text-yellow-500" />
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <p className="font-bold text-xl">{ranking[0].name}</p>
                <p className="text-2xl font-bold text-yellow-600 mt-2">{(ranking[0].daily_points || 0).toLocaleString('pt-BR')} pts</p>
                <p className="text-sm text-muted-foreground mt-1">Prêmio: R$ 20,00</p>
              </CardContent>
            </Card>

            <Card className="border-gray-200 bg-gray-50">
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="text-lg">🥈 2º Lugar</CardTitle>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      onClick={() => handleEditClick(ranking[1])}
                    >
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Medal className="h-8 w-8 text-gray-400" />
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <p className="font-bold text-xl">{ranking[1].name}</p>
                <p className="text-2xl font-bold text-gray-600 mt-2">{(ranking[1].daily_points || 0).toLocaleString('pt-BR')} pts</p>
                <p className="text-sm text-muted-foreground mt-1">Prêmio: R$ 10,00</p>
              </CardContent>
            </Card>

            <Card className="border-amber-200 bg-amber-50">
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="text-lg">🥉 3º Lugar</CardTitle>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      onClick={() => handleEditClick(ranking[2])}
                    >
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Award className="h-8 w-8 text-amber-700" />
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <p className="font-bold text-xl">{ranking[2].name}</p>
                <p className="text-2xl font-bold text-amber-700 mt-2">{(ranking[2].daily_points || 0).toLocaleString('pt-BR')} pts</p>
                <p className="text-sm text-muted-foreground mt-1">Prêmio: R$ 5,00</p>
              </CardContent>
            </Card>
          </>
        )}
      </div>

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>Ranking Completo</CardTitle>
              <CardDescription>Todos os usuários por pontuação diária</CardDescription>
            </div>
          </div>
          <div className="mt-4">
            <Input
              placeholder="Pesquisar por e-mail..."
              value={searchEmail}
              onChange={(e) => setSearchEmail(e.target.value)}
              className="w-full md:w-96"
            />
          </div>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="text-center py-8 text-muted-foreground">Carregando ranking...</div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-20">Posição</TableHead>
                    <TableHead>Nome</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead className="text-right">Pontos Diários</TableHead>
                    <TableHead className="text-right">Prêmio</TableHead>
                    <TableHead className="w-20 text-center">Ações</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {!ranking || ranking.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                        Nenhum usuário no ranking
                      </TableCell>
                    </TableRow>
                  ) : (
                    ranking
                      .filter((user) =>
                        searchEmail === "" || (user.email && user.email.toLowerCase().includes(searchEmail.toLowerCase()))
                      )
                      .map((user, index) => {
                      const position = index + 1;
                      const prize = getPrizeAmount(position);
                      return (
                        <TableRow key={user.id} className={position <= 3 ? "bg-muted/50" : ""}>
                          <TableCell className="font-medium">
                            <div className="flex items-center gap-2">
                              {getPositionIcon(position)}
                            </div>
                          </TableCell>
                          <TableCell className="font-semibold">{user.name}</TableCell>
                          <TableCell>{user.email || "-"}</TableCell>
                          <TableCell className="text-right font-bold text-lg">
                            {(user.daily_points || 0).toLocaleString('pt-BR')}
                          </TableCell>
                          <TableCell className="text-right">
                            {prize > 0 ? (
                              <Badge variant="default" className="bg-green-600">
                                R$ {prize.toFixed(2)}
                              </Badge>
                            ) : (
                              <span className="text-muted-foreground">-</span>
                            )}
                          </TableCell>
                          <TableCell className="text-center">
                            <Button
                              variant="ghost"
                              size="icon"
                              className="h-8 w-8"
                              onClick={() => handleEditClick(user)}
                            >
                              <Pencil className="h-4 w-4" />
                            </Button>
                          </TableCell>
                        </TableRow>
                      );
                    })
                  )}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Dialog de Edição de Pontos */}
      <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>Editar Pontos Diários</DialogTitle>
            <DialogDescription>
              Altere os pontos diários de <strong>{selectedUser?.name}</strong>
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid gap-2">
              <Label>Pontos Atuais</Label>
              <p className="text-lg font-bold">{(selectedUser?.dailyPoints || 0).toLocaleString('pt-BR')} pts</p>
            </div>
            <div className="grid gap-2">
              <Label htmlFor="operation">Operação</Label>
              <Select value={operation} onValueChange={(value) => setOperation(value as "set" | "add" | "subtract")}>
                <SelectTrigger>
                  <SelectValue placeholder="Selecione a operação" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="set">Definir valor</SelectItem>
                  <SelectItem value="add">Adicionar</SelectItem>
                  <SelectItem value="subtract">Subtrair</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="grid gap-2">
              <Label htmlFor="points">
                {operation === "set" ? "Novo valor" : operation === "add" ? "Valor a adicionar" : "Valor a subtrair"}
              </Label>
              <Input
                id="points"
                type="number"
                min="0"
                value={newPoints}
                onChange={(e) => setNewPoints(e.target.value)}
                placeholder="Digite o valor"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditDialogOpen(false)}>
              Cancelar
            </Button>
            <Button onClick={handleSavePoints} disabled={updateDailyPointsMutation.isPending}>
              {updateDailyPointsMutation.isPending ? "Salvando..." : "Salvar"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
