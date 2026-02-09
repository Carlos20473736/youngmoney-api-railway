/**
 * Task Progress Router - Endpoints públicos para verificar progresso de tarefas
 * Integração com monetag-postback-server para detectar conclusão de tarefas
 */

import { publicProcedure, router } from "./_core/trpc";
import { z } from "zod";
import * as railwayDb from "./railwayDb";

export const taskProgressRouter = router({
  /**
   * Verifica se um usuário completou uma tarefa específica
   * GET /api/trpc/taskProgress.checkCompletion?userId=623&taskType=impression
   */
  checkCompletion: publicProcedure
    .input(z.object({
      userId: z.number(),
      taskType: z.string(),
    }))
    .query(async ({ input }) => {
      const isCompleted = await railwayDb.checkUserTaskCompletion(input.userId, input.taskType);
      return {
        success: true,
        userId: input.userId,
        taskType: input.taskType,
        completed: isCompleted,
      };
    }),

  /**
   * Obtém o progresso de uma tarefa para um usuário
   * GET /api/trpc/taskProgress.getProgress?userId=623&taskType=impression
   */
  getProgress: publicProcedure
    .input(z.object({
      userId: z.number(),
      taskType: z.string(),
    }))
    .query(async ({ input }) => {
      const progress = await railwayDb.getUserTaskProgress(input.userId, input.taskType);
      return {
        success: true,
        userId: input.userId,
        taskType: input.taskType,
        ...progress,
      };
    }),

  /**
   * Marca uma tarefa como completa para um usuário
   * POST /api/trpc/taskProgress.complete
   * Body: { userId: 623, taskType: "impression", pointsAwarded: 50 }
   */
  complete: publicProcedure
    .input(z.object({
      userId: z.number(),
      taskType: z.string(),
      pointsAwarded: z.number().optional().default(0),
    }))
    .mutation(async ({ input }) => {
      const success = await railwayDb.completeUserTask(input.userId, input.taskType, input.pointsAwarded);
      return {
        success: success,
        userId: input.userId,
        taskType: input.taskType,
        message: success ? 'Tarefa marcada como completa' : 'Erro ao completar tarefa',
      };
    }),
});
