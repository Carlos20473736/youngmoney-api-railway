import { int, mysqlEnum, mysqlTable, text, timestamp, varchar, decimal, boolean, datetime } from "drizzle-orm/mysql-core";

/**
 * Schema adaptado para o banco de dados real do Young Money no Railway
 * Mantém compatibilidade com a estrutura existente
 */

// Tabela de usuários do sistema admin (Manus Auth)
// IMPORTANTE: Renomeada para admin_users para não conflitar com a tabela users do Railway
export const users = mysqlTable("admin_users", {
  id: int("id").autoincrement().primaryKey(),
  openId: varchar("openId", { length: 64 }).notNull().unique(),
  name: text("name"),
  email: varchar("email", { length: 320 }),
  loginMethod: varchar("loginMethod", { length: 64 }),
  role: mysqlEnum("role", ["user", "admin", "superadmin"]).default("user").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
  lastSignedIn: timestamp("lastSignedIn").defaultNow().notNull(),
});

// Tabela de usuários do aplicativo Young Money (banco real)
// IMPORTANTE: A tabela real no Railway se chama 'users', não 'app_users'
export const appUsers = mysqlTable("users", {
  id: int("id").autoincrement().primaryKey(),
  ymid: int("ymid").unique(),
  deviceId: varchar("device_id", { length: 255 }),
  googleId: varchar("google_id", { length: 255 }),
  telegramId: varchar("telegram_id", { length: 255 }),
  email: varchar("email", { length: 320 }),
  name: varchar("name", { length: 255 }),
  username: varchar("username", { length: 255 }),
  photoUrl: text("photo_url"),
  profilePicture: text("profile_picture"),
  balance: decimal("balance", { precision: 10, scale: 2 }).default("0.00"),
  points: int("points").default(0),
  monetagImpressions: int("monetag_impressions").default(0),
  monetagClicks: int("monetag_clicks").default(0),
  dailyPoints: int("daily_points").default(0),
  inviteCode: varchar("invite_code", { length: 20 }),
  invitedBy: varchar("invited_by", { length: 255 }),
  hasUsedInviteCode: boolean("has_used_invite_code").default(false),
  token: varchar("token", { length: 255 }),
  tokenExpiresAt: datetime("token_expires_at"),
  createdAt: datetime("created_at").notNull(),
  updatedAt: datetime("updated_at").notNull(),
  masterSeed: text("master_seed"),
  sessionSalt: varchar("session_salt", { length: 255 }),
  lastLoginAt: datetime("last_login_at"),
  saltUpdatedAt: datetime("salt_updated_at"),
});

// Transações de pontos
export const pointTransactions = mysqlTable("point_transactions", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  points: int("points").notNull(),
  type: varchar("type", { length: 50 }).notNull(),
  description: text("description"),
  createdAt: datetime("created_at").notNull(),
});

// Solicitações de saque
export const withdrawals = mysqlTable("withdrawals", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  amount: decimal("amount", { precision: 10, scale: 2 }).notNull(),
  pixKey: varchar("pix_key", { length: 255 }).notNull(),
  pixKeyType: varchar("pix_key_type", { length: 50 }),
  status: varchar("status", { length: 50 }).default("pending").notNull(),
  requestedAt: datetime("requested_at").notNull(),
  processedAt: datetime("processed_at"),
  adminNotes: text("admin_notes"),
});

// Sistema de convites
export const referrals = mysqlTable("referrals", {
  id: int("id").autoincrement().primaryKey(),
  referrerId: int("referrer_id").notNull(),
  referredId: int("referred_id").notNull(),
  pointsAwarded: int("points_awarded").default(0),
  createdAt: datetime("created_at").notNull(),
});

// Ranking de usuários
export const rankingPoints = mysqlTable("ranking_points", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  points: int("points").default(0),
  periodId: int("period_id"),
  rank: int("rank"),
  updatedAt: datetime("updated_at").notNull(),
});

// Períodos de ranking
export const rankingPeriods = mysqlTable("ranking_periods", {
  id: int("id").autoincrement().primaryKey(),
  startDate: datetime("start_date").notNull(),
  endDate: datetime("end_date").notNull(),
  status: varchar("status", { length: 50 }).default("active"),
  createdAt: datetime("created_at").notNull(),
});

// Chaves PIX dos usuários
export const pixKeys = mysqlTable("pix_keys", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  pixKey: varchar("pix_key", { length: 255 }).notNull(),
  pixKeyType: varchar("pix_key_type", { length: 50 }).notNull(),
  isDefault: boolean("is_default").default(false),
  createdAt: datetime("created_at").notNull(),
  updatedAt: datetime("updated_at").notNull(),
});

// Pagamentos via PIX
export const pixPayments = mysqlTable("pix_payments", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  amount: decimal("amount", { precision: 10, scale: 2 }).notNull(),
  pixKey: varchar("pix_key", { length: 255 }).notNull(),
  status: varchar("status", { length: 50 }).default("pending"),
  transactionId: varchar("transaction_id", { length: 255 }),
  createdAt: datetime("created_at").notNull(),
  completedAt: datetime("completed_at"),
  notes: text("notes"),
});

// Configurações do sistema
export const systemSettings = mysqlTable("system_settings", {
  id: int("id").autoincrement().primaryKey(),
  settingKey: varchar("setting_key", { length: 100 }).notNull().unique(),
  settingValue: text("setting_value").notNull(),
  description: text("description"),
  updatedAt: datetime("updated_at").notNull(),
});

// Logs de auditoria
export const adminLogs = mysqlTable("admin_logs", {
  id: int("id").autoincrement().primaryKey(),
  adminId: int("admin_id").notNull(),
  action: varchar("action", { length: 255 }).notNull(),
  targetType: varchar("target_type", { length: 100 }),
  targetId: int("target_id"),
  details: text("details"),
  ipAddress: varchar("ip_address", { length: 45 }),
  createdAt: datetime("created_at").notNull(),
});

// Notificações
export const notifications = mysqlTable("notifications", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  title: varchar("title", { length: 255 }).notNull(),
  message: text("message").notNull(),
  type: varchar("type", { length: 50 }).default("info"),
  isRead: boolean("is_read").default(false),
  createdAt: datetime("created_at").notNull(),
});

// Configurações da roleta
export const rouletteSettings = mysqlTable("roulette_settings", {
  id: int("id").autoincrement().primaryKey(),
  settingKey: varchar("setting_key", { length: 100 }).notNull().unique(),
  settingValue: text("setting_value").notNull(),
  updatedAt: datetime("updated_at").notNull(),
});

// Prêmios da roleta
export const roulettePrizes = mysqlTable("roulette_prizes", {
  id: int("id").autoincrement().primaryKey(),
  name: varchar("name", { length: 255 }).notNull(),
  points: int("points").notNull(),
  probability: decimal("probability", { precision: 5, scale: 2 }).notNull(),
  isActive: boolean("is_active").default(true),
  createdAt: datetime("created_at").notNull(),
});

// Histórico de giros
export const spinHistory = mysqlTable("spin_history", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  prizeId: int("prize_id").notNull(),
  pointsWon: int("points_won").notNull(),
  createdAt: datetime("created_at").notNull(),
});

// Giros disponíveis
export const spins = mysqlTable("spins", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  spinsAvailable: int("spins_available").default(0),
  lastResetAt: datetime("last_reset_at"),
  updatedAt: datetime("updated_at").notNull(),
});

// Check-in diário
export const dailyCheckin = mysqlTable("daily_checkin", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  checkinDate: datetime("checkin_date").notNull(),
  pointsAwarded: int("points_awarded").default(0),
  streak: int("streak").default(1),
});

// Tarefas diárias
export const dailyTasks = mysqlTable("daily_tasks", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  taskType: varchar("task_type", { length: 100 }).notNull(),
  isCompleted: boolean("is_completed").default(false),
  pointsAwarded: int("points_awarded").default(0),
  completedAt: datetime("completed_at"),
  createdAt: datetime("created_at").notNull(),
});

export type User = typeof users.$inferSelect;
export type InsertUser = typeof users.$inferInsert;
export type AppUser = typeof appUsers.$inferSelect;
export type InsertAppUser = typeof appUsers.$inferInsert;
export type PointTransaction = typeof pointTransactions.$inferSelect;
export type Withdrawal = typeof withdrawals.$inferSelect;
export type Referral = typeof referrals.$inferSelect;
export type RankingPoint = typeof rankingPoints.$inferSelect;
export type SystemSetting = typeof systemSettings.$inferSelect;
export type AdminLog = typeof adminLogs.$inferSelect;

// Alias para compatibilidade
export const auditLogs = adminLogs;
export const gameActivities = spinHistory;
export const rankingHistory = rankingPoints;

// Tipos de inserção
export type InsertPointTransaction = Omit<typeof pointTransactions.$inferInsert, 'createdAt'> & { createdAt?: Date };
export type InsertWithdrawal = typeof withdrawals.$inferInsert;
export type InsertReferral = typeof referrals.$inferInsert;
export type InsertRankingHistory = typeof rankingPoints.$inferInsert;
export type InsertGameActivity = typeof spinHistory.$inferInsert;
export type InsertSystemSetting = typeof systemSettings.$inferInsert;
export type InsertAuditLog = typeof adminLogs.$inferInsert;
export type InsertNotification = typeof notifications.$inferInsert;
