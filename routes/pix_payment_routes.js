/**
 * PIX Payment Routes - YoungMoney API
 * Endpoints para gerenciar chaves PIX e processar pagamentos automáticos
 */

const express = require('express');
const router = express.Router();
const mysql = require('mysql2/promise');

// Configuração do pool de conexão MySQL
const pool = mysql.createPool({
    host: process.env.MYSQLHOST,
    port: process.env.MYSQLPORT,
    user: process.env.MYSQLUSER,
    password: process.env.MYSQLPASSWORD,
    database: process.env.MYSQLDATABASE,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

/**
 * POST /api/pix/save-key
 * Salva a chave PIX do usuário
 * 
 * Body:
 * {
 *   "user_id": "123",
 *   "pix_key_type": "CPF|CNPJ|Email|Telefone|Chave Aleatória",
 *   "pix_key": "valor_da_chave"
 * }
 */
router.post('/save-key', async (req, res) => {
    try {
        const { user_id, pix_key_type, pix_key } = req.body;

        // Validação
        if (!user_id || !pix_key_type || !pix_key) {
            return res.status(400).json({
                success: false,
                error: 'Dados incompletos'
            });
        }

        const connection = await pool.getConnection();

        try {
            // Verificar se usuário existe
            const [userCheck] = await connection.execute(
                'SELECT id FROM users WHERE id = ?',
                [user_id]
            );

            if (userCheck.length === 0) {
                return res.status(404).json({
                    success: false,
                    error: 'Usuário não encontrado'
                });
            }

            // Salvar ou atualizar chave PIX
            const [existingKey] = await connection.execute(
                'SELECT id FROM pix_keys WHERE user_id = ?',
                [user_id]
            );

            if (existingKey.length > 0) {
                // Atualizar chave existente
                await connection.execute(
                    'UPDATE pix_keys SET pix_key_type = ?, pix_key = ?, updated_at = NOW() WHERE user_id = ?',
                    [pix_key_type, pix_key, user_id]
                );
            } else {
                // Inserir nova chave
                await connection.execute(
                    'INSERT INTO pix_keys (user_id, pix_key_type, pix_key, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())',
                    [user_id, pix_key_type, pix_key]
                );
            }

            res.json({
                success: true,
                message: 'Chave PIX salva com sucesso'
            });

        } finally {
            connection.release();
        }

    } catch (error) {
        console.error('Erro ao salvar chave PIX:', error);
        res.status(500).json({
            success: false,
            error: 'Erro ao salvar chave PIX'
        });
    }
});

/**
 * GET /api/pix/key/:user_id
 * Obtém a chave PIX do usuário
 */
router.get('/key/:user_id', async (req, res) => {
    try {
        const { user_id } = req.params;

        const connection = await pool.getConnection();

        try {
            const [pixKey] = await connection.execute(
                'SELECT pix_key_type, pix_key FROM pix_keys WHERE user_id = ?',
                [user_id]
            );

            if (pixKey.length === 0) {
                return res.status(404).json({
                    success: false,
                    error: 'Chave PIX não encontrada'
                });
            }

            res.json({
                success: true,
                data: pixKey[0]
            });

        } finally {
            connection.release();
        }

    } catch (error) {
        console.error('Erro ao buscar chave PIX:', error);
        res.status(500).json({
            success: false,
            error: 'Erro ao buscar chave PIX'
        });
    }
});

/**
 * POST /api/pix/process-top10-payments
 * Processa pagamentos automáticos para o top 10 do ranking
 * 
 * Valores escalonados:
 * - Top 1: R$ 20,00
 * - Top 2: R$ 10,00
 * - Top 3: R$ 5,00
 * - Top 4-10: R$ 1,00
 * 
 * Body:
 * {
 *   "ranking_period": "2024-12-10" (opcional, usa hoje se não informado)
 * }
 */
router.post('/process-top10-payments', async (req, res) => {
    try {
        const { ranking_period } = req.body;
        const period = ranking_period || new Date().toISOString().split('T')[0];

        const connection = await pool.getConnection();

        try {
            // Obter top 10 do ranking
            const [topUsers] = await connection.execute(`
                SELECT 
                    u.id,
                    u.username,
                    r.position,
                    r.daily_points,
                    pk.pix_key_type,
                    pk.pix_key
                FROM rankings r
                JOIN users u ON r.user_id = u.id
                LEFT JOIN pix_keys pk ON u.id = pk.user_id
                WHERE DATE(r.created_at) = ?
                ORDER BY r.position ASC
                LIMIT 10
            `, [period]);

            if (topUsers.length === 0) {
                return res.status(404).json({
                    success: false,
                    error: 'Nenhum ranking encontrado para este período'
                });
            }

            // Valores de pagamento por posição
            const paymentValues = {
                1: 20.00,
                2: 10.00,
                3: 5.00,
                4: 1.00,
                5: 1.00,
                6: 1.00,
                7: 1.00,
                8: 1.00,
                9: 1.00,
                10: 1.00
            };

            // Processar pagamentos
            const payments = [];
            const failedPayments = [];

            for (const user of topUsers) {
                const position = user.position;
                const amount = paymentValues[position] || 0;

                // Validar se usuário tem chave PIX
                if (!user.pix_key || !user.pix_key_type) {
                    failedPayments.push({
                        user_id: user.id,
                        username: user.username,
                        position: position,
                        amount: amount,
                        reason: 'Chave PIX não registrada'
                    });
                    continue;
                }

                try {
                    // Registrar pagamento no banco de dados
                    const [paymentResult] = await connection.execute(`
                        INSERT INTO pix_payments 
                        (user_id, position, amount, pix_key_type, pix_key, status, created_at)
                        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                    `, [user.id, position, amount, user.pix_key_type, user.pix_key]);

                    payments.push({
                        user_id: user.id,
                        username: user.username,
                        position: position,
                        amount: amount,
                        pix_key_type: user.pix_key_type,
                        pix_key: user.pix_key,
                        payment_id: paymentResult.insertId,
                        status: 'pending'
                    });

                    // TODO: Integrar com gateway de pagamento PIX aqui
                    // Exemplo: await processPixPayment(user.pix_key, amount);

                } catch (error) {
                    console.error(`Erro ao processar pagamento para usuário ${user.id}:`, error);
                    failedPayments.push({
                        user_id: user.id,
                        username: user.username,
                        position: position,
                        amount: amount,
                        reason: 'Erro ao processar pagamento'
                    });
                }
            }

            res.json({
                success: true,
                message: `${payments.length} pagamentos processados com sucesso`,
                data: {
                    period: period,
                    total_payments: payments.length,
                    total_amount: payments.reduce((sum, p) => sum + p.amount, 0),
                    payments: payments,
                    failed_payments: failedPayments
                }
            });

        } finally {
            connection.release();
        }

    } catch (error) {
        console.error('Erro ao processar pagamentos do top 10:', error);
        res.status(500).json({
            success: false,
            error: 'Erro ao processar pagamentos'
        });
    }
});

/**
 * GET /api/pix/payments/:user_id
 * Obtém histórico de pagamentos do usuário
 */
router.get('/payments/:user_id', async (req, res) => {
    try {
        const { user_id } = req.params;

        const connection = await pool.getConnection();

        try {
            const [payments] = await connection.execute(`
                SELECT 
                    id,
                    position,
                    amount,
                    status,
                    created_at
                FROM pix_payments
                WHERE user_id = ?
                ORDER BY created_at DESC
            `, [user_id]);

            res.json({
                success: true,
                data: payments
            });

        } finally {
            connection.release();
        }

    } catch (error) {
        console.error('Erro ao buscar pagamentos:', error);
        res.status(500).json({
            success: false,
            error: 'Erro ao buscar pagamentos'
        });
    }
});

/**
 * PUT /api/pix/payment/:payment_id
 * Atualiza status de um pagamento
 * 
 * Body:
 * {
 *   "status": "completed|failed|pending"
 * }
 */
router.put('/payment/:payment_id', async (req, res) => {
    try {
        const { payment_id } = req.params;
        const { status } = req.body;

        if (!['completed', 'failed', 'pending'].includes(status)) {
            return res.status(400).json({
                success: false,
                error: 'Status inválido'
            });
        }

        const connection = await pool.getConnection();

        try {
            await connection.execute(
                'UPDATE pix_payments SET status = ?, updated_at = NOW() WHERE id = ?',
                [status, payment_id]
            );

            res.json({
                success: true,
                message: 'Status do pagamento atualizado'
            });

        } finally {
            connection.release();
        }

    } catch (error) {
        console.error('Erro ao atualizar pagamento:', error);
        res.status(500).json({
            success: false,
            error: 'Erro ao atualizar pagamento'
        });
    }
});

module.exports = router;
