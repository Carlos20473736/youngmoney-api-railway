import express from 'express';
import cors from 'cors';
import bodyParser from 'body-parser';
import dotenv from 'dotenv';
import pixRoutes from './routes/pix-payment-routes.js';

// Carregar variáveis de ambiente
dotenv.config();

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// Health check
app.get('/health', (req, res) => {
    res.json({ 
        status: 'ok', 
        message: 'YoungMoney API is running',
        version: '2.0.0'
    });
});

// Rotas de PIX
app.use('/api/pix', pixRoutes);

// Rota padrão
app.get('/', (req, res) => {
    res.json({ 
        message: 'YoungMoney API v2.0.0',
        endpoints: {
            health: '/health',
            pix: '/api/pix'
        }
    });
});

// Tratamento de erros 404
app.use((req, res) => {
    res.status(404).json({ 
        success: false, 
        error: 'Rota não encontrada' 
    });
});

// Iniciar servidor
app.listen(PORT, () => {
    console.log(`🚀 YoungMoney API rodando em http://localhost:${PORT}`);
    console.log(`📊 Health check: http://localhost:${PORT}/health`);
});
