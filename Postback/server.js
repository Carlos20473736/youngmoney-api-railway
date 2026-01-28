const http = require('http');
const fs = require('fs');
const path = require('path');

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

console.log('Starting server...');
console.log('__dirname:', __dirname);
console.log('Files in directory:', fs.readdirSync(__dirname));

const server = http.createServer((req, res) => {
    console.log(`Request: ${req.method} ${req.url}`);
    
    let filePath = req.url === '/' ? '/index.html' : req.url;
    
    // Remove query strings
    filePath = filePath.split('?')[0];
    
    // Construct full path
    const fullPath = path.join(__dirname, filePath);
    console.log('Full path:', fullPath);
    
    // Get file extension
    const ext = path.extname(fullPath).toLowerCase();
    const contentType = mimeTypes[ext] || 'application/octet-stream';
    
    // Check if file exists
    if (!fs.existsSync(fullPath)) {
        console.log('File not found:', fullPath);
        // Serve index.html for SPA routing
        const indexPath = path.join(__dirname, 'index.html');
        console.log('Trying index.html at:', indexPath);
        
        if (fs.existsSync(indexPath)) {
            const content = fs.readFileSync(indexPath);
            res.writeHead(200, { 'Content-Type': 'text/html' });
            res.end(content);
        } else {
            res.writeHead(404);
            res.end('404 Not Found - index.html not found');
        }
        return;
    }
    
    // Read and serve the file
    try {
        const content = fs.readFileSync(fullPath);
        res.writeHead(200, { 'Content-Type': contentType });
        res.end(content);
        console.log('Served:', fullPath);
    } catch (err) {
        console.error('Error reading file:', err);
        res.writeHead(500);
        res.end('Server Error: ' + err.message);
    }
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`Server running on port ${PORT}`);
});
