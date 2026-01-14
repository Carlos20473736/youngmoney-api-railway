import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { DollarSign, Activity, Webhook } from "lucide-react";

export default function Monetag() {
  const { data: events, isLoading: loadingEvents } = trpc.monetag.events.useQuery({ limit: 100 });
  const { data: postbacks, isLoading: loadingPostbacks } = trpc.monetag.postbacks.useQuery({ limit: 100 });

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <DollarSign className="h-8 w-8 text-green-500" />
        <div>
          <h1 className="text-3xl font-bold">Monetag</h1>
          <p className="text-muted-foreground">Eventos e postbacks de monetização</p>
        </div>
      </div>

      <Tabs defaultValue="events" className="space-y-4">
        <TabsList>
          <TabsTrigger value="events" className="flex items-center gap-2">
            <Activity className="h-4 w-4" />
            Eventos ({events?.length || 0})
          </TabsTrigger>
          <TabsTrigger value="postbacks" className="flex items-center gap-2">
            <Webhook className="h-4 w-4" />
            Postbacks ({postbacks?.length || 0})
          </TabsTrigger>
        </TabsList>

        <TabsContent value="events">
          <Card>
            <CardHeader>
              <CardTitle>Eventos Monetag</CardTitle>
              <CardDescription>Impressões e cliques registrados</CardDescription>
            </CardHeader>
            <CardContent>
              {loadingEvents ? (
                <div className="space-y-2">
                  {[1, 2, 3, 4, 5].map((i) => (
                    <Skeleton key={i} className="h-12 w-full" />
                  ))}
                </div>
              ) : events?.length === 0 ? (
                <p className="text-center text-muted-foreground py-8">Nenhum evento registrado</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>ID</TableHead>
                      <TableHead>Usuário</TableHead>
                      <TableHead>Tipo</TableHead>
                      <TableHead>Valor</TableHead>
                      <TableHead>Data</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {events?.map((event: any) => (
                      <TableRow key={event.id}>
                        <TableCell className="font-mono">{event.id}</TableCell>
                        <TableCell>{event.user_name || `#${event.user_id}`}</TableCell>
                        <TableCell>
                          <Badge variant={event.event_type === 'impression' ? 'secondary' : 'default'}>
                            {event.event_type}
                          </Badge>
                        </TableCell>
                        <TableCell className="font-semibold text-green-600">
                          {event.value ? `R$ ${Number(event.value).toFixed(4)}` : '-'}
                        </TableCell>
                        <TableCell>{new Date(event.created_at).toLocaleString('pt-BR')}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="postbacks">
          <Card>
            <CardHeader>
              <CardTitle>Postbacks Monetag</CardTitle>
              <CardDescription>Callbacks recebidos do servidor Monetag</CardDescription>
            </CardHeader>
            <CardContent>
              {loadingPostbacks ? (
                <div className="space-y-2">
                  {[1, 2, 3, 4, 5].map((i) => (
                    <Skeleton key={i} className="h-12 w-full" />
                  ))}
                </div>
              ) : postbacks?.length === 0 ? (
                <p className="text-center text-muted-foreground py-8">Nenhum postback registrado</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>ID</TableHead>
                      <TableHead>Tipo</TableHead>
                      <TableHead>Payload</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Data</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {postbacks?.map((postback: any) => (
                      <TableRow key={postback.id}>
                        <TableCell className="font-mono">{postback.id}</TableCell>
                        <TableCell>{postback.type || '-'}</TableCell>
                        <TableCell className="max-w-xs truncate font-mono text-xs">
                          {postback.payload ? JSON.stringify(postback.payload).substring(0, 50) + '...' : '-'}
                        </TableCell>
                        <TableCell>
                          <Badge variant={postback.status === 'processed' ? 'default' : 'secondary'}>
                            {postback.status || 'received'}
                          </Badge>
                        </TableCell>
                        <TableCell>{new Date(postback.created_at).toLocaleString('pt-BR')}</TableCell>
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
