const http = require('http');
const https = require('https');
const fs = require('fs');
const path = require('path');
const url = require('url');

const PORT = process.env.PORT || 8080;

const mimeTypes = {
    '.html': 'text/html',
    '.css': 'text/css',
    '.js': 'application/javascript',
    '.json': 'application/json',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.gif': 'image/gif',
    '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
    '.ttf': 'font/ttf'
};

// URLs das APIs externas
const API_TARGETS = {
    '/api/youngmoney': 'https://youngmoney-api-railway-production-561a.up.railway.app/telegramyoung2',
    '/api/postback': 'https://monetag-postback-server-production.up.railway.app/api/postback'
};

console.log('Starting server with CORS proxy...');
console.log('__dirname:', __dirname);

// Função para fazer proxy de requisições
function proxyRequest(targetUrl, req, res) {
    const parsedUrl = url.parse(targetUrl);
    
    const options = {
        hostname: parsedUrl.hostname,
        port: parsedUrl.port || 443,
        path: parsedUrl.path,
        method: req.method,
        headers: {
            'Content-Type': 'application/json',
            'User-Agent': 'YoungMoney-Proxy/1.0'
        }
    };

    console.log(`[PROXY] ${req.method} ${targetUrl}`);

    const proxyReq = https.request(options, (proxyRes) => {
        // Adicionar headers CORS
        res.writeHead(proxyRes.statusCode, {
            'Content-Type': proxyRes.headers['content-type'] || 'application/json',
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization'
        });

        proxyRes.pipe(res);
    });

    proxyReq.on('error', (err) => {
        console.error('[PROXY] Error:', err.message);
        res.writeHead(500, {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
        });
        res.end(JSON.stringify({ error: 'Proxy error', message: err.message }));
    });

    // Se houver body na requisição, enviar
    if (req.method === 'POST' || req.method === 'PUT') {
        let body = '';
        req.on('data', chunk => body += chunk);
        req.on('end', () => {
            if (body) proxyReq.write(body);
            proxyReq.end();
        });
    } else {
        proxyReq.end();
    }
}

const server = http.createServer((req, res) => {
    const parsedUrl = url.parse(req.url, true);
    const pathname = parsedUrl.pathname;
    
    console.log(`Request: ${req.method} ${req.url}`);

    // Handle CORS preflight
    if (req.method === 'OPTIONS') {
        res.writeHead(200, {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization',
            'Access-Control-Max-Age': '86400'
        });
        res.end();
        return;
    }

    // Proxy para API YoungMoney - profile
    if (pathname.startsWith('/api/youngmoney/profile')) {
        const userId = parsedUrl.query.user_id;
        const targetUrl = `${API_TARGETS['/api/youngmoney']}/profile?user_id=${userId}`;
        proxyRequest(targetUrl, req, res);
        return;
    }

    // Proxy para API YoungMoney - progress
    if (pathname.startsWith('/api/youngmoney/monetag/progress')) {
        const userId = parsedUrl.query.user_id;
        const targetUrl = `${API_TARGETS['/api/youngmoney']}/monetag/progress.php?user_id=${userId}`;
        proxyRequest(targetUrl, req, res);
        return;
    }

    // Proxy para postback
    if (pathname.startsWith('/api/postback')) {
        const queryString = url.parse(req.url).query || '';
        const targetUrl = `${API_TARGETS['/api/postback']}?${queryString}`;
        proxyRequest(targetUrl, req, res);
        return;
    }
    
    // Servir arquivos estáticos
    let filePath = pathname === '/' ? '/index.html' : pathname;
    
    // Construct full path
    const fullPath = path.join(__dirname, filePath);
    
    // Check if path exists
    if (!fs.existsSync(fullPath)) {
        console.log('File not found:', fullPath);
        // Serve index.html for SPA routing
        const indexPath = path.join(__dirname, 'index.html');
        if (fs.existsSync(indexPath)) {
            const content = fs.readFileSync(indexPath);
            res.writeHead(200, { 
                'Content-Type': 'text/html',
                'Access-Control-Allow-Origin': '*'
            });
            res.end(content);
        } else {
            res.writeHead(404);
            res.end('404 Not Found');
        }
        return;
    }
    
    // Check if it's a directory
    const stats = fs.statSync(fullPath);
    if (stats.isDirectory()) {
        const indexInDir = path.join(fullPath, 'index.html');
        if (fs.existsSync(indexInDir)) {
            const content = fs.readFileSync(indexInDir);
            res.writeHead(200, { 
                'Content-Type': 'text/html',
                'Access-Control-Allow-Origin': '*'
            });
            res.end(content);
        } else {
            const mainIndex = path.join(__dirname, 'index.html');
            const content = fs.readFileSync(mainIndex);
            res.writeHead(200, { 
                'Content-Type': 'text/html',
                'Access-Control-Allow-Origin': '*'
            });
            res.end(content);
        }
        return;
    }
    
    // Get file extension
    const ext = path.extname(fullPath).toLowerCase();
    const contentType = mimeTypes[ext] || 'application/octet-stream';
    
    // Read and serve the file
    try {
        const content = fs.readFileSync(fullPath);
        res.writeHead(200, { 
            'Content-Type': contentType,
            'Access-Control-Allow-Origin': '*'
        });
        res.end(content);
        console.log('Served:', fullPath);
    } catch (err) {
        console.error('Error reading file:', err);
        res.writeHead(500);
        res.end('Server Error: ' + err.message);
    }
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`Server running on port ${PORT} with CORS proxy enabled`);
    console.log('Proxy routes:');
    console.log('  /api/youngmoney/* -> youngmoney-api-railway');
    console.log('  /api/postback/* -> monetag-postback-server');
});
