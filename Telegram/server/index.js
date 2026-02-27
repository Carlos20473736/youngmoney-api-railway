import express from 'express';
import cors from 'cors';
import axios from 'axios';
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';

// Load environment variables
dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;
const NODE_ENV = process.env.NODE_ENV || 'development';

// Middleware
app.use(cors({
  origin: process.env.CORS_ORIGIN || '*',
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization', 'Host', 'User-Agent']
}));

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Serve static files from public directory
const publicPath = path.join(__dirname, '../public');
app.use(express.static(publicPath));

// Logging middleware
app.use((req, res, next) => {
  console.log(`[${new Date().toISOString()}] ${req.method} ${req.path}`);
  next();
});

// API Proxy Routes for Graninha Bot Backend
const GRANINHA_API_URL = process.env.GRANINHA_API_URL || 'https://painel.graninha.com.br/api/v1';

// Proxy genérico para API Graninha (aceita qualquer endpoint)
app.post('/api/:endpoint', async (req, res) => {
  try {
    const { endpoint } = req.params;
    const { bearer_token, data } = req.body;
    
    console.log(`[PROXY] Requisição para ${endpoint}`);
    
    const response = await axios.post(
      `${GRANINHA_API_URL}/${endpoint}`,
      data,
      {
        headers: {
          'accept': 'application/json',
          'authorization': `Bearer ${bearer_token}`,
          'content-type': 'application/x-www-form-urlencoded',
          'user-agent': 'okhttp/4.12.0'
        }
      }
    );
    
    console.log(`[PROXY] ${endpoint} - sucesso`);
    res.json(response.data);
  } catch (error) {
    console.error(`[PROXY] Erro em ${req.params.endpoint}:`, error.message);
    res.status(error.response?.status || 500).json({
      error: 'Erro na requisição',
      message: error.message
    });
  }
});

// Proxy: Get User Info (mantido para compatibilidade)
app.post('/api/get_user_old', async (req, res) => {
  try {
    console.log('[PROXY] Obtendo informações do usuário');
    
    const response = await axios.post(
      `${GRANINHA_API_URL}/get_user`,
      req.body,
      {
        headers: {
          'Content-Type': 'application/json; charset=UTF-8',
          'User-Agent': 'okhttp/4.11.0'
        }
      }
    );
    
    console.log('[PROXY] Usuário obtido com sucesso');
    res.json(response.data);
  } catch (error) {
    console.error('[PROXY] Erro ao obter usuário:', error.message);
    res.status(error.response?.status || 500).json({
      error: 'Erro ao obter usuário',
      message: error.message
    });
  }
});

// Proxy: Play Scratch Card (Raspadinha)
app.post('/api/play_scratch', async (req, res) => {
  try {
    console.log('[PROXY] Jogando raspadinha');
    
    const response = await axios.post(
      `${GRANINHA_API_URL}/play_scratch`,
      req.body,
      {
        headers: {
          'Content-Type': 'application/json; charset=UTF-8',
          'User-Agent': 'okhttp/4.11.0'
        }
      }
    );
    
    console.log('[PROXY] Raspadinha jogada com sucesso');
    res.json(response.data);
  } catch (error) {
    console.error('[PROXY] Erro ao jogar raspadinha:', error.message);
    res.status(error.response?.status || 500).json({
      error: 'Erro ao jogar raspadinha',
      message: error.message
    });
  }
});

// Proxy: Play Roulette (Roleta)
app.post('/api/play_roulette', async (req, res) => {
  try {
    console.log('[PROXY] Jogando roleta');
    
    const response = await axios.post(
      `${GRANINHA_API_URL}/play_roulette`,
      req.body,
      {
        headers: {
          'Content-Type': 'application/json; charset=UTF-8',
          'User-Agent': 'okhttp/4.11.0'
        }
      }
    );
    
    console.log('[PROXY] Roleta jogada com sucesso');
    res.json(response.data);
  } catch (error) {
    console.error('[PROXY] Erro ao jogar roleta:', error.message);
    res.status(error.response?.status || 500).json({
      error: 'Erro ao jogar roleta',
      message: error.message
    });
  }
});

// Proxy: Play Quiz
app.post('/api/play_quiz', async (req, res) => {
  try {
    console.log('[PROXY] Respondendo quiz');
    
    const response = await axios.post(
      `${GRANINHA_API_URL}/play_quiz`,
      req.body,
      {
        headers: {
          'Content-Type': 'application/json; charset=UTF-8',
          'User-Agent': 'okhttp/4.11.0'
        }
      }
    );
    
    console.log('[PROXY] Quiz respondido com sucesso');
    res.json(response.data);
  } catch (error) {
    console.error('[PROXY] Erro ao responder quiz:', error.message);
    res.status(error.response?.status || 500).json({
      error: 'Erro ao responder quiz',
      message: error.message
    });
  }
});

// Proxy: Play Game
app.post('/api/play_game', async (req, res) => {
  try {
    console.log('[PROXY] Jogando game:', req.body.game_id);
    
    const response = await axios.post(
      `${GRANINHA_API_URL}/play_game`,
      req.body,
      {
        headers: {
          'Content-Type': 'application/json; charset=UTF-8',
          'User-Agent': 'okhttp/4.11.0'
        }
      }
    );
    
    console.log('[PROXY] Game jogado com sucesso');
    res.json(response.data);
  } catch (error) {
    console.error('[PROXY] Erro ao jogar game:', error.message);
    res.status(error.response?.status || 500).json({
      error: 'Erro ao jogar game',
      message: error.message
    });
  }
});

// Proxy: Get Spin (Roleta)
app.post('/api/get_spin', async (req, res) => {
  try {
    console.log('[PROXY] Obtendo spin da roleta');
    
    const response = await axios.post(
      `${GRANINHA_API_URL}/get_spin`,
      req.body,
      {
        headers: {
          'Content-Type': 'application/json; charset=UTF-8',
          'User-Agent': 'okhttp/4.11.0'
        }
      }
    );
    
    console.log('[PROXY] Spin obtido com sucesso');
    res.json(response.data);
  } catch (error) {
    console.error('[PROXY] Erro ao obter spin:', error.message);
    res.status(error.response?.status || 500).json({
      error: 'Erro ao obter spin',
      message: error.message
    });
  }
});



// Health check endpoint
app.get('/health', (req, res) => {
  res.json({
    status: 'ok',
    timestamp: new Date().toISOString(),
    environment: NODE_ENV
  });
});

// Serve index.html for all other routes (SPA support)
app.get('*', (req, res) => {
  res.sendFile(path.join(publicPath, 'index.html'));
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error('[ERROR]', err);
  res.status(500).json({
    error: 'Internal Server Error',
    message: NODE_ENV === 'development' ? err.message : 'An error occurred'
  });
});

// Start server
app.listen(PORT, () => {
  console.log(`\n🚀 Graninha Bot Server`);
  console.log(`📍 Running on http://localhost:${PORT}`);
  console.log(`🔧 Environment: ${NODE_ENV}`);
  console.log(`🌐 CORS enabled for: ${process.env.CORS_ORIGIN || '*'}`);
  console.log(`🤖 Backend API: ${GRANINHA_API_URL}`);
  console.log(`\n✅ Server ready to accept connections\n`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
  console.log('SIGTERM signal received: closing HTTP server');
  process.exit(0);
});

process.on('SIGINT', () => {
  console.log('SIGINT signal received: closing HTTP server');
  process.exit(0);
});
