import { describe, expect, it } from "vitest";
import { appRouter } from "./routers";
import type { TrpcContext } from "./_core/context";

type AuthenticatedUser = NonNullable<TrpcContext["user"]>;

function createAdminContext(): { ctx: TrpcContext } {
  const user: AuthenticatedUser = {
    id: 1,
    openId: "admin-test",
    email: "admin@youngmoney.com",
    name: "Admin Test",
    loginMethod: "manus",
    role: "admin",
    createdAt: new Date(),
    updatedAt: new Date(),
    lastSignedIn: new Date(),
  };

  const ctx: TrpcContext = {
    user,
    req: {
      protocol: "https",
      headers: {},
    } as TrpcContext["req"],
    res: {
      clearCookie: () => {},
    } as TrpcContext["res"],
  };

  return { ctx };
}

function createPublicContext(): { ctx: TrpcContext } {
  const ctx: TrpcContext = {
    user: null,
    req: {
      protocol: "https",
      headers: {},
    } as TrpcContext["req"],
    res: {
      clearCookie: () => {},
    } as TrpcContext["res"],
  };

  return { ctx };
}

describe("Admin Dashboard", () => {
  it("should return dashboard stats", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    const stats = await caller.dashboard.stats();

    expect(stats).toBeDefined();
    expect(stats).toHaveProperty("totalUsers");
    expect(stats).toHaveProperty("totalPoints");
    expect(stats).toHaveProperty("totalBalance");
    expect(stats).toHaveProperty("pendingWithdrawals");
    expect(stats).toHaveProperty("totalWithdrawn");
    expect(stats).toHaveProperty("activeUsersToday");
    expect(typeof stats.totalUsers).toBe("number");
    // totalPoints pode ser string (BigInt do MySQL) ou number
    expect(["number", "string"].includes(typeof stats.totalPoints)).toBe(true);
  });

  it("should return stats even for public access (no auth required)", async () => {
    const { ctx } = createPublicContext();
    const caller = appRouter.createCaller(ctx);

    const stats = await caller.dashboard.stats();
    expect(stats).toBeDefined();
    expect(stats).toHaveProperty("totalUsers");
  });
});

describe("App Users Management", () => {
  it("should list app users", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    const result = await caller.appUsers.list({ limit: 10, offset: 0 });

    expect(result).toBeDefined();
    expect(result).toHaveProperty("users");
    expect(result).toHaveProperty("total");
    expect(Array.isArray(result.users)).toBe(true);
    expect(typeof result.total).toBe("number");
  });

  it("should get user by id", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    // This may throw if user doesn't exist, which is expected behavior
    try {
      const user = await caller.appUsers.getById({ id: 1 });
      expect(user).toBeDefined();
    } catch (error: any) {
      expect(error.code).toBe("NOT_FOUND");
    }
  });
});

describe("Withdrawals Management", () => {
  it("should list withdrawals", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    const withdrawals = await caller.withdrawals.list({ limit: 10 });

    expect(withdrawals).toBeDefined();
    expect(Array.isArray(withdrawals)).toBe(true);
  });

  it("should get withdrawal stats", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    const stats = await caller.withdrawals.stats();

    expect(stats).toBeDefined();
    expect(stats).toHaveProperty("pending");
    expect(stats).toHaveProperty("approved");
    expect(stats).toHaveProperty("rejected");
    expect(stats.pending).toHaveProperty("count");
    expect(stats.pending).toHaveProperty("total");
  });
});

describe("Ranking", () => {
  it("should return ranking list", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    const ranking = await caller.ranking.list({ limit: 100 });

    expect(ranking).toBeDefined();
    expect(Array.isArray(ranking)).toBe(true);
  });
});

describe("Point Transactions", () => {
  it("should list point transactions", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    const transactions = await caller.pointTransactions.list({ limit: 50 });

    expect(transactions).toBeDefined();
    expect(Array.isArray(transactions)).toBe(true);
  });
});

describe("Referrals", () => {
  it("should list referrals", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    const referrals = await caller.referrals.list({ limit: 50 });

    expect(referrals).toBeDefined();
    expect(Array.isArray(referrals)).toBe(true);
  });
});

describe("Database Explorer", () => {
  it("should list tables", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    const tables = await caller.database.tables();

    expect(tables).toBeDefined();
    expect(Array.isArray(tables)).toBe(true);
  });

  it("should only allow SELECT queries", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    // DELETE should be rejected
    await expect(
      caller.database.query({ sql: "DELETE FROM users WHERE id = 1" })
    ).rejects.toThrow("Apenas queries SELECT são permitidas");

    // UPDATE should be rejected
    await expect(
      caller.database.query({ sql: "UPDATE users SET name = 'test'" })
    ).rejects.toThrow("Apenas queries SELECT são permitidas");
  });
});

describe("Authentication", () => {
  it("should return current user info", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    const user = await caller.auth.me();

    expect(user).toBeDefined();
    expect(user?.role).toBe("admin");
    expect(user?.email).toBe("admin@youngmoney.com");
  });

  it("should return null for unauthenticated users", async () => {
    const { ctx } = createPublicContext();
    const caller = appRouter.createCaller(ctx);

    const user = await caller.auth.me();
    expect(user).toBeNull();
  });

  it("should logout successfully", async () => {
    const { ctx } = createAdminContext();
    const caller = appRouter.createCaller(ctx);

    const result = await caller.auth.logout();

    expect(result).toEqual({ success: true });
  });
});
