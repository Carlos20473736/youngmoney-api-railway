// server/_core/index.ts
import "dotenv/config";
import express2 from "express";
import { createServer } from "http";
import net from "net";
import { createExpressMiddleware } from "@trpc/server/adapters/express";

// shared/const.ts
var COOKIE_NAME = "app_session_id";
var ONE_YEAR_MS = 1e3 * 60 * 60 * 24 * 365;
var AXIOS_TIMEOUT_MS = 3e4;
var UNAUTHED_ERR_MSG = "Please login (10001)";
var NOT_ADMIN_ERR_MSG = "You do not have required permission (10002)";

// server/db.ts
import { eq } from "drizzle-orm";
import { drizzle } from "drizzle-orm/mysql2";

// drizzle/schema.ts
import { int, mysqlEnum, mysqlTable, text, timestamp, varchar, decimal, boolean, datetime } from "drizzle-orm/mysql-core";
var users = mysqlTable("admin_users", {
  id: int("id").autoincrement().primaryKey(),
  openId: varchar("openId", { length: 64 }).notNull().unique(),
  name: text("name"),
  email: varchar("email", { length: 320 }),
  loginMethod: varchar("loginMethod", { length: 64 }),
  role: mysqlEnum("role", ["user", "admin", "superadmin"]).default("user").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
  lastSignedIn: timestamp("lastSignedIn").defaultNow().notNull()
});
var appUsers = mysqlTable("users", {
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
  saltUpdatedAt: datetime("salt_updated_at")
});
var pointTransactions = mysqlTable("point_transactions", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  points: int("points").notNull(),
  type: varchar("type", { length: 50 }).notNull(),
  description: text("description"),
  createdAt: datetime("created_at").notNull()
});
var withdrawals = mysqlTable("withdrawals", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  amount: decimal("amount", { precision: 10, scale: 2 }).notNull(),
  pixKey: varchar("pix_key", { length: 255 }).notNull(),
  pixKeyType: varchar("pix_key_type", { length: 50 }),
  status: varchar("status", { length: 50 }).default("pending").notNull(),
  requestedAt: datetime("requested_at").notNull(),
  processedAt: datetime("processed_at"),
  adminNotes: text("admin_notes")
});
var referrals = mysqlTable("referrals", {
  id: int("id").autoincrement().primaryKey(),
  referrerId: int("referrer_id").notNull(),
  referredId: int("referred_id").notNull(),
  pointsAwarded: int("points_awarded").default(0),
  createdAt: datetime("created_at").notNull()
});
var rankingPoints = mysqlTable("ranking_points", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  points: int("points").default(0),
  periodId: int("period_id"),
  rank: int("rank"),
  updatedAt: datetime("updated_at").notNull()
});
var rankingPeriods = mysqlTable("ranking_periods", {
  id: int("id").autoincrement().primaryKey(),
  startDate: datetime("start_date").notNull(),
  endDate: datetime("end_date").notNull(),
  status: varchar("status", { length: 50 }).default("active"),
  createdAt: datetime("created_at").notNull()
});
var pixKeys = mysqlTable("pix_keys", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  pixKey: varchar("pix_key", { length: 255 }).notNull(),
  pixKeyType: varchar("pix_key_type", { length: 50 }).notNull(),
  isDefault: boolean("is_default").default(false),
  createdAt: datetime("created_at").notNull(),
  updatedAt: datetime("updated_at").notNull()
});
var pixPayments = mysqlTable("pix_payments", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  amount: decimal("amount", { precision: 10, scale: 2 }).notNull(),
  pixKey: varchar("pix_key", { length: 255 }).notNull(),
  status: varchar("status", { length: 50 }).default("pending"),
  transactionId: varchar("transaction_id", { length: 255 }),
  createdAt: datetime("created_at").notNull(),
  completedAt: datetime("completed_at"),
  notes: text("notes")
});
var systemSettings = mysqlTable("system_settings", {
  id: int("id").autoincrement().primaryKey(),
  settingKey: varchar("setting_key", { length: 100 }).notNull().unique(),
  settingValue: text("setting_value").notNull(),
  description: text("description"),
  updatedAt: datetime("updated_at").notNull()
});
var adminLogs = mysqlTable("admin_logs", {
  id: int("id").autoincrement().primaryKey(),
  adminId: int("admin_id").notNull(),
  action: varchar("action", { length: 255 }).notNull(),
  targetType: varchar("target_type", { length: 100 }),
  targetId: int("target_id"),
  details: text("details"),
  ipAddress: varchar("ip_address", { length: 45 }),
  createdAt: datetime("created_at").notNull()
});
var notifications = mysqlTable("notifications", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  title: varchar("title", { length: 255 }).notNull(),
  message: text("message").notNull(),
  type: varchar("type", { length: 50 }).default("info"),
  isRead: boolean("is_read").default(false),
  createdAt: datetime("created_at").notNull()
});
var rouletteSettings = mysqlTable("roulette_settings", {
  id: int("id").autoincrement().primaryKey(),
  settingKey: varchar("setting_key", { length: 100 }).notNull().unique(),
  settingValue: text("setting_value").notNull(),
  updatedAt: datetime("updated_at").notNull()
});
var roulettePrizes = mysqlTable("roulette_prizes", {
  id: int("id").autoincrement().primaryKey(),
  name: varchar("name", { length: 255 }).notNull(),
  points: int("points").notNull(),
  probability: decimal("probability", { precision: 5, scale: 2 }).notNull(),
  isActive: boolean("is_active").default(true),
  createdAt: datetime("created_at").notNull()
});
var spinHistory = mysqlTable("spin_history", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  prizeId: int("prize_id").notNull(),
  pointsWon: int("points_won").notNull(),
  createdAt: datetime("created_at").notNull()
});
var spins = mysqlTable("spins", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  spinsAvailable: int("spins_available").default(0),
  lastResetAt: datetime("last_reset_at"),
  updatedAt: datetime("updated_at").notNull()
});
var dailyCheckin = mysqlTable("daily_checkin", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  checkinDate: datetime("checkin_date").notNull(),
  pointsAwarded: int("points_awarded").default(0),
  streak: int("streak").default(1)
});
var dailyTasks = mysqlTable("daily_tasks", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull(),
  taskType: varchar("task_type", { length: 100 }).notNull(),
  isCompleted: boolean("is_completed").default(false),
  pointsAwarded: int("points_awarded").default(0),
  completedAt: datetime("completed_at"),
  createdAt: datetime("created_at").notNull()
});

// server/_core/env.ts
var ENV = {
  appId: process.env.VITE_APP_ID ?? "",
  cookieSecret: process.env.JWT_SECRET ?? "",
  databaseUrl: process.env.DATABASE_URL ?? "",
  oAuthServerUrl: process.env.OAUTH_SERVER_URL ?? "",
  ownerOpenId: process.env.OWNER_OPEN_ID ?? "",
  isProduction: process.env.NODE_ENV === "production",
  forgeApiUrl: process.env.BUILT_IN_FORGE_API_URL ?? "",
  forgeApiKey: process.env.BUILT_IN_FORGE_API_KEY ?? ""
};

// server/db.ts
var _db = null;
async function getDb() {
  if (!_db && process.env.DATABASE_URL) {
    try {
      _db = drizzle(process.env.DATABASE_URL);
    } catch (error) {
      console.warn("[Database] Failed to connect:", error);
      _db = null;
    }
  }
  return _db;
}
async function upsertUser(user) {
  if (!user.openId) {
    throw new Error("User openId is required for upsert");
  }
  const db = await getDb();
  if (!db) {
    console.warn("[Database] Cannot upsert user: database not available");
    return;
  }
  try {
    const values = {
      openId: user.openId
    };
    const updateSet = {};
    const textFields = ["name", "email", "loginMethod"];
    const assignNullable = (field) => {
      const value = user[field];
      if (value === void 0) return;
      const normalized = value ?? null;
      values[field] = normalized;
      updateSet[field] = normalized;
    };
    textFields.forEach(assignNullable);
    if (user.lastSignedIn !== void 0) {
      values.lastSignedIn = user.lastSignedIn;
      updateSet.lastSignedIn = user.lastSignedIn;
    }
    if (user.role !== void 0) {
      values.role = user.role;
      updateSet.role = user.role;
    } else if (user.openId === ENV.ownerOpenId) {
      values.role = "admin";
      updateSet.role = "admin";
    }
    if (!values.lastSignedIn) {
      values.lastSignedIn = /* @__PURE__ */ new Date();
    }
    if (Object.keys(updateSet).length === 0) {
      updateSet.lastSignedIn = /* @__PURE__ */ new Date();
    }
    await db.insert(users).values(values).onDuplicateKeyUpdate({
      set: updateSet
    });
  } catch (error) {
    console.error("[Database] Failed to upsert user:", error);
    throw error;
  }
}
async function getUserByOpenId(openId) {
  const db = await getDb();
  if (!db) {
    console.warn("[Database] Cannot get user: database not available");
    return void 0;
  }
  const result = await db.select().from(users).where(eq(users.openId, openId)).limit(1);
  return result.length > 0 ? result[0] : void 0;
}

// server/_core/cookies.ts
function isSecureRequest(req) {
  if (req.protocol === "https") return true;
  const forwardedProto = req.headers["x-forwarded-proto"];
  if (!forwardedProto) return false;
  const protoList = Array.isArray(forwardedProto) ? forwardedProto : forwardedProto.split(",");
  return protoList.some((proto) => proto.trim().toLowerCase() === "https");
}
function getSessionCookieOptions(req) {
  return {
    httpOnly: true,
    path: "/",
    sameSite: "none",
    secure: isSecureRequest(req)
  };
}

// shared/_core/errors.ts
var HttpError = class extends Error {
  constructor(statusCode, message) {
    super(message);
    this.statusCode = statusCode;
    this.name = "HttpError";
  }
};
var ForbiddenError = (msg) => new HttpError(403, msg);

// server/_core/sdk.ts
import axios from "axios";
import { parse as parseCookieHeader } from "cookie";
import { SignJWT, jwtVerify } from "jose";
var isNonEmptyString = (value) => typeof value === "string" && value.length > 0;
var EXCHANGE_TOKEN_PATH = `/webdev.v1.WebDevAuthPublicService/ExchangeToken`;
var GET_USER_INFO_PATH = `/webdev.v1.WebDevAuthPublicService/GetUserInfo`;
var GET_USER_INFO_WITH_JWT_PATH = `/webdev.v1.WebDevAuthPublicService/GetUserInfoWithJwt`;
var OAuthService = class {
  constructor(client) {
    this.client = client;
    console.log("[OAuth] Initialized with baseURL:", ENV.oAuthServerUrl);
    if (!ENV.oAuthServerUrl) {
      console.error(
        "[OAuth] ERROR: OAUTH_SERVER_URL is not configured! Set OAUTH_SERVER_URL environment variable."
      );
    }
  }
  decodeState(state) {
    const redirectUri = atob(state);
    return redirectUri;
  }
  async getTokenByCode(code, state) {
    const payload = {
      clientId: ENV.appId,
      grantType: "authorization_code",
      code,
      redirectUri: this.decodeState(state)
    };
    const { data } = await this.client.post(
      EXCHANGE_TOKEN_PATH,
      payload
    );
    return data;
  }
  async getUserInfoByToken(token) {
    const { data } = await this.client.post(
      GET_USER_INFO_PATH,
      {
        accessToken: token.accessToken
      }
    );
    return data;
  }
};
var createOAuthHttpClient = () => axios.create({
  baseURL: ENV.oAuthServerUrl,
  timeout: AXIOS_TIMEOUT_MS
});
var SDKServer = class {
  client;
  oauthService;
  constructor(client = createOAuthHttpClient()) {
    this.client = client;
    this.oauthService = new OAuthService(this.client);
  }
  deriveLoginMethod(platforms, fallback) {
    if (fallback && fallback.length > 0) return fallback;
    if (!Array.isArray(platforms) || platforms.length === 0) return null;
    const set = new Set(
      platforms.filter((p) => typeof p === "string")
    );
    if (set.has("REGISTERED_PLATFORM_EMAIL")) return "email";
    if (set.has("REGISTERED_PLATFORM_GOOGLE")) return "google";
    if (set.has("REGISTERED_PLATFORM_APPLE")) return "apple";
    if (set.has("REGISTERED_PLATFORM_MICROSOFT") || set.has("REGISTERED_PLATFORM_AZURE"))
      return "microsoft";
    if (set.has("REGISTERED_PLATFORM_GITHUB")) return "github";
    const first = Array.from(set)[0];
    return first ? first.toLowerCase() : null;
  }
  /**
   * Exchange OAuth authorization code for access token
   * @example
   * const tokenResponse = await sdk.exchangeCodeForToken(code, state);
   */
  async exchangeCodeForToken(code, state) {
    return this.oauthService.getTokenByCode(code, state);
  }
  /**
   * Get user information using access token
   * @example
   * const userInfo = await sdk.getUserInfo(tokenResponse.accessToken);
   */
  async getUserInfo(accessToken) {
    const data = await this.oauthService.getUserInfoByToken({
      accessToken
    });
    const loginMethod = this.deriveLoginMethod(
      data?.platforms,
      data?.platform ?? data.platform ?? null
    );
    return {
      ...data,
      platform: loginMethod,
      loginMethod
    };
  }
  parseCookies(cookieHeader) {
    if (!cookieHeader) {
      return /* @__PURE__ */ new Map();
    }
    const parsed = parseCookieHeader(cookieHeader);
    return new Map(Object.entries(parsed));
  }
  getSessionSecret() {
    const secret = ENV.cookieSecret;
    return new TextEncoder().encode(secret);
  }
  /**
   * Create a session token for a Manus user openId
   * @example
   * const sessionToken = await sdk.createSessionToken(userInfo.openId);
   */
  async createSessionToken(openId, options = {}) {
    return this.signSession(
      {
        openId,
        appId: ENV.appId,
        name: options.name || ""
      },
      options
    );
  }
  async signSession(payload, options = {}) {
    const issuedAt = Date.now();
    const expiresInMs = options.expiresInMs ?? ONE_YEAR_MS;
    const expirationSeconds = Math.floor((issuedAt + expiresInMs) / 1e3);
    const secretKey = this.getSessionSecret();
    return new SignJWT({
      openId: payload.openId,
      appId: payload.appId,
      name: payload.name
    }).setProtectedHeader({ alg: "HS256", typ: "JWT" }).setExpirationTime(expirationSeconds).sign(secretKey);
  }
  async verifySession(cookieValue) {
    if (!cookieValue) {
      console.warn("[Auth] Missing session cookie");
      return null;
    }
    try {
      const secretKey = this.getSessionSecret();
      const { payload } = await jwtVerify(cookieValue, secretKey, {
        algorithms: ["HS256"]
      });
      const { openId, appId, name } = payload;
      if (!isNonEmptyString(openId) || !isNonEmptyString(appId) || !isNonEmptyString(name)) {
        console.warn("[Auth] Session payload missing required fields");
        return null;
      }
      return {
        openId,
        appId,
        name
      };
    } catch (error) {
      console.warn("[Auth] Session verification failed", String(error));
      return null;
    }
  }
  async getUserInfoWithJwt(jwtToken) {
    const payload = {
      jwtToken,
      projectId: ENV.appId
    };
    const { data } = await this.client.post(
      GET_USER_INFO_WITH_JWT_PATH,
      payload
    );
    const loginMethod = this.deriveLoginMethod(
      data?.platforms,
      data?.platform ?? data.platform ?? null
    );
    return {
      ...data,
      platform: loginMethod,
      loginMethod
    };
  }
  async authenticateRequest(req) {
    const cookies = this.parseCookies(req.headers.cookie);
    const sessionCookie = cookies.get(COOKIE_NAME);
    const session = await this.verifySession(sessionCookie);
    if (!session) {
      throw ForbiddenError("Invalid session cookie");
    }
    const sessionUserId = session.openId;
    const signedInAt = /* @__PURE__ */ new Date();
    let user = await getUserByOpenId(sessionUserId);
    if (!user) {
      try {
        const userInfo = await this.getUserInfoWithJwt(sessionCookie ?? "");
        await upsertUser({
          openId: userInfo.openId,
          name: userInfo.name || null,
          email: userInfo.email ?? null,
          loginMethod: userInfo.loginMethod ?? userInfo.platform ?? null,
          lastSignedIn: signedInAt
        });
        user = await getUserByOpenId(userInfo.openId);
      } catch (error) {
        console.error("[Auth] Failed to sync user from OAuth:", error);
        throw ForbiddenError("Failed to sync user info");
      }
    }
    if (!user) {
      throw ForbiddenError("User not found");
    }
    await upsertUser({
      openId: user.openId,
      lastSignedIn: signedInAt
    });
    return user;
  }
};
var sdk = new SDKServer();

// server/_core/oauth.ts
function getQueryParam(req, key) {
  const value = req.query[key];
  return typeof value === "string" ? value : void 0;
}
function registerOAuthRoutes(app) {
  app.get("/api/oauth/callback", async (req, res) => {
    const code = getQueryParam(req, "code");
    const state = getQueryParam(req, "state");
    if (!code || !state) {
      res.status(400).json({ error: "code and state are required" });
      return;
    }
    try {
      const tokenResponse = await sdk.exchangeCodeForToken(code, state);
      const userInfo = await sdk.getUserInfo(tokenResponse.accessToken);
      if (!userInfo.openId) {
        res.status(400).json({ error: "openId missing from user info" });
        return;
      }
      await upsertUser({
        openId: userInfo.openId,
        name: userInfo.name || null,
        email: userInfo.email ?? null,
        loginMethod: userInfo.loginMethod ?? userInfo.platform ?? null,
        lastSignedIn: /* @__PURE__ */ new Date()
      });
      const sessionToken = await sdk.createSessionToken(userInfo.openId, {
        name: userInfo.name || "",
        expiresInMs: ONE_YEAR_MS
      });
      const cookieOptions = getSessionCookieOptions(req);
      res.cookie(COOKIE_NAME, sessionToken, { ...cookieOptions, maxAge: ONE_YEAR_MS });
      res.redirect(302, "/");
    } catch (error) {
      console.error("[OAuth] Callback failed", error);
      res.status(500).json({ error: "OAuth callback failed" });
    }
  });
}

// server/_core/systemRouter.ts
import { z } from "zod";

// server/_core/notification.ts
import { TRPCError } from "@trpc/server";
var TITLE_MAX_LENGTH = 1200;
var CONTENT_MAX_LENGTH = 2e4;
var trimValue = (value) => value.trim();
var isNonEmptyString2 = (value) => typeof value === "string" && value.trim().length > 0;
var buildEndpointUrl = (baseUrl) => {
  const normalizedBase = baseUrl.endsWith("/") ? baseUrl : `${baseUrl}/`;
  return new URL(
    "webdevtoken.v1.WebDevService/SendNotification",
    normalizedBase
  ).toString();
};
var validatePayload = (input) => {
  if (!isNonEmptyString2(input.title)) {
    throw new TRPCError({
      code: "BAD_REQUEST",
      message: "Notification title is required."
    });
  }
  if (!isNonEmptyString2(input.content)) {
    throw new TRPCError({
      code: "BAD_REQUEST",
      message: "Notification content is required."
    });
  }
  const title = trimValue(input.title);
  const content = trimValue(input.content);
  if (title.length > TITLE_MAX_LENGTH) {
    throw new TRPCError({
      code: "BAD_REQUEST",
      message: `Notification title must be at most ${TITLE_MAX_LENGTH} characters.`
    });
  }
  if (content.length > CONTENT_MAX_LENGTH) {
    throw new TRPCError({
      code: "BAD_REQUEST",
      message: `Notification content must be at most ${CONTENT_MAX_LENGTH} characters.`
    });
  }
  return { title, content };
};
async function notifyOwner(payload) {
  const { title, content } = validatePayload(payload);
  if (!ENV.forgeApiUrl) {
    throw new TRPCError({
      code: "INTERNAL_SERVER_ERROR",
      message: "Notification service URL is not configured."
    });
  }
  if (!ENV.forgeApiKey) {
    throw new TRPCError({
      code: "INTERNAL_SERVER_ERROR",
      message: "Notification service API key is not configured."
    });
  }
  const endpoint = buildEndpointUrl(ENV.forgeApiUrl);
  try {
    const response = await fetch(endpoint, {
      method: "POST",
      headers: {
        accept: "application/json",
        authorization: `Bearer ${ENV.forgeApiKey}`,
        "content-type": "application/json",
        "connect-protocol-version": "1"
      },
      body: JSON.stringify({ title, content })
    });
    if (!response.ok) {
      const detail = await response.text().catch(() => "");
      console.warn(
        `[Notification] Failed to notify owner (${response.status} ${response.statusText})${detail ? `: ${detail}` : ""}`
      );
      return false;
    }
    return true;
  } catch (error) {
    console.warn("[Notification] Error calling notification service:", error);
    return false;
  }
}

// server/_core/trpc.ts
import { initTRPC, TRPCError as TRPCError2 } from "@trpc/server";
import superjson from "superjson";
var t = initTRPC.context().create({
  transformer: superjson
});
var router = t.router;
var publicProcedure = t.procedure;
var requireUser = t.middleware(async (opts) => {
  const { ctx, next } = opts;
  if (!ctx.user) {
    throw new TRPCError2({ code: "UNAUTHORIZED", message: UNAUTHED_ERR_MSG });
  }
  return next({
    ctx: {
      ...ctx,
      user: ctx.user
    }
  });
});
var protectedProcedure = t.procedure.use(requireUser);
var adminProcedure = t.procedure.use(
  t.middleware(async (opts) => {
    const { ctx, next } = opts;
    if (!ctx.user || ctx.user.role !== "admin") {
      throw new TRPCError2({ code: "FORBIDDEN", message: NOT_ADMIN_ERR_MSG });
    }
    return next({
      ctx: {
        ...ctx,
        user: ctx.user
      }
    });
  })
);

// server/_core/systemRouter.ts
var systemRouter = router({
  health: publicProcedure.input(
    z.object({
      timestamp: z.number().min(0, "timestamp cannot be negative")
    })
  ).query(() => ({
    ok: true
  })),
  notifyOwner: adminProcedure.input(
    z.object({
      title: z.string().min(1, "title is required"),
      content: z.string().min(1, "content is required")
    })
  ).mutation(async ({ input }) => {
    const delivered = await notifyOwner(input);
    return {
      success: delivered
    };
  })
});

// server/routers.ts
import { TRPCError as TRPCError3 } from "@trpc/server";
import { z as z2 } from "zod";

// server/railwayDb.ts
import mysql from "mysql2/promise";
var RAILWAY_DB_URL = "mysql://root:XvWOlrgTfcJLaDjfywmnSHRNdwEhktSS@gondola.proxy.rlwy.net:46765/railway";
var _pool = null;
async function getPool() {
  if (!_pool) {
    try {
      _pool = mysql.createPool({
        uri: RAILWAY_DB_URL,
        waitForConnections: true,
        connectionLimit: 10,
        queueLimit: 0
      });
      console.log("[Railway DB] Pool created successfully");
    } catch (error) {
      console.warn("[Railway DB] Failed to create pool:", error);
      _pool = null;
    }
  }
  return _pool;
}
async function executeRawQuery(query, params = []) {
  const pool = await getPool();
  if (!pool) {
    console.warn("[Railway DB] Pool not available");
    return [];
  }
  try {
    const [rows] = await pool.query(query, params);
    return rows;
  } catch (error) {
    console.error("[Railway DB] Query error:", error);
    return [];
  }
}
async function getAllRailwayUsers(limit = 100, offset = 0) {
  try {
    const rows = await executeRawQuery(
      `SELECT * FROM users ORDER BY id DESC LIMIT ? OFFSET ?`,
      [limit, offset]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching users:", error);
    return [];
  }
}
async function getRailwayUserById(id) {
  try {
    const rows = await executeRawQuery(
      `SELECT * FROM users WHERE id = ?`,
      [id]
    );
    return rows[0] || null;
  } catch (error) {
    console.warn("[Railway DB] Error fetching user:", error);
    return null;
  }
}
async function getRailwayUserCount() {
  try {
    const rows = await executeRawQuery(`SELECT COUNT(*) as count FROM users`);
    return rows[0]?.count || 0;
  } catch (error) {
    console.warn("[Railway DB] Error counting users:", error);
    return 0;
  }
}
async function updateRailwayUserBalance(userId, balance) {
  try {
    await executeRawQuery(
      `UPDATE users SET balance = ? WHERE id = ?`,
      [balance, userId]
    );
    return true;
  } catch (error) {
    console.warn("[Railway DB] Error updating balance:", error);
    return false;
  }
}
async function getRailwayWithdrawals(status, limit = 100) {
  try {
    let query = `SELECT w.*, u.name as user_name, u.email as user_email 
                 FROM withdrawals w 
                 LEFT JOIN users u ON w.user_id = u.id 
                 ORDER BY w.id DESC LIMIT ?`;
    let params = [limit];
    if (status) {
      query = `SELECT w.*, u.name as user_name, u.email as user_email 
               FROM withdrawals w 
               LEFT JOIN users u ON w.user_id = u.id 
               WHERE w.status = ? 
               ORDER BY w.id DESC LIMIT ?`;
      params = [status, limit];
    }
    const rows = await executeRawQuery(query, params);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching withdrawals:", error);
    return [];
  }
}
async function updateRailwayWithdrawalStatus(withdrawalId, status, receiptUrl) {
  try {
    if (receiptUrl) {
      await executeRawQuery(
        `UPDATE withdrawals SET status = ?, receipt_url = ?, updated_at = NOW() WHERE id = ?`,
        [status, receiptUrl, withdrawalId]
      );
    } else {
      await executeRawQuery(
        `UPDATE withdrawals SET status = ?, updated_at = NOW() WHERE id = ?`,
        [status, withdrawalId]
      );
    }
    return true;
  } catch (error) {
    console.warn("[Railway DB] Error updating withdrawal:", error);
    return false;
  }
}
async function getRailwayWithdrawalStats() {
  try {
    const pending = await executeRawQuery(
      `SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM withdrawals WHERE status = 'pending'`
    );
    const approved = await executeRawQuery(
      `SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM withdrawals WHERE status = 'approved'`
    );
    const rejected = await executeRawQuery(
      `SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM withdrawals WHERE status = 'rejected'`
    );
    return {
      pending: { count: pending[0]?.count || 0, total: pending[0]?.total || 0 },
      approved: { count: approved[0]?.count || 0, total: approved[0]?.total || 0 },
      rejected: { count: rejected[0]?.count || 0, total: rejected[0]?.total || 0 }
    };
  } catch (error) {
    console.warn("[Railway DB] Error fetching withdrawal stats:", error);
    return {
      pending: { count: 0, total: 0 },
      approved: { count: 0, total: 0 },
      rejected: { count: 0, total: 0 }
    };
  }
}
async function getRailwayReferrals(limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT r.*, 
              u1.name as referrer_name, u1.email as referrer_email,
              u2.name as referred_name, u2.email as referred_email
       FROM referrals r
       LEFT JOIN users u1 ON r.referrer_id = u1.id
       LEFT JOIN users u2 ON r.referred_id = u2.id
       ORDER BY r.id DESC LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching referrals:", error);
    return [];
  }
}
async function getRailwayRanking(limit = 1e4) {
  try {
    const rows = await executeRawQuery(
      `SELECT id, name, email, points, daily_points, balance, photo_url, profile_picture 
       FROM users 
       ORDER BY daily_points DESC 
       LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching ranking:", error);
    return [];
  }
}
async function updateDailyPoints(userId, points, operation) {
  try {
    let query = "";
    if (operation === "add") {
      query = `UPDATE users SET daily_points = daily_points + ?, updated_at = NOW() WHERE id = ?`;
    } else if (operation === "subtract") {
      query = `UPDATE users SET daily_points = daily_points - ?, updated_at = NOW() WHERE id = ?`;
    } else {
      query = `UPDATE users SET daily_points = ?, updated_at = NOW() WHERE id = ?`;
    }
    await executeRawQuery(query, [points, userId]);
    return true;
  } catch (error) {
    console.error("[Railway DB] Error updating daily points:", error);
    return false;
  }
}
async function getRailwayDashboardStats() {
  try {
    const userCount = await executeRawQuery(`SELECT COUNT(*) as count FROM users`);
    const totalPoints = await executeRawQuery(`SELECT COALESCE(SUM(points), 0) as total FROM users`);
    const totalBalance = await executeRawQuery(`SELECT COALESCE(SUM(balance), 0) as total FROM users`);
    const pendingWithdrawals = await executeRawQuery(
      `SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM withdrawals WHERE status = 'pending'`
    );
    const approvedWithdrawals = await executeRawQuery(
      `SELECT COALESCE(SUM(amount), 0) as total FROM withdrawals WHERE status = 'approved'`
    );
    const today = (/* @__PURE__ */ new Date()).toISOString().split("T")[0];
    const activeToday = await executeRawQuery(
      `SELECT COUNT(*) as count FROM users WHERE DATE(last_login_at) = ?`,
      [today]
    );
    return {
      totalUsers: userCount[0]?.count || 0,
      totalPoints: totalPoints[0]?.total || 0,
      totalBalance: totalBalance[0]?.total || 0,
      pendingWithdrawals: pendingWithdrawals[0]?.count || 0,
      pendingWithdrawalsAmount: pendingWithdrawals[0]?.total || 0,
      totalWithdrawn: approvedWithdrawals[0]?.total || 0,
      activeUsersToday: activeToday[0]?.count || 0
    };
  } catch (error) {
    console.warn("[Railway DB] Error fetching dashboard stats:", error);
    return {
      totalUsers: 0,
      totalPoints: 0,
      totalBalance: 0,
      pendingWithdrawals: 0,
      pendingWithdrawalsAmount: 0,
      totalWithdrawn: 0,
      activeUsersToday: 0
    };
  }
}
async function listRailwayTables() {
  try {
    const rows = await executeRawQuery(`SHOW TABLES`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error listing tables:", error);
    return [];
  }
}
async function describeRailwayTable(tableName) {
  try {
    const rows = await executeRawQuery(`DESCRIBE ${tableName}`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error describing table:", error);
    return [];
  }
}
async function createRailwayWithdrawal(data) {
  try {
    const now = (/* @__PURE__ */ new Date()).toISOString().slice(0, 19).replace("T", " ");
    const result = await executeRawQuery(
      `INSERT INTO withdrawals (user_id, amount, pix_type, pix_key, status, created_at, updated_at) 
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [data.userId, data.amount, data.pixType, data.pixKey, data.status || "pending", now, now]
    );
    return result;
  } catch (error) {
    console.error("[Railway DB] Error creating withdrawal:", error);
    throw error;
  }
}
async function getSystemSettings() {
  try {
    const rows = await executeRawQuery(`SELECT * FROM system_settings ORDER BY id`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching system settings:", error);
    return [];
  }
}
async function updateSystemSetting(key, value) {
  try {
    await executeRawQuery(
      `INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at) 
       VALUES (?, ?, NOW(), NOW()) 
       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()`,
      [key, value]
    );
    return true;
  } catch (error) {
    console.error("[Railway DB] Error updating system setting:", error);
    return false;
  }
}
async function getRouletteSettings() {
  try {
    const rows = await executeRawQuery(`SELECT * FROM roulette_settings ORDER BY id`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching roulette settings:", error);
    return [];
  }
}
async function updateRouletteSetting(key, value) {
  try {
    await executeRawQuery(
      `UPDATE roulette_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?`,
      [value, key]
    );
    return true;
  } catch (error) {
    console.error("[Railway DB] Error updating roulette setting:", error);
    return false;
  }
}
async function getRoulettePrizes() {
  try {
    const rows = await executeRawQuery(`SELECT * FROM roulette_prizes ORDER BY id`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching roulette prizes:", error);
    return [];
  }
}
async function updateRoulettePrize(id, prizeName, prizeValue, probability, isActive) {
  try {
    await executeRawQuery(
      `UPDATE roulette_prizes SET prize_name = ?, prize_value = ?, probability = ?, is_active = ? WHERE id = ?`,
      [prizeName, prizeValue, probability, isActive ? 1 : 0, id]
    );
    return true;
  } catch (error) {
    console.error("[Railway DB] Error updating roulette prize:", error);
    return false;
  }
}
async function getSpinHistory(limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT s.*, u.name as user_name, u.email as user_email 
       FROM spins s 
       LEFT JOIN users u ON s.user_id = u.id 
       ORDER BY s.id DESC LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching spin history:", error);
    return [];
  }
}
async function getDailyCheckins(limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT dc.*, u.name as user_name, u.email as user_email 
       FROM daily_checkin dc 
       LEFT JOIN users u ON dc.user_id = u.id 
       ORDER BY dc.id DESC LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching daily checkins:", error);
    return [];
  }
}
async function getDailyTasks() {
  try {
    const rows = await executeRawQuery(`SELECT * FROM daily_tasks ORDER BY id`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching daily tasks:", error);
    return [];
  }
}
async function updateDailyTask(id, data) {
  try {
    const updates = [];
    const params = [];
    if (data.task_name !== void 0) {
      updates.push("task_name = ?");
      params.push(data.task_name);
    }
    if (data.points_reward !== void 0) {
      updates.push("points_reward = ?");
      params.push(data.points_reward);
    }
    if (data.is_active !== void 0) {
      updates.push("is_active = ?");
      params.push(data.is_active ? 1 : 0);
    }
    if (updates.length === 0) return false;
    params.push(id);
    await executeRawQuery(
      `UPDATE daily_tasks SET ${updates.join(", ")} WHERE id = ?`,
      params
    );
    return true;
  } catch (error) {
    console.error("[Railway DB] Error updating daily task:", error);
    return false;
  }
}
async function getNotifications(limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT n.*, u.name as user_name, u.email as user_email 
       FROM notifications n 
       LEFT JOIN users u ON n.user_id = u.id 
       ORDER BY n.created_at DESC LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching notifications:", error);
    return [];
  }
}
async function createNotification(userId, title, message, type = "info", points = 0) {
  try {
    await executeRawQuery(
      `INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())`,
      [userId, title, message, type]
    );
    if (points > 0) {
      await executeRawQuery(
        `UPDATE users SET points = points + ? WHERE id = ?`,
        [points, userId]
      );
      console.log(`[Railway DB] Added ${points} points to user ${userId}`);
    }
    return true;
  } catch (error) {
    console.error("[Railway DB] Error creating notification:", error);
    return false;
  }
}
async function sendBroadcastNotification(title, message, type = "info", points = 0) {
  try {
    const users2 = await executeRawQuery(`SELECT id FROM users`);
    for (const user of users2) {
      await createNotification(user.id, title, message, type, points);
    }
    return true;
  } catch (error) {
    console.error("[Railway DB] Error sending broadcast notification:", error);
    return false;
  }
}
async function getDeviceBlacklist(limit = 100) {
  try {
    const rows = await executeRawQuery(`SELECT * FROM device_blacklist ORDER BY created_at DESC LIMIT ?`, [limit]);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching device blacklist:", error);
    return [];
  }
}
async function addToDeviceBlacklist(deviceId, reason) {
  try {
    await executeRawQuery(
      `INSERT INTO device_blacklist (device_id, reason, created_at) VALUES (?, ?, NOW())`,
      [deviceId, reason]
    );
    return true;
  } catch (error) {
    console.error("[Railway DB] Error adding to device blacklist:", error);
    return false;
  }
}
async function removeFromDeviceBlacklist(id) {
  try {
    await executeRawQuery(`DELETE FROM device_blacklist WHERE id = ?`, [id]);
    return true;
  } catch (error) {
    console.error("[Railway DB] Error removing from device blacklist:", error);
    return false;
  }
}
async function getSecurityViolations(limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT sv.*, u.name as user_name, u.email as user_email 
       FROM security_violations sv 
       LEFT JOIN users u ON sv.user_id = u.id 
       ORDER BY sv.created_at DESC LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching security violations:", error);
    return [];
  }
}
async function getActiveSessions(limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT as2.*, u.name as user_name, u.email as user_email 
       FROM active_sessions as2 
       LEFT JOIN users u ON as2.user_id = u.id 
       ORDER BY as2.id DESC LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching active sessions:", error);
    return [];
  }
}
async function terminateSession(sessionId) {
  try {
    await executeRawQuery(`DELETE FROM active_sessions WHERE id = ?`, [sessionId]);
    return true;
  } catch (error) {
    console.error("[Railway DB] Error terminating session:", error);
    return false;
  }
}
async function getMonetag_Events(limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT me.*, u.name as user_name, u.email as user_email 
       FROM monetag_events me 
       LEFT JOIN users u ON me.user_id = u.id 
       ORDER BY me.id DESC LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching monetag events:", error);
    return [];
  }
}
async function getMonetag_Postbacks(limit = 100) {
  try {
    const rows = await executeRawQuery(`SELECT * FROM monetag_postbacks ORDER BY id DESC LIMIT ?`, [limit]);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching monetag postbacks:", error);
    return [];
  }
}
async function getPixPayments(limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT pp.*, u.name as user_name, u.email as user_email 
       FROM pix_payments pp 
       LEFT JOIN users u ON pp.user_id = u.id 
       ORDER BY pp.id DESC LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching pix payments:", error);
    return [];
  }
}
async function getPointTransactions(limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT pt.*, u.name as user_name, u.email as user_email 
       FROM point_transactions pt 
       LEFT JOIN users u ON pt.user_id = u.id 
       ORDER BY pt.id DESC LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching point transactions:", error);
    return [];
  }
}
async function getTrackingEvents(limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT te.*, u.name as user_name, u.email as user_email 
       FROM tracking_events te 
       LEFT JOIN users u ON te.user_id = u.id 
       ORDER BY te.id DESC LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching tracking events:", error);
    return [];
  }
}
async function getAdminLogs(limit = 100) {
  try {
    const rows = await executeRawQuery(`SELECT * FROM admin_logs ORDER BY id DESC LIMIT ?`, [limit]);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching admin logs:", error);
    return [];
  }
}
async function createAdminLog(action, details, adminId) {
  try {
    await executeRawQuery(
      `INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, NOW())`,
      [adminId || null, action, details]
    );
    return true;
  } catch (error) {
    console.error("[Railway DB] Error creating admin log:", error);
    return false;
  }
}
async function getWithdrawalRequests(limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT wr.*, u.name as user_name, u.email as user_email 
       FROM withdrawal_requests wr 
       LEFT JOIN users u ON wr.user_id = u.id 
       ORDER BY wr.id DESC LIMIT ?`,
      [limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching withdrawal requests:", error);
    return [];
  }
}
async function updateUserPoints(userId, points, operation) {
  try {
    let query = "";
    if (operation === "add") {
      query = `UPDATE users SET points = points + ?, updated_at = NOW() WHERE id = ?`;
    } else if (operation === "subtract") {
      query = `UPDATE users SET points = points - ?, updated_at = NOW() WHERE id = ?`;
    } else {
      query = `UPDATE users SET points = ?, updated_at = NOW() WHERE id = ?`;
    }
    await executeRawQuery(query, [points, userId]);
    return true;
  } catch (error) {
    console.error("[Railway DB] Error updating user points:", error);
    return false;
  }
}
async function banUser(userId, reason) {
  try {
    const user = await executeRawQuery(`SELECT device_id FROM users WHERE id = ?`, [userId]);
    if (user[0]?.device_id) {
      await addToDeviceBlacklist(user[0].device_id, reason);
    }
    await createAdminLog("BAN_USER", `Usu\xE1rio ${userId} banido. Motivo: ${reason}`);
    return true;
  } catch (error) {
    console.error("[Railway DB] Error banning user:", error);
    return false;
  }
}
async function deleteAllUsers() {
  try {
    const countResult = await executeRawQuery(`SELECT COUNT(*) as count FROM users`);
    const count = countResult[0]?.count || 0;
    await executeRawQuery(`DELETE FROM withdrawals`);
    await executeRawQuery(`DELETE FROM point_transactions`);
    await executeRawQuery(`DELETE FROM referrals`);
    await executeRawQuery(`DELETE FROM daily_tasks`);
    await executeRawQuery(`DELETE FROM users`);
    console.log(`[Railway DB] Deleted all users. Count: ${count}`);
    return { success: true, count };
  } catch (error) {
    console.error("[Railway DB] Error deleting all users:", error);
    return { success: false, count: 0 };
  }
}
async function getUserPointsHistory(userId, limit = 100) {
  try {
    const rows = await executeRawQuery(
      `SELECT * FROM points_history 
       WHERE user_id = ? 
       ORDER BY created_at DESC 
       LIMIT ?`,
      [userId, limit]
    );
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching user points history:", error);
    return [];
  }
}

// server/routers.ts
var adminProcedure2 = publicProcedure;
var appRouter = router({
  system: systemRouter,
  auth: router({
    me: publicProcedure.query((opts) => opts.ctx.user),
    logout: publicProcedure.mutation(({ ctx }) => {
      const cookieOptions = getSessionCookieOptions(ctx.req);
      ctx.res.clearCookie(COOKIE_NAME, { ...cookieOptions, maxAge: -1 });
      return { success: true };
    })
  }),
  // ============= DASHBOARD =============
  dashboard: router({
    stats: adminProcedure2.query(async () => {
      const stats = await getRailwayDashboardStats();
      return stats;
    })
  }),
  // ============= APP USERS =============
  appUsers: router({
    list: adminProcedure2.input(z2.object({
      limit: z2.number().optional().default(100),
      offset: z2.number().optional().default(0),
      search: z2.string().optional()
    })).query(async ({ input }) => {
      const users2 = await getAllRailwayUsers(input.limit, input.offset);
      const total = await getRailwayUserCount();
      return { users: users2, total };
    }),
    getById: adminProcedure2.input(z2.object({ id: z2.number() })).query(async ({ input }) => {
      const user = await getRailwayUserById(input.id);
      if (!user) {
        throw new TRPCError3({ code: "NOT_FOUND", message: "Usu\xE1rio n\xE3o encontrado" });
      }
      return user;
    }),
    updatePoints: adminProcedure2.input(z2.object({
      id: z2.number(),
      points: z2.number(),
      operation: z2.enum(["add", "subtract", "set"]).optional().default("set")
    })).mutation(async ({ input }) => {
      await updateUserPoints(input.id, input.points, input.operation);
      await createAdminLog("UPDATE_POINTS", `Pontos do usu\xE1rio ${input.id} atualizados: ${input.operation} ${input.points}`);
      return { success: true };
    }),
    updateBalance: adminProcedure2.input(z2.object({
      id: z2.number(),
      balance: z2.number()
    })).mutation(async ({ input }) => {
      await updateRailwayUserBalance(input.id, input.balance);
      await createAdminLog("UPDATE_BALANCE", `Saldo do usu\xE1rio ${input.id} atualizado para ${input.balance}`);
      return { success: true };
    }),
    ban: adminProcedure2.input(z2.object({
      id: z2.number(),
      reason: z2.string()
    })).mutation(async ({ input }) => {
      await banUser(input.id, input.reason);
      return { success: true };
    }),
    deleteAll: adminProcedure2.input(z2.object({
      confirmText: z2.string()
    })).mutation(async ({ input }) => {
      if (input.confirmText !== "EXCLUIR TODOS") {
        throw new TRPCError3({ code: "BAD_REQUEST", message: "Texto de confirma\xE7\xE3o incorreto" });
      }
      const result = await deleteAllUsers();
      await createAdminLog("DELETE_ALL_USERS", `Todos os usu\xE1rios foram exclu\xEDdos. Total: ${result.count}`);
      return { success: true, count: result.count };
    })
  }),
  // ============= POINT TRANSACTIONS =============
  pointTransactions: router({
    list: adminProcedure2.input(z2.object({
      userId: z2.number().optional(),
      limit: z2.number().optional().default(100)
    })).query(async ({ input }) => {
      const transactions = await getPointTransactions(input.limit);
      return transactions;
    }),
    userHistory: adminProcedure2.input(z2.object({
      userId: z2.number(),
      limit: z2.number().optional().default(100)
    })).query(async ({ input }) => {
      const history = await getUserPointsHistory(input.userId, input.limit);
      return history;
    })
  }),
  // ============= WITHDRAWALS =============
  withdrawals: router({
    list: adminProcedure2.input(z2.object({
      status: z2.string().optional(),
      limit: z2.number().optional().default(100)
    })).query(async ({ input }) => {
      const withdrawals2 = await getRailwayWithdrawals(input.status, input.limit);
      return withdrawals2;
    }),
    updateStatus: adminProcedure2.input(z2.object({
      id: z2.number(),
      status: z2.enum(["pending", "approved", "rejected"]),
      receiptUrl: z2.string().optional()
    })).mutation(async ({ input }) => {
      await updateRailwayWithdrawalStatus(input.id, input.status, input.receiptUrl);
      await createAdminLog("UPDATE_WITHDRAWAL", `Saque ${input.id} atualizado para ${input.status}${input.receiptUrl ? " com comprovante" : ""}`);
      return { success: true };
    }),
    stats: adminProcedure2.query(async () => {
      const stats = await getRailwayWithdrawalStats();
      return stats;
    }),
    create: adminProcedure2.input(z2.object({
      userId: z2.number(),
      amount: z2.number(),
      pixType: z2.string(),
      pixKey: z2.string(),
      status: z2.string().optional().default("pending")
    })).mutation(async ({ input }) => {
      await createRailwayWithdrawal(input);
      await createAdminLog("CREATE_WITHDRAWAL", `Saque criado para usu\xE1rio ${input.userId}: R$ ${input.amount}`);
      return { success: true };
    }),
    requests: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const requests = await getWithdrawalRequests(input.limit);
      return requests;
    })
  }),
  // ============= REFERRALS =============
  referrals: router({
    list: adminProcedure2.input(z2.object({
      limit: z2.number().optional().default(100)
    })).query(async ({ input }) => {
      const referrals2 = await getRailwayReferrals(input.limit);
      return referrals2;
    })
  }),
  // ============= RANKING =============
  ranking: router({
    list: adminProcedure2.input(z2.object({
      limit: z2.number().optional().default(1e4)
    })).query(async ({ input }) => {
      const ranking = await getRailwayRanking(input.limit);
      return ranking;
    }),
    updateDailyPoints: adminProcedure2.input(z2.object({
      id: z2.number(),
      points: z2.number(),
      operation: z2.enum(["add", "subtract", "set"]).optional().default("set")
    })).mutation(async ({ input }) => {
      await updateDailyPoints(input.id, input.points, input.operation);
      await createAdminLog("UPDATE_DAILY_POINTS", `Pontos di\xE1rios do usu\xE1rio ${input.id} atualizados: ${input.operation} ${input.points}`);
      return { success: true };
    })
  }),
  // ============= SYSTEM SETTINGS =============
  settings: router({
    list: adminProcedure2.query(async () => {
      const settings = await getSystemSettings();
      return settings;
    }),
    update: adminProcedure2.input(z2.object({
      key: z2.string(),
      value: z2.string()
    })).mutation(async ({ input }) => {
      await updateSystemSetting(input.key, input.value);
      await createAdminLog("UPDATE_SETTING", `Configura\xE7\xE3o ${input.key} atualizada para ${input.value}`);
      return { success: true };
    })
  }),
  // ============= ROULETTE =============
  roulette: router({
    settings: adminProcedure2.query(async () => {
      const settings = await getRouletteSettings();
      return settings;
    }),
    updateSetting: adminProcedure2.input(z2.object({
      key: z2.string(),
      value: z2.string()
    })).mutation(async ({ input }) => {
      await updateRouletteSetting(input.key, input.value);
      await createAdminLog("UPDATE_ROULETTE", `Roleta ${input.key} atualizada para ${input.value}`);
      return { success: true };
    }),
    prizes: adminProcedure2.query(async () => {
      const prizes = await getRoulettePrizes();
      return prizes;
    }),
    updatePrize: adminProcedure2.input(z2.object({
      id: z2.number(),
      prizeName: z2.string(),
      prizeValue: z2.number(),
      probability: z2.number(),
      isActive: z2.boolean()
    })).mutation(async ({ input }) => {
      await updateRoulettePrize(input.id, input.prizeName, input.prizeValue, input.probability, input.isActive);
      await createAdminLog("UPDATE_PRIZE", `Pr\xEAmio ${input.id} atualizado`);
      return { success: true };
    }),
    spinHistory: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const history = await getSpinHistory(input.limit);
      return history;
    })
  }),
  // ============= DAILY CHECKIN =============
  checkin: router({
    list: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const checkins = await getDailyCheckins(input.limit);
      return checkins;
    })
  }),
  // ============= DAILY TASKS =============
  tasks: router({
    list: adminProcedure2.query(async () => {
      const tasks = await getDailyTasks();
      return tasks;
    }),
    update: adminProcedure2.input(z2.object({
      id: z2.number(),
      taskName: z2.string().optional(),
      pointsReward: z2.number().optional(),
      isActive: z2.boolean().optional()
    })).mutation(async ({ input }) => {
      await updateDailyTask(input.id, {
        task_name: input.taskName,
        points_reward: input.pointsReward,
        is_active: input.isActive
      });
      await createAdminLog("UPDATE_TASK", `Tarefa ${input.id} atualizada`);
      return { success: true };
    })
  }),
  // ============= NOTIFICATIONS =============
  notifications: router({
    list: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const notifications2 = await getNotifications(input.limit);
      return notifications2;
    }),
    create: adminProcedure2.input(z2.object({
      userId: z2.number(),
      title: z2.string(),
      message: z2.string(),
      type: z2.string().optional().default("info"),
      points: z2.number().optional().default(0)
    })).mutation(async ({ input }) => {
      await createNotification(input.userId, input.title, input.message, input.type, input.points);
      const pointsMsg = input.points > 0 ? ` (+${input.points} pontos)` : "";
      await createAdminLog("CREATE_NOTIFICATION", `Notifica\xE7\xE3o enviada para usu\xE1rio ${input.userId}${pointsMsg}`);
      return { success: true };
    }),
    broadcast: adminProcedure2.input(z2.object({
      title: z2.string(),
      message: z2.string(),
      type: z2.string().optional().default("info"),
      points: z2.number().optional().default(0)
    })).mutation(async ({ input }) => {
      await sendBroadcastNotification(input.title, input.message, input.type, input.points);
      const pointsMsg = input.points > 0 ? ` (+${input.points} pontos para todos)` : "";
      await createAdminLog("BROADCAST_NOTIFICATION", `Notifica\xE7\xE3o broadcast enviada: ${input.title}${pointsMsg}`);
      return { success: true };
    })
  }),
  // ============= DEVICE BLACKLIST =============
  blacklist: router({
    list: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const devices = await getDeviceBlacklist(input.limit);
      return devices;
    }),
    add: adminProcedure2.input(z2.object({
      deviceId: z2.string(),
      reason: z2.string()
    })).mutation(async ({ input }) => {
      await addToDeviceBlacklist(input.deviceId, input.reason);
      await createAdminLog("ADD_BLACKLIST", `Dispositivo ${input.deviceId} adicionado \xE0 blacklist`);
      return { success: true };
    }),
    remove: adminProcedure2.input(z2.object({ id: z2.number() })).mutation(async ({ input }) => {
      await removeFromDeviceBlacklist(input.id);
      await createAdminLog("REMOVE_BLACKLIST", `Dispositivo removido da blacklist`);
      return { success: true };
    })
  }),
  // ============= SECURITY VIOLATIONS =============
  security: router({
    violations: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const violations = await getSecurityViolations(input.limit);
      return violations;
    })
  }),
  // ============= ACTIVE SESSIONS =============
  sessions: router({
    list: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const sessions = await getActiveSessions(input.limit);
      return sessions;
    }),
    terminate: adminProcedure2.input(z2.object({ id: z2.number() })).mutation(async ({ input }) => {
      await terminateSession(input.id);
      await createAdminLog("TERMINATE_SESSION", `Sess\xE3o ${input.id} encerrada`);
      return { success: true };
    })
  }),
  // ============= MONETAG =============
  monetag: router({
    events: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const events = await getMonetag_Events(input.limit);
      return events;
    }),
    postbacks: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const postbacks = await getMonetag_Postbacks(input.limit);
      return postbacks;
    })
  }),
  // ============= PIX PAYMENTS =============
  pixPayments: router({
    list: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const payments = await getPixPayments(input.limit);
      return payments;
    })
  }),
  // ============= TRACKING EVENTS =============
  tracking: router({
    events: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const events = await getTrackingEvents(input.limit);
      return events;
    })
  }),
  // ============= ADMIN LOGS =============
  adminLogs: router({
    list: adminProcedure2.input(z2.object({ limit: z2.number().optional().default(100) })).query(async ({ input }) => {
      const logs = await getAdminLogs(input.limit);
      return logs;
    })
  }),
  // ============= DATABASE EXPLORER =============
  database: router({
    tables: adminProcedure2.query(async () => {
      const tables = await listRailwayTables();
      return tables;
    }),
    describe: adminProcedure2.input(z2.object({ table: z2.string() })).query(async ({ input }) => {
      const structure = await describeRailwayTable(input.table);
      return structure;
    }),
    query: adminProcedure2.input(z2.object({
      sql: z2.string(),
      params: z2.array(z2.any()).optional().default([])
    })).mutation(async ({ input }) => {
      const sqlLower = input.sql.toLowerCase().trim();
      if (!sqlLower.startsWith("select")) {
        throw new TRPCError3({
          code: "BAD_REQUEST",
          message: "Apenas queries SELECT s\xE3o permitidas"
        });
      }
      const result = await executeRawQuery(input.sql, input.params);
      return result;
    }),
    execute: adminProcedure2.input(z2.object({
      sql: z2.string(),
      params: z2.array(z2.any()).optional().default([])
    })).mutation(async ({ input }) => {
      const result = await executeRawQuery(input.sql, input.params);
      await createAdminLog("EXECUTE_SQL", `SQL executado: ${input.sql.substring(0, 100)}`);
      return result;
    })
  })
});

// server/_core/context.ts
async function createContext(opts) {
  let user = null;
  try {
    user = await sdk.authenticateRequest(opts.req);
  } catch (error) {
    user = null;
  }
  return {
    req: opts.req,
    res: opts.res,
    user
  };
}

// server/_core/vite.ts
import express from "express";
import fs from "fs";
import { nanoid } from "nanoid";
import path2 from "path";
import { createServer as createViteServer } from "vite";

// vite.config.ts
import { jsxLocPlugin } from "@builder.io/vite-plugin-jsx-loc";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import path from "path";
import { defineConfig } from "vite";
import { vitePluginManusRuntime } from "vite-plugin-manus-runtime";
var plugins = [react(), tailwindcss(), jsxLocPlugin(), vitePluginManusRuntime()];
var vite_config_default = defineConfig({
  plugins,
  resolve: {
    alias: {
      "@": path.resolve(import.meta.dirname, "client", "src"),
      "@shared": path.resolve(import.meta.dirname, "shared"),
      "@assets": path.resolve(import.meta.dirname, "attached_assets")
    }
  },
  envDir: path.resolve(import.meta.dirname),
  root: path.resolve(import.meta.dirname, "client"),
  publicDir: path.resolve(import.meta.dirname, "client", "public"),
  build: {
    outDir: path.resolve(import.meta.dirname, "dist/public"),
    emptyOutDir: true
  },
  server: {
    host: true,
    allowedHosts: [
      ".manuspre.computer",
      ".manus.computer",
      ".manus-asia.computer",
      ".manuscomputer.ai",
      ".manusvm.computer",
      "localhost",
      "127.0.0.1"
    ],
    fs: {
      strict: true,
      deny: ["**/.*"]
    }
  }
});

// server/_core/vite.ts
async function setupVite(app, server) {
  const serverOptions = {
    middlewareMode: true,
    hmr: { server },
    allowedHosts: true
  };
  const vite = await createViteServer({
    ...vite_config_default,
    configFile: false,
    server: serverOptions,
    appType: "custom"
  });
  app.use(vite.middlewares);
  app.use("*", async (req, res, next) => {
    const url = req.originalUrl;
    try {
      const clientTemplate = path2.resolve(
        import.meta.dirname,
        "../..",
        "client",
        "index.html"
      );
      let template = await fs.promises.readFile(clientTemplate, "utf-8");
      template = template.replace(
        `src="/src/main.tsx"`,
        `src="/src/main.tsx?v=${nanoid()}"`
      );
      const page = await vite.transformIndexHtml(url, template);
      res.status(200).set({ "Content-Type": "text/html" }).end(page);
    } catch (e) {
      vite.ssrFixStacktrace(e);
      next(e);
    }
  });
}
function serveStatic(app) {
  const distPath = process.env.NODE_ENV === "development" ? path2.resolve(import.meta.dirname, "../..", "dist", "public") : path2.resolve(import.meta.dirname, "public");
  if (!fs.existsSync(distPath)) {
    console.error(
      `Could not find the build directory: ${distPath}, make sure to build the client first`
    );
  }
  app.use(express.static(distPath));
  app.use("*", (_req, res) => {
    res.sendFile(path2.resolve(distPath, "index.html"));
  });
}

// server/_core/index.ts
function isPortAvailable(port) {
  return new Promise((resolve) => {
    const server = net.createServer();
    server.listen(port, () => {
      server.close(() => resolve(true));
    });
    server.on("error", () => resolve(false));
  });
}
async function findAvailablePort(startPort = 3e3) {
  for (let port = startPort; port < startPort + 20; port++) {
    if (await isPortAvailable(port)) {
      return port;
    }
  }
  throw new Error(`No available port found starting from ${startPort}`);
}
async function startServer() {
  const app = express2();
  const server = createServer(app);
  app.use(express2.json({ limit: "50mb" }));
  app.use(express2.urlencoded({ limit: "50mb", extended: true }));
  registerOAuthRoutes(app);
  app.use(
    "/api/trpc",
    createExpressMiddleware({
      router: appRouter,
      createContext
    })
  );
  if (process.env.NODE_ENV === "development") {
    await setupVite(app, server);
  } else {
    serveStatic(app);
  }
  const preferredPort = parseInt(process.env.PORT || "3000");
  const port = await findAvailablePort(preferredPort);
  if (port !== preferredPort) {
    console.log(`Port ${preferredPort} is busy, using port ${port} instead`);
  }
  server.listen(port, () => {
    console.log(`Server running on http://localhost:${port}/`);
  });
}
startServer().catch(console.error);
