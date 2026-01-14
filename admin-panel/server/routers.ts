import { COOKIE_NAME } from "@shared/const";
import { getSessionCookieOptions } from "./_core/cookies";
import { systemRouter } from "./_core/systemRouter";
import { publicProcedure, protectedProcedure, router } from "./_core/trpc";
import { TRPCError } from "@trpc/server";
import { z } from "zod";
import * as railwayDb from "./railwayDb";

// Admin-only procedure - desabilitado para acesso livre
const adminProcedure = publicProcedure;

export const appRouter = router({
  system: systemRouter,
  
  auth: router({
    me: publicProcedure.query(opts => opts.ctx.user),
    logout: publicProcedure.mutation(({ ctx }) => {
      const cookieOptions = getSessionCookieOptions(ctx.req);
      ctx.res.clearCookie(COOKIE_NAME, { ...cookieOptions, maxAge: -1 });
      return { success: true } as const;
    }),
  }),

  // ============= DASHBOARD =============
  dashboard: router({
    stats: adminProcedure.query(async () => {
      const stats = await railwayDb.getRailwayDashboardStats();
      return stats;
    }),
  }),

  // ============= APP USERS =============
  appUsers: router({
    list: adminProcedure
      .input(z.object({
        limit: z.number().optional().default(100),
        offset: z.number().optional().default(0),
        search: z.string().optional(),
      }))
      .query(async ({ input }) => {
        const users = await railwayDb.getAllRailwayUsers(input.limit, input.offset);
        const total = await railwayDb.getRailwayUserCount();
        return { users, total };
      }),

    getById: adminProcedure
      .input(z.object({ id: z.number() }))
      .query(async ({ input }) => {
        const user = await railwayDb.getRailwayUserById(input.id);
        if (!user) {
          throw new TRPCError({ code: 'NOT_FOUND', message: 'Usuário não encontrado' });
        }
        return user;
      }),

    updatePoints: adminProcedure
      .input(z.object({
        id: z.number(),
        points: z.number(),
        operation: z.enum(['add', 'subtract', 'set']).optional().default('set'),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.updateUserPoints(input.id, input.points, input.operation);
        await railwayDb.createAdminLog('UPDATE_POINTS', `Pontos do usuário ${input.id} atualizados: ${input.operation} ${input.points}`);
        return { success: true };
      }),

    updateBalance: adminProcedure
      .input(z.object({
        id: z.number(),
        balance: z.number(),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.updateRailwayUserBalance(input.id, input.balance);
        await railwayDb.createAdminLog('UPDATE_BALANCE', `Saldo do usuário ${input.id} atualizado para ${input.balance}`);
        return { success: true };
      }),

    ban: adminProcedure
      .input(z.object({
        id: z.number(),
        reason: z.string(),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.banUser(input.id, input.reason);
        return { success: true };
      }),

    deleteAll: adminProcedure
      .input(z.object({
        confirmText: z.string(),
      }))
      .mutation(async ({ input }) => {
        if (input.confirmText !== 'EXCLUIR TODOS') {
          throw new TRPCError({ code: 'BAD_REQUEST', message: 'Texto de confirmação incorreto' });
        }
        const result = await railwayDb.deleteAllUsers();
        await railwayDb.createAdminLog('DELETE_ALL_USERS', `Todos os usuários foram excluídos. Total: ${result.count}`);
        return { success: true, count: result.count };
      }),
  }),

  // ============= POINT TRANSACTIONS =============
  pointTransactions: router({
    list: adminProcedure
      .input(z.object({
        userId: z.number().optional(),
        limit: z.number().optional().default(100),
      }))
      .query(async ({ input }) => {
        const transactions = await railwayDb.getPointTransactions(input.limit);
        return transactions;
      }),

    userHistory: adminProcedure
      .input(z.object({
        userId: z.number(),
        limit: z.number().optional().default(100),
      }))
      .query(async ({ input }) => {
        const history = await railwayDb.getUserPointsHistory(input.userId, input.limit);
        return history;
      }),
  }),

  // ============= WITHDRAWALS =============
  withdrawals: router({
    list: adminProcedure
      .input(z.object({
        status: z.string().optional(),
        limit: z.number().optional().default(100),
      }))
      .query(async ({ input }) => {
        const withdrawals = await railwayDb.getRailwayWithdrawals(input.status, input.limit);
        return withdrawals;
      }),

    updateStatus: adminProcedure
      .input(z.object({
        id: z.number(),
        status: z.enum(['pending', 'approved', 'rejected']),
        receiptUrl: z.string().optional(),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.updateRailwayWithdrawalStatus(input.id, input.status, input.receiptUrl);
        await railwayDb.createAdminLog('UPDATE_WITHDRAWAL', `Saque ${input.id} atualizado para ${input.status}${input.receiptUrl ? ' com comprovante' : ''}`);
        return { success: true };
      }),

    stats: adminProcedure.query(async () => {
      const stats = await railwayDb.getRailwayWithdrawalStats();
      return stats;
    }),

    create: adminProcedure
      .input(z.object({
        userId: z.number(),
        amount: z.number(),
        pixType: z.string(),
        pixKey: z.string(),
        status: z.string().optional().default('pending'),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.createRailwayWithdrawal(input);
        await railwayDb.createAdminLog('CREATE_WITHDRAWAL', `Saque criado para usuário ${input.userId}: R$ ${input.amount}`);
        return { success: true };
      }),

    requests: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const requests = await railwayDb.getWithdrawalRequests(input.limit);
        return requests;
      }),
  }),

  // ============= REFERRALS =============
  referrals: router({
    list: adminProcedure
      .input(z.object({
        limit: z.number().optional().default(100),
      }))
      .query(async ({ input }) => {
        const referrals = await railwayDb.getRailwayReferrals(input.limit);
        return referrals;
      }),
  }),

  // ============= RANKING =============
  ranking: router({
    list: adminProcedure
      .input(z.object({
        limit: z.number().optional().default(10000),
      }))
      .query(async ({ input }) => {
        const ranking = await railwayDb.getRailwayRanking(input.limit);
        return ranking;
      }),

    updateDailyPoints: adminProcedure
      .input(z.object({
        id: z.number(),
        points: z.number(),
        operation: z.enum(['add', 'subtract', 'set']).optional().default('set'),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.updateDailyPoints(input.id, input.points, input.operation);
        await railwayDb.createAdminLog('UPDATE_DAILY_POINTS', `Pontos diários do usuário ${input.id} atualizados: ${input.operation} ${input.points}`);
        return { success: true };
      }),
  }),

  // ============= SYSTEM SETTINGS =============
  settings: router({
    list: adminProcedure.query(async () => {
      const settings = await railwayDb.getSystemSettings();
      return settings;
    }),

    update: adminProcedure
      .input(z.object({
        key: z.string(),
        value: z.string(),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.updateSystemSetting(input.key, input.value);
        await railwayDb.createAdminLog('UPDATE_SETTING', `Configuração ${input.key} atualizada para ${input.value}`);
        return { success: true };
      }),
  }),

  // ============= ROULETTE =============
  roulette: router({
    settings: adminProcedure.query(async () => {
      const settings = await railwayDb.getRouletteSettings();
      return settings;
    }),

    updateSetting: adminProcedure
      .input(z.object({
        key: z.string(),
        value: z.string(),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.updateRouletteSetting(input.key, input.value);
        await railwayDb.createAdminLog('UPDATE_ROULETTE', `Roleta ${input.key} atualizada para ${input.value}`);
        return { success: true };
      }),

    prizes: adminProcedure.query(async () => {
      const prizes = await railwayDb.getRoulettePrizes();
      return prizes;
    }),

    updatePrize: adminProcedure
      .input(z.object({
        id: z.number(),
        prizeName: z.string(),
        prizeValue: z.number(),
        probability: z.number(),
        isActive: z.boolean(),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.updateRoulettePrize(input.id, input.prizeName, input.prizeValue, input.probability, input.isActive);
        await railwayDb.createAdminLog('UPDATE_PRIZE', `Prêmio ${input.id} atualizado`);
        return { success: true };
      }),

    spinHistory: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const history = await railwayDb.getSpinHistory(input.limit);
        return history;
      }),
  }),

  // ============= DAILY CHECKIN =============
  checkin: router({
    list: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const checkins = await railwayDb.getDailyCheckins(input.limit);
        return checkins;
      }),
  }),

  // ============= DAILY TASKS =============
  tasks: router({
    list: adminProcedure.query(async () => {
      const tasks = await railwayDb.getDailyTasks();
      return tasks;
    }),

    update: adminProcedure
      .input(z.object({
        id: z.number(),
        taskName: z.string().optional(),
        pointsReward: z.number().optional(),
        isActive: z.boolean().optional(),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.updateDailyTask(input.id, {
          task_name: input.taskName,
          points_reward: input.pointsReward,
          is_active: input.isActive,
        });
        await railwayDb.createAdminLog('UPDATE_TASK', `Tarefa ${input.id} atualizada`);
        return { success: true };
      }),
  }),

  // ============= NOTIFICATIONS =============
  notifications: router({
    list: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const notifications = await railwayDb.getNotifications(input.limit);
        return notifications;
      }),

    create: adminProcedure
      .input(z.object({
        userId: z.number(),
        title: z.string(),
        message: z.string(),
        type: z.string().optional().default('info'),
        points: z.number().optional().default(0),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.createNotification(input.userId, input.title, input.message, input.type, input.points);
        const pointsMsg = input.points > 0 ? ` (+${input.points} pontos)` : '';
        await railwayDb.createAdminLog('CREATE_NOTIFICATION', `Notificação enviada para usuário ${input.userId}${pointsMsg}`);
        return { success: true };
      }),

    broadcast: adminProcedure
      .input(z.object({
        title: z.string(),
        message: z.string(),
        type: z.string().optional().default('info'),
        points: z.number().optional().default(0),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.sendBroadcastNotification(input.title, input.message, input.type, input.points);
        const pointsMsg = input.points > 0 ? ` (+${input.points} pontos para todos)` : '';
        await railwayDb.createAdminLog('BROADCAST_NOTIFICATION', `Notificação broadcast enviada: ${input.title}${pointsMsg}`);
        return { success: true };
      }),
  }),

  // ============= DEVICE BLACKLIST =============
  blacklist: router({
    list: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const devices = await railwayDb.getDeviceBlacklist(input.limit);
        return devices;
      }),

    add: adminProcedure
      .input(z.object({
        deviceId: z.string(),
        reason: z.string(),
      }))
      .mutation(async ({ input }) => {
        await railwayDb.addToDeviceBlacklist(input.deviceId, input.reason);
        await railwayDb.createAdminLog('ADD_BLACKLIST', `Dispositivo ${input.deviceId} adicionado à blacklist`);
        return { success: true };
      }),

    remove: adminProcedure
      .input(z.object({ id: z.number() }))
      .mutation(async ({ input }) => {
        await railwayDb.removeFromDeviceBlacklist(input.id);
        await railwayDb.createAdminLog('REMOVE_BLACKLIST', `Dispositivo removido da blacklist`);
        return { success: true };
      }),
  }),

  // ============= SECURITY VIOLATIONS =============
  security: router({
    violations: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const violations = await railwayDb.getSecurityViolations(input.limit);
        return violations;
      }),
  }),

  // ============= ACTIVE SESSIONS =============
  sessions: router({
    list: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const sessions = await railwayDb.getActiveSessions(input.limit);
        return sessions;
      }),

    terminate: adminProcedure
      .input(z.object({ id: z.number() }))
      .mutation(async ({ input }) => {
        await railwayDb.terminateSession(input.id);
        await railwayDb.createAdminLog('TERMINATE_SESSION', `Sessão ${input.id} encerrada`);
        return { success: true };
      }),
  }),

  // ============= MONETAG =============
  monetag: router({
    events: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const events = await railwayDb.getMonetag_Events(input.limit);
        return events;
      }),

    postbacks: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const postbacks = await railwayDb.getMonetag_Postbacks(input.limit);
        return postbacks;
      }),
  }),

  // ============= PIX PAYMENTS =============
  pixPayments: router({
    list: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const payments = await railwayDb.getPixPayments(input.limit);
        return payments;
      }),
  }),

  // ============= TRACKING EVENTS =============
  tracking: router({
    events: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const events = await railwayDb.getTrackingEvents(input.limit);
        return events;
      }),
  }),

  // ============= ADMIN LOGS =============
  adminLogs: router({
    list: adminProcedure
      .input(z.object({ limit: z.number().optional().default(100) }))
      .query(async ({ input }) => {
        const logs = await railwayDb.getAdminLogs(input.limit);
        return logs;
      }),
  }),

  // ============= DATABASE EXPLORER =============
  database: router({
    tables: adminProcedure.query(async () => {
      const tables = await railwayDb.listRailwayTables();
      return tables;
    }),

    describe: adminProcedure
      .input(z.object({ table: z.string() }))
      .query(async ({ input }) => {
        const structure = await railwayDb.describeRailwayTable(input.table);
        return structure;
      }),

    query: adminProcedure
      .input(z.object({ 
        sql: z.string(),
        params: z.array(z.any()).optional().default([]),
      }))
      .mutation(async ({ input }) => {
        // Apenas SELECT queries são permitidas por segurança
        const sqlLower = input.sql.toLowerCase().trim();
        if (!sqlLower.startsWith('select')) {
          throw new TRPCError({ 
            code: 'BAD_REQUEST', 
            message: 'Apenas queries SELECT são permitidas' 
          });
        }
        const result = await railwayDb.executeRawQuery(input.sql, input.params);
        return result;
      }),

    execute: adminProcedure
      .input(z.object({ 
        sql: z.string(),
        params: z.array(z.any()).optional().default([]),
      }))
      .mutation(async ({ input }) => {
        // Permite INSERT, UPDATE, DELETE para admins
        const result = await railwayDb.executeRawQuery(input.sql, input.params);
        await railwayDb.createAdminLog('EXECUTE_SQL', `SQL executado: ${input.sql.substring(0, 100)}`);
        return result;
      }),
  }),
});

export type AppRouter = typeof appRouter;
