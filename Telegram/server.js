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

// Armazenamento em memória para dados dos usuários
const userDataStore = {};

console.log('Starting server with local API...');
console.log('__dirname:', __dirname);

// Função para obter ou criar dados do usuário
function getUserData(userId) {
    if (!userDataStore[userId]) {
        userDataStore[userId] = {
            user_id: userId,
            email: `user_${userId}@telegram.com`,
            impressions: 0,
            clicks: 0,
            required_impressions: 20,
            created_at: new Date().toISOString()
        };
    }
    return userDataStore[userId];
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

    // API Local - profile
    if (pathname.startsWith('/api/youngmoney/profile')) {
        const userId = parsedUrl.query.user_id;
        console.log(`[API] Profile request for user_id: ${userId}`);
        
        if (!userId) {
            res.writeHead(400, {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            });
            res.end(JSON.stringify({ success: false, error: 'user_id required' }));
            return;
        }
        
        const userData = getUserData(userId);
        res.writeHead(200, {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
        });
        res.end(JSON.stringify({
            success: true,
            data: {
                user_id: userData.user_id,
                email: userData.email
            }
        }));
        return;
    }

    // API Local - progress
    if (pathname.startsWith('/api/youngmoney/monetag/progress')) {
        const userId = parsedUrl.query.user_id;
        console.log(`[API] Progress request for user_id: ${userId}`);
        
        if (!userId) {
            res.writeHead(400, {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            });
            res.end(JSON.stringify({ success: false, error: 'user_id required' }));
            return;
        }
        
        const userData = getUserData(userId);
        res.writeHead(200, {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
        });
        res.end(JSON.stringify({
            success: true,
            data: {
                impressions: userData.impressions,
                clicks: userData.clicks,
                required_impressions: userData.required_impressions
            }
        }));
        return;
    }

    // API Local - reset_postback (resetar e randomizar impressões)
    if (pathname.startsWith('/monetag/reset_postback') || pathname.startsWith('/api/reset_postback')) {
        const userId = parsedUrl.query.user_id || parsedUrl.query.ymid;
        console.log(`[API] Reset postback for user_id: ${userId}`);
        
        if (userId) {
            const userData = getUserData(userId);
            // Resetar impressões para um valor randômico entre 0 e 5
            const randomImpressions = Math.floor(Math.random() * 6);
            userData.impressions = randomImpressions;
            userData.clicks = 0;
            // Randomizar required_impressions entre 15 e 25
            userData.required_impressions = Math.floor(Math.random() * 11) + 15;
            console.log(`[API] User ${userId} reset - impressions: ${userData.impressions}, required: ${userData.required_impressions}`);
        }
        
        res.writeHead(200, {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
        });
        res.end(JSON.stringify({ 
            success: true, 
            message: 'Postback reset successfully',
            data: userId ? getUserData(userId) : null
        }));
        return;
    }

    // API Local - postback (registrar impressões/cliques)
    if (pathname.startsWith('/api/postback')) {
        const eventType = parsedUrl.query.event_type;
        const ymid = parsedUrl.query.ymid;
        
        console.log(`[API] Postback: event_type=${eventType}, ymid=${ymid}`);
        
        if (ymid) {
            const userData = getUserData(ymid);
            if (eventType === 'impression') {
                userData.impressions++;
                console.log(`[API] User ${ymid} impressions: ${userData.impressions}`);
            } else if (eventType === 'click') {
                userData.clicks++;
                console.log(`[API] User ${ymid} clicks: ${userData.clicks}`);
            }
        }
        
        res.writeHead(200, {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
        });
        res.end(JSON.stringify({ success: true, message: 'Postback received' }));
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
    console.log(`Server running on port ${PORT} with local API enabled`);
    console.log('API routes:');
    console.log('  /api/youngmoney/profile - Get user profile');
    console.log('  /api/youngmoney/monetag/progress - Get user progress');
    console.log('  /api/postback - Register impressions/clicks');
});
