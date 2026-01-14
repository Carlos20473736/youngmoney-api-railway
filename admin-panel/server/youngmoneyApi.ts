import axios, { AxiosInstance } from 'axios';

/**
 * Cliente para integração com a API do Young Money no Railway
 * Base URL: https://youngmoney-api-railway-production.up.railway.app/
 */

const YOUNGMONEY_API_BASE_URL = process.env.YOUNGMONEY_API_URL || 'https://youngmoney-api-railway-production.up.railway.app';

class YoungMoneyApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: YOUNGMONEY_API_BASE_URL,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
      },
    });
  }

  /**
   * Device Login - Autenticação por dispositivo
   */
  async deviceLogin(deviceId: string) {
    try {
      const response = await this.client.post('/api/v1/auth/device-login.php', {
        device_id: deviceId,
      });
      return response.data;
    } catch (error: any) {
      console.error('[YoungMoney API] Device login error:', error.response?.data || error.message);
      throw error;
    }
  }

  /**
   * Get User Profile - Buscar perfil de usuário
   */
  async getUserProfile(userId: number) {
    try {
      const response = await this.client.get('/api/v1/users.php', {
        params: { user_id: userId },
      });
      return response.data;
    } catch (error: any) {
      console.error('[YoungMoney API] Get user error:', error.response?.data || error.message);
      throw error;
    }
  }

  /**
   * Manage Points - Gerenciar pontos do usuário
   */
  async managePoints(userId: number, amount: number, action: 'add' | 'remove') {
    try {
      const response = await this.client.post('/api/v1/points.php', {
        user_id: userId,
        amount,
        action,
      });
      return response.data;
    } catch (error: any) {
      console.error('[YoungMoney API] Manage points error:', error.response?.data || error.message);
      throw error;
    }
  }

  /**
   * Request Withdrawal - Solicitar saque
   */
  async requestWithdrawal(userId: number, amount: number, pixKey: string, pixKeyType: string) {
    try {
      const response = await this.client.post('/api/v1/withdrawals.php', {
        user_id: userId,
        amount,
        pix_key: pixKey,
        pix_key_type: pixKeyType,
      });
      return response.data;
    } catch (error: any) {
      console.error('[YoungMoney API] Request withdrawal error:', error.response?.data || error.message);
      throw error;
    }
  }

  /**
   * Get Withdrawals - Listar saques
   */
  async getWithdrawals(status?: string) {
    try {
      const response = await this.client.get('/api/v1/withdrawals.php', {
        params: status ? { status } : {},
      });
      return response.data;
    } catch (error: any) {
      console.error('[YoungMoney API] Get withdrawals error:', error.response?.data || error.message);
      throw error;
    }
  }
}

export const youngMoneyApi = new YoungMoneyApiClient();
