import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Badge } from "@/components/ui/badge";
import { toast } from "sonner";
import { Database as DatabaseIcon, Play, Table as TableIcon, Eye } from "lucide-react";

export default function Database() {
  const { data: tables, isLoading: loadingTables } = trpc.database.tables.useQuery();
  const [selectedTable, setSelectedTable] = useState<string | null>(null);
  const [sql, setSql] = useState("SELECT * FROM users LIMIT 10");
  const [queryResult, setQueryResult] = useState<any[] | null>(null);
  const [isQuerying, setIsQuerying] = useState(false);

  const { data: tableStructure, isLoading: loadingStructure } = trpc.database.describe.useQuery(
    { table: selectedTable! },
    { enabled: !!selectedTable }
  );

  const executeQuery = trpc.database.query.useMutation({
    onSuccess: (data) => {
      setQueryResult(data);
      toast.success(`Query executada! ${data.length} resultados`);
    },
    onError: (error) => {
      toast.error(`Erro: ${error.message}`);
      setQueryResult(null);
    },
    onSettled: () => setIsQuerying(false),
  });

  const handleExecute = () => {
    if (!sql.trim()) {
      toast.error("Digite uma query SQL");
      return;
    }
    setIsQuerying(true);
    executeQuery.mutate({ sql, params: [] });
  };

  const handleTableClick = (tableName: string) => {
    setSelectedTable(tableName);
    setSql(`SELECT * FROM ${tableName} LIMIT 20`);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <DatabaseIcon className="h-8 w-8 text-primary" />
        <div>
          <h1 className="text-3xl font-bold">Explorador de Banco de Dados</h1>
          <p className="text-muted-foreground">Execute queries SQL e explore as tabelas</p>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-4">
        {/* Lista de Tabelas */}
        <Card className="lg:col-span-1">
          <CardHeader className="pb-3">
            <CardTitle className="text-lg flex items-center gap-2">
              <TableIcon className="h-4 w-4" />
              Tabelas
            </CardTitle>
            <CardDescription>{tables?.length || 0} tabelas</CardDescription>
          </CardHeader>
          <CardContent className="p-0">
            <ScrollArea className="h-[400px]">
              {loadingTables ? (
                <div className="p-4 space-y-2">
                  {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
                    <Skeleton key={i} className="h-8 w-full" />
                  ))}
                </div>
              ) : (
                <div className="p-2">
                  {tables?.map((table: any) => (
                    <Button
                      key={table.Tables_in_railway}
                      variant={selectedTable === table.Tables_in_railway ? "secondary" : "ghost"}
                      className="w-full justify-start text-sm h-8 mb-1"
                      onClick={() => handleTableClick(table.Tables_in_railway)}
                    >
                      <TableIcon className="h-3 w-3 mr-2" />
                      {table.Tables_in_railway}
                    </Button>
                  ))}
                </div>
              )}
            </ScrollArea>
          </CardContent>
        </Card>

        {/* Editor SQL e Resultados */}
        <div className="lg:col-span-3 space-y-4">
          {/* Estrutura da Tabela */}
          {selectedTable && (
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-lg flex items-center gap-2">
                  <Eye className="h-4 w-4" />
                  Estrutura: {selectedTable}
                </CardTitle>
              </CardHeader>
              <CardContent>
                {loadingStructure ? (
                  <Skeleton className="h-32 w-full" />
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {tableStructure?.map((col: any) => (
                      <Badge key={col.Field} variant="outline" className="text-xs">
                        <span className="font-semibold">{col.Field}</span>
                        <span className="text-muted-foreground ml-1">({col.Type})</span>
                        {col.Key === 'PRI' && <span className="ml-1 text-yellow-500 font-bold">(PK)</span>}
                      </Badge>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          {/* Editor SQL */}
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-lg">Query SQL</CardTitle>
              <CardDescription>Apenas queries SELECT são permitidas</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <Textarea
                value={sql}
                onChange={(e) => setSql(e.target.value)}
                placeholder="SELECT * FROM users LIMIT 10"
                className="font-mono text-sm min-h-[100px]"
              />
              <Button onClick={handleExecute} disabled={isQuerying} className="flex items-center gap-2">
                <Play className="h-4 w-4" />
                {isQuerying ? "Executando..." : "Executar Query"}
              </Button>
            </CardContent>
          </Card>

          {/* Resultados */}
          {queryResult && (
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-lg">Resultados ({queryResult.length})</CardTitle>
              </CardHeader>
              <CardContent>
                <ScrollArea className="h-[400px]">
                  {queryResult.length === 0 ? (
                    <p className="text-center text-muted-foreground py-8">Nenhum resultado</p>
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          {Object.keys(queryResult[0]).map((key) => (
                            <TableHead key={key} className="text-xs">{key}</TableHead>
                          ))}
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {queryResult.map((row, idx) => (
                          <TableRow key={idx}>
                            {Object.values(row).map((value: any, i) => (
                              <TableCell key={i} className="text-xs font-mono max-w-[200px] truncate">
                                {value === null ? <span className="text-muted-foreground">NULL</span> : String(value)}
                              </TableCell>
                            ))}
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </ScrollArea>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}
