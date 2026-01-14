import mysql from "mysql2/promise";

// Conexão direta com o banco MySQL do Railway
const RAILWAY_DB_URL = "mysql://root:XvWOlrgTfcJLaDjfywmnSHRNdwEhktSS@gondola.proxy.rlwy.net:46765/railway";

let _pool: mysql.Pool | null = null;

async function getPool() {
  if (!_pool) {
    try {
      _pool = mysql.createPool({
        uri: RAILWAY_DB_URL,
        waitForConnections: true,
        connectionLimit: 10,
        queueLimit: 0,
      });
      console.log("[Railway DB] Pool created successfully");
    } catch (error) {
      console.warn("[Railway DB] Failed to create pool:", error);
      _pool = null;
    }
  }
  return _pool;
}

// Função para executar queries SQL diretas
export async function executeRawQuery(query: string, params: any[] = []) {
  const pool = await getPool();
  if (!pool) {
    console.warn("[Railway DB] Pool not available");
    return [];
  }
  try {
    // Usar query() em vez de execute() para evitar problemas com tipos de parâmetros
    const [rows] = await pool.query(query, params);
    return rows as any[];
  } catch (error) {
    console.error("[Railway DB] Query error:", error);
    return [];
  }
}

// ============= USUÁRIOS DO APP (tabela users do Railway) =============

export async function getAllRailwayUsers(limit = 100, offset = 0) {
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

export async function getRailwayUserById(id: number) {
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

export async function getRailwayUserCount() {
  try {
    const rows = await executeRawQuery(`SELECT COUNT(*) as count FROM users`);
    return rows[0]?.count || 0;
  } catch (error) {
    console.warn("[Railway DB] Error counting users:", error);
    return 0;
  }
}

export async function updateRailwayUserPoints(userId: number, points: number) {
  try {
    await executeRawQuery(
      `UPDATE users SET points = ? WHERE id = ?`,
      [points, userId]
    );
    return true;
  } catch (error) {
    console.warn("[Railway DB] Error updating points:", error);
    return false;
  }
}

export async function updateRailwayUserBalance(userId: number, balance: number) {
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

// ============= TRANSAÇÕES DE PONTOS =============

export async function getRailwayPointTransactions(userId?: number, limit = 100) {
  try {
    let query = `SELECT * FROM point_transactions ORDER BY id DESC LIMIT ?`;
    let params: any[] = [limit];
    
    if (userId) {
      query = `SELECT * FROM point_transactions WHERE user_id = ? ORDER BY id DESC LIMIT ?`;
      params = [userId, limit];
    }
    
    const rows = await executeRawQuery(query, params);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching point transactions:", error);
    return [];
  }
}

// ============= SAQUES =============

export async function getRailwayWithdrawals(status?: string, limit = 100) {
  try {
    let query = `SELECT w.*, u.name as user_name, u.email as user_email 
                 FROM withdrawals w 
                 LEFT JOIN users u ON w.user_id = u.id 
                 ORDER BY w.id DESC LIMIT ?`;
    let params: any[] = [limit];
    
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

export async function updateRailwayWithdrawalStatus(withdrawalId: number, status: string, receiptUrl?: string) {
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

export async function getRailwayWithdrawalStats() {
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
      rejected: { count: rejected[0]?.count || 0, total: rejected[0]?.total || 0 },
    };
  } catch (error) {
    console.warn("[Railway DB] Error fetching withdrawal stats:", error);
    return {
      pending: { count: 0, total: 0 },
      approved: { count: 0, total: 0 },
      rejected: { count: 0, total: 0 },
    };
  }
}

// ============= REFERÊNCIAS/CONVITES =============

export async function getRailwayReferrals(limit = 100) {
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

// ============= RANKING =============

export async function getRailwayRanking(limit = 10000) {
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

// ============= ATUALIZAR PONTOS DIÁRIOS =============

export async function updateDailyPoints(userId: number, points: number, operation: 'add' | 'subtract' | 'set') {
  try {
    let query = '';
    if (operation === 'add') {
      query = `UPDATE users SET daily_points = daily_points + ?, updated_at = NOW() WHERE id = ?`;
    } else if (operation === 'subtract') {
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

// ============= ESTATÍSTICAS GERAIS =============

export async function getRailwayDashboardStats() {
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
    
    // Usuários ativos hoje
    const today = new Date().toISOString().split('T')[0];
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
      activeUsersToday: activeToday[0]?.count || 0,
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
      activeUsersToday: 0,
    };
  }
}

// ============= LISTAR TABELAS =============

export async function listRailwayTables() {
  try {
    const rows = await executeRawQuery(`SHOW TABLES`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error listing tables:", error);
    return [];
  }
}

// ============= DESCREVER TABELA =============

export async function describeRailwayTable(tableName: string) {
  try {
    const rows = await executeRawQuery(`DESCRIBE ${tableName}`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error describing table:", error);
    return [];
  }
}

// ============= CRIAR SAQUE =============

export async function createRailwayWithdrawal(data: {
  userId: number;
  amount: number;
  pixType: string;
  pixKey: string;
  status?: string;
}) {
  try {
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const result = await executeRawQuery(
      `INSERT INTO withdrawals (user_id, amount, pix_type, pix_key, status, created_at, updated_at) 
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [data.userId, data.amount, data.pixType, data.pixKey, data.status || 'pending', now, now]
    );
    return result;
  } catch (error) {
    console.error("[Railway DB] Error creating withdrawal:", error);
    throw error;
  }
}

// ============= BUSCAR CHAVE PIX DO USUÁRIO =============

export async function getRailwayUserPixKey(userId: number) {
  try {
    const rows = await executeRawQuery(
      `SELECT * FROM pix_keys WHERE user_id = ? ORDER BY id DESC LIMIT 1`,
      [userId]
    );
    return rows[0] || null;
  } catch (error) {
    console.warn("[Railway DB] Error fetching user pix key:", error);
    return null;
  }
}


// ============= CONFIGURAÇÕES DO SISTEMA =============

export async function getSystemSettings() {
  try {
    const rows = await executeRawQuery(`SELECT * FROM system_settings ORDER BY id`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching system settings:", error);
    return [];
  }
}

export async function updateSystemSetting(key: string, value: string) {
  try {
    // Usar INSERT ON DUPLICATE KEY UPDATE para criar se não existir
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

// ============= ROLETA - CONFIGURAÇÕES =============

export async function getRouletteSettings() {
  try {
    const rows = await executeRawQuery(`SELECT * FROM roulette_settings ORDER BY id`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching roulette settings:", error);
    return [];
  }
}

export async function updateRouletteSetting(key: string, value: string) {
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

// ============= ROLETA - PRÊMIOS =============

export async function getRoulettePrizes() {
  try {
    const rows = await executeRawQuery(`SELECT * FROM roulette_prizes ORDER BY id`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching roulette prizes:", error);
    return [];
  }
}

export async function updateRoulettePrize(id: number, prizeName: string, prizeValue: number, probability: number, isActive: boolean) {
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

// ============= SPINS (GIROS DA ROLETA) =============

export async function getSpinHistory(limit = 100) {
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

// ============= CHECK-IN DIÁRIO =============

export async function getDailyCheckins(limit = 100) {
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

// ============= TAREFAS DIÁRIAS =============

export async function getDailyTasks() {
  try {
    const rows = await executeRawQuery(`SELECT * FROM daily_tasks ORDER BY id`);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching daily tasks:", error);
    return [];
  }
}

export async function updateDailyTask(id: number, data: { task_name?: string; points_reward?: number; is_active?: boolean }) {
  try {
    const updates: string[] = [];
    const params: any[] = [];
    
    if (data.task_name !== undefined) {
      updates.push('task_name = ?');
      params.push(data.task_name);
    }
    if (data.points_reward !== undefined) {
      updates.push('points_reward = ?');
      params.push(data.points_reward);
    }
    if (data.is_active !== undefined) {
      updates.push('is_active = ?');
      params.push(data.is_active ? 1 : 0);
    }
    
    if (updates.length === 0) return false;
    
    params.push(id);
    await executeRawQuery(
      `UPDATE daily_tasks SET ${updates.join(', ')} WHERE id = ?`,
      params
    );
    return true;
  } catch (error) {
    console.error("[Railway DB] Error updating daily task:", error);
    return false;
  }
}

// ============= NOTIFICAÇÕES =============

export async function getNotifications(limit = 100) {
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

export async function createNotification(userId: number, title: string, message: string, type: string = 'info', points: number = 0) {
  try {
    await executeRawQuery(
      `INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())`,
      [userId, title, message, type]
    );
    
    // Adicionar pontos ao usuário se especificado
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

export async function sendBroadcastNotification(title: string, message: string, type: string = 'info', points: number = 0) {
  try {
    const users = await executeRawQuery(`SELECT id FROM users`);
    for (const user of users) {
      await createNotification(user.id, title, message, type, points);
    }
    return true;
  } catch (error) {
    console.error("[Railway DB] Error sending broadcast notification:", error);
    return false;
  }
}

// ============= BLACKLIST DE DISPOSITIVOS =============

export async function getDeviceBlacklist(limit = 100) {
  try {
    const rows = await executeRawQuery(`SELECT * FROM device_blacklist ORDER BY created_at DESC LIMIT ?`, [limit]);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching device blacklist:", error);
    return [];
  }
}

export async function addToDeviceBlacklist(deviceId: string, reason: string) {
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

export async function removeFromDeviceBlacklist(id: number) {
  try {
    await executeRawQuery(`DELETE FROM device_blacklist WHERE id = ?`, [id]);
    return true;
  } catch (error) {
    console.error("[Railway DB] Error removing from device blacklist:", error);
    return false;
  }
}

// ============= VIOLAÇÕES DE SEGURANÇA =============

export async function getSecurityViolations(limit = 100) {
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

// ============= SESSÕES ATIVAS =============

export async function getActiveSessions(limit = 100) {
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

export async function terminateSession(sessionId: number) {
  try {
    await executeRawQuery(`DELETE FROM active_sessions WHERE id = ?`, [sessionId]);
    return true;
  } catch (error) {
    console.error("[Railway DB] Error terminating session:", error);
    return false;
  }
}

// ============= MONETAG EVENTS =============

export async function getMonetag_Events(limit = 100) {
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

export async function getMonetag_Postbacks(limit = 100) {
  try {
    const rows = await executeRawQuery(`SELECT * FROM monetag_postbacks ORDER BY id DESC LIMIT ?`, [limit]);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching monetag postbacks:", error);
    return [];
  }
}

// ============= PIX PAYMENTS =============

export async function getPixPayments(limit = 100) {
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

// ============= POINT TRANSACTIONS =============

export async function getPointTransactions(limit = 100) {
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

// ============= TRACKING EVENTS =============

export async function getTrackingEvents(limit = 100) {
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

// ============= ADMIN LOGS =============

export async function getAdminLogs(limit = 100) {
  try {
    const rows = await executeRawQuery(`SELECT * FROM admin_logs ORDER BY id DESC LIMIT ?`, [limit]);
    return rows;
  } catch (error) {
    console.warn("[Railway DB] Error fetching admin logs:", error);
    return [];
  }
}

export async function createAdminLog(action: string, details: string, adminId?: number) {
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

// ============= WITHDRAWAL REQUESTS =============

export async function getWithdrawalRequests(limit = 100) {
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

// ============= ATUALIZAR PONTOS DO USUÁRIO =============

export async function updateUserPoints(userId: number, points: number, operation: 'add' | 'subtract' | 'set') {
  try {
    let query = '';
    if (operation === 'add') {
      query = `UPDATE users SET points = points + ?, updated_at = NOW() WHERE id = ?`;
    } else if (operation === 'subtract') {
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

// ============= BANIR/DESBANIR USUÁRIO =============

export async function banUser(userId: number, reason: string) {
  try {
    // Adiciona o dispositivo do usuário à blacklist
    const user = await executeRawQuery(`SELECT device_id FROM users WHERE id = ?`, [userId]);
    if (user[0]?.device_id) {
      await addToDeviceBlacklist(user[0].device_id, reason);
    }
    // Registra no log
    await createAdminLog('BAN_USER', `Usuário ${userId} banido. Motivo: ${reason}`);
    return true;
  } catch (error) {
    console.error("[Railway DB] Error banning user:", error);
    return false;
  }
}

export async function deleteAllUsers() {
  try {
    // Primeiro conta quantos usuários serão excluídos
    const countResult = await executeRawQuery(`SELECT COUNT(*) as count FROM users`);
    const count = countResult[0]?.count || 0;
    
    // Exclui todos os registros relacionados primeiro (para evitar erros de foreign key)
    await executeRawQuery(`DELETE FROM withdrawals`);
    await executeRawQuery(`DELETE FROM point_transactions`);
    await executeRawQuery(`DELETE FROM referrals`);
    await executeRawQuery(`DELETE FROM daily_tasks`);
    
    // Exclui todos os usuários
    await executeRawQuery(`DELETE FROM users`);
    
    console.log(`[Railway DB] Deleted all users. Count: ${count}`);
    return { success: true, count };
  } catch (error) {
    console.error("[Railway DB] Error deleting all users:", error);
    return { success: false, count: 0 };
  }
}


// ============= HISTÓRICO DE PONTOS DO USUÁRIO =============

export async function getUserPointsHistory(userId: number, limit = 100) {
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
