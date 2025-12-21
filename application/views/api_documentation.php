<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printer Monitoring API Documentation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 0; text-align: center; margin-bottom: 30px; border-radius: 10px; }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .header p { font-size: 1.2em; opacity: 0.9; }
        .section { background: white; margin-bottom: 30px; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section h2 { color: #667eea; margin-bottom: 20px; font-size: 1.8em; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .endpoint { margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea; }
        .method { display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 0.9em; margin-right: 10px; }
        .get { background: #28a745; color: white; }
        .post { background: #007bff; color: white; }
        .put { background: #ffc107; color: black; }
        .delete { background: #dc3545; color: white; }
        .url { font-family: 'Courier New', monospace; background: #e9ecef; padding: 8px 12px; border-radius: 4px; margin: 10px 0; }
        .description { margin: 10px 0; color: #666; }
        .auth-required { background: #fff3cd; color: #856404; padding: 8px 12px; border-radius: 4px; font-size: 0.9em; margin: 10px 0; }
        .example { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; margin: 10px 0; }
        .example h4 { margin-bottom: 10px; color: #495057; }
        .code { font-family: 'Courier New', monospace; font-size: 0.9em; white-space: pre-wrap; background: #f8f9fa; padding: 12px; border-radius: 4px; border: 1px solid #dee2e6; }
        .code:hover { background: #e9ecef; }
        .code[onclick] { border: 2px dashed #007bff; }
        .code[onclick]:hover { background: #e7f3ff; border-color: #0056b3; }
        .copy-btn { position: absolute; top: 8px; right: 8px; background: #007bff; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; cursor: pointer; }
        .copy-btn:hover { background: #0056b3; }
        .status { margin-top: 20px; padding: 15px; background: #d4edda; border-radius: 8px; }
        .status h3 { color: #155724; margin-bottom: 10px; }
        .feature { display: inline-block; background: #e7f3ff; color: #0066cc; padding: 6px 12px; border-radius: 20px; margin: 5px; font-size: 0.9em; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🖨️ Printer Monitoring API</h1>
            <p>Real-time printer monitoring and alert system</p>
            <div style="margin-top: 20px;">
                <span class="feature">SNMP Monitoring</span>
                <span class="feature">Real-time Alerts</span>
                <span class="feature">JWT Authentication</span>
                <span class="feature">HP Web Interface</span>
            </div>
        </div>

        <div class="status">
            <h3>🚀 API Status: Online</h3>
            <p>Base URL: <code><?= base_url() ?>api/</code></p>
            <p>Version: 1.0.0 | Database: printer_monitoring</p>
        </div>

        <div class="section">
            <h2>🔐 Authentication</h2>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <div class="url">/api/auth/register</div>
                <div class="description">Register new user account</div>
                <div class="example">
                    <h4>📋 Copy Payload:</h4>
                    <div class="code" onclick="copyToClipboard(this)" style="cursor: pointer; position: relative;" title="Click to copy">{
  "username": "admin",
  "email": "admin@example.com",
  "password": "password123",
  "role": "admin"
}</div>
                    <h4>✅ Success Response (201):</h4>
                    <div class="code">{
  "message": "User created successfully",
  "user": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "role": "admin"
  }
}</div>
                </div>
            </div>

            <div class="endpoint">
                <span class="method post">POST</span>
                <div class="url">/api/auth/login</div>
                <div class="description">Login and get access token</div>
                <div class="example">
                    <h4>📋 Copy Payload:</h4>
                    <div class="code" onclick="copyToClipboard(this)" style="cursor: pointer; position: relative;" title="Click to copy">{
  "username": "admin",
  "password": "password123"
}</div>
                    <h4>✅ Success Response (200):</h4>
                    <div class="code">{
  "user": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "role": "admin"
  },
  "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "refreshToken": "abc123..."
}</div>
                    <h4>❌ Error Response (401):</h4>
                    <div class="code">{
  "message": "Invalid credentials"
}</div>
                </div>
            </div>

            <div class="endpoint">
                <span class="method post">POST</span>
                <div class="url">/api/auth/refresh</div>
                <div class="description">Refresh access token</div>
                <div class="example">
                    <h4>📋 Copy Payload:</h4>
                    <div class="code" onclick="copyToClipboard(this)" style="cursor: pointer; position: relative;" title="Click to copy">{
  "refreshToken": "your-refresh-token-here"
}</div>
                </div>
            </div>

            <div class="endpoint">
                <span class="method post">POST</span>
                <div class="url">/api/auth/logout</div>
                <div class="description">Logout and clear refresh token</div>
                <div class="example">
                    <h4>📋 Copy Payload:</h4>
                    <div class="code" onclick="copyToClipboard(this)" style="cursor: pointer; position: relative;" title="Click to copy">{
  "refreshToken": "your-refresh-token-here"
}</div>
                </div>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/auth/verify</div>
                <div class="description">Verify token validity</div>
                <div class="auth-required">🔒 Headers: Authorization: Bearer {token}</div>
            </div>
        </div>

        <div class="section">
            <h2>🖨️ Printer Management</h2>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/printers</div>
                <div class="description">Get all active printers with latest metrics</div>
                <div class="example">
                    <h4>✅ Success Response (200):</h4>
                    <div class="code">[
  {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "HP LaserJet Pro",
    "ipAddress": "192.168.1.100",
    "model": "HP LaserJet Pro MFP M428fdw",
    "location": "Office Floor 1",
    "status": "online",
    "cyanLevel": 85,
    "magentaLevel": 72,
    "yellowLevel": 90,
    "blackLevel": 45
  }
]</div>
                </div>
            </div>

            <div class="endpoint">
                <span class="method post">POST</span>
                <div class="url">/api/printers</div>
                <div class="description">Create new printer</div>
                <div class="auth-required">🔒 Headers: Authorization: Bearer {token}</div>
                <div class="example">
                    <h4>📋 Copy Payload:</h4>
                    <div class="code" onclick="copyToClipboard(this)" style="cursor: pointer; position: relative;" title="Click to copy">{
  "name": "HP LaserJet Pro",
  "ipAddress": "192.168.1.100",
  "model": "HP LaserJet Pro MFP M428fdw",
  "location": "Office Floor 1",
  "snmpProfile": "default"
}</div>
                    <h4>✅ Success Response (201):</h4>
                    <div class="code">{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "HP LaserJet Pro",
  "ipAddress": "192.168.1.100",
  "model": "HP LaserJet Pro MFP M428fdw",
  "location": "Office Floor 1",
  "status": "offline",
  "isActive": true,
  "createdAt": "2024-01-15T10:30:00Z"
}</div>
                </div>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/printers/{id}</div>
                <div class="description">Get specific printer with metrics history</div>
                <div class="example">
                    <h4>✅ Success Response (200):</h4>
                    <div class="code">{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "HP LaserJet Pro",
  "ipAddress": "192.168.1.100",
  "metrics": [
    {
      "cyanLevel": 85,
      "magentaLevel": 72,
      "yellowLevel": 90,
      "blackLevel": 45,
      "createdAt": "2024-01-15T10:30:00Z"
    }
  ]
}</div>
                </div>
            </div>

            <div class="endpoint">
                <span class="method put">PUT</span>
                <div class="url">/api/printers/{id}</div>
                <div class="description">Update printer information</div>
                <div class="auth-required">🔒 Headers: Authorization: Bearer {token}</div>
                <div class="example">
                    <h4>📋 Copy Payload:</h4>
                    <div class="code" onclick="copyToClipboard(this)" style="cursor: pointer; position: relative;" title="Click to copy">{
  "name": "HP LaserJet Pro Updated",
  "location": "Office Floor 2",
  "model": "HP LaserJet Pro MFP M428fdw"
}</div>
                </div>
            </div>

            <div class="endpoint">
                <span class="method delete">DELETE</span>
                <div class="url">/api/printers/{id}</div>
                <div class="description">Soft delete printer (set inactive)</div>
                <div class="auth-required">🔒 Headers: Authorization: Bearer {token}</div>
                <div class="example">
                    <h4>✅ Success Response (200):</h4>
                    <div class="code">{
  "message": "Printer deleted successfully"
}</div>
                </div>
            </div>

            <div class="endpoint">
                <span class="method post">POST</span>
                <div class="url">/api/printers/test</div>
                <div class="description">Test printer connection</div>
                <div class="auth-required">🔒 Headers: Authorization: Bearer {token}</div>
                <div class="example">
                    <h4>📋 Copy Payload:</h4>
                    <div class="code" onclick="copyToClipboard(this)" style="cursor: pointer; position: relative;" title="Click to copy">{
  "ipAddress": "192.168.1.100",
  "snmpProfile": {
    "community": "public",
    "version": "2c",
    "timeout": 5000
  }
}</div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>🚨 Alert Management</h2>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/alerts/test</div>
                <div class="description">Test endpoint - get alert count</div>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/alerts</div>
                <div class="description">Get all alerts with filtering</div>
                <div class="example">
                    <h4>Query Parameters:</h4>
                    <div class="code">?limit=50&offset=0&status=active&severity=high&printer=HP</div>
                </div>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/alerts/stats</div>
                <div class="description">Get alert statistics</div>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/alerts/printer/{printerId}</div>
                <div class="description">Get alerts for specific printer</div>
            </div>

            <div class="endpoint">
                <span class="method put">PUT</span>
                <div class="url">/api/alerts/{id}/acknowledge</div>
                <div class="description">Acknowledge alert</div>
                <div class="auth-required">🔒 Requires: Bearer Token</div>
            </div>

            <div class="endpoint">
                <span class="method delete">DELETE</span>
                <div class="url">/api/alerts/{id}</div>
                <div class="description">Delete alert (admin only)</div>
                <div class="auth-required">🔒 Requires: Bearer Token + Admin Role</div>
            </div>
        </div>

        <div class="section">
            <h2>📡 SNMP Operations</h2>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/snmp/test/{ip}?community=public</div>
                <div class="description">Test SNMP connection to printer</div>
                <div class="auth-required">🔒 Requires: Bearer Token</div>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/snmp/metrics/{ip}?community=public</div>
                <div class="description">Get printer metrics via SNMP</div>
                <div class="auth-required">🔒 Requires: Bearer Token</div>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/snmp/scan-hp/{ip}?community=public</div>
                <div class="description">Scan HP-specific ink OIDs</div>
                <div class="auth-required">🔒 Requires: Bearer Token</div>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/snmp/hp-web/{ip}</div>
                <div class="description">Get HP ink levels via web interface</div>
                <div class="auth-required">🔒 Requires: Bearer Token</div>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <div class="url">/api/snmp/profiles</div>
                <div class="description">Get SNMP profiles</div>
            </div>

            <div class="endpoint">
                <span class="method post">POST</span>
                <div class="url">/api/snmp/profiles</div>
                <div class="description">Create SNMP profile</div>
            </div>
        </div>

        <div class="section">
            <h2>⚙️ Background Services</h2>
            <div class="grid">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <h3>🔄 SNMP Polling</h3>
                    <p>Automated printer monitoring every 30 seconds</p>
                    <div class="code">php index.php cli/poller poll_all</div>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <h3>🧹 Alert Cleanup</h3>
                    <p>Clean old acknowledged alerts daily</p>
                    <div class="code">php index.php cli/poller cleanup_alerts</div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>📋 Response Formats</h2>
            <div class="example">
                <h4>Success Response:</h4>
                <div class="code">{
  "success": true,
  "data": { ... },
  "message": "Operation successful"
}</div>
            </div>
            <div class="example">
                <h4>Error Response:</h4>
                <div class="code">{
  "success": false,
  "error": "Error message",
  "message": "Operation failed"
}</div>
            </div>
        </div>

        <div class="section">
            <h2>🔧 Setup Instructions</h2>
            <div class="example">
                <h4>1. Database Setup:</h4>
                <div class="code">mysql -u username -p printer_monitoring < database/setup.sql</div>
            </div>
            <div class="example">
                <h4>2. Configure Database:</h4>
                <div class="code">// application/config/database.php
$db['default']['database'] = 'printer_monitoring';</div>
            </div>
            <div class="example">
                <h4>3. Setup Cron Jobs:</h4>
                <div class="code"># Every 30 seconds
* * * * * cd /path/to/project && php index.php cli/poller poll_all
* * * * * sleep 30 && cd /path/to/project && php index.php cli/poller poll_all

# Daily cleanup at 2 AM
0 2 * * * cd /path/to/project && php index.php cli/poller cleanup_alerts</div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 40px; padding: 20px; background: white; border-radius: 10px;">
            <p style="color: #666;">🚀 Printer Monitoring API v1.0.0 | Built with CodeIgniter 3</p>
            <p style="color: #666; margin-top: 10px;">Ready for production use | Real-time monitoring | Enterprise grade</p>
        </div>
    </div>

    <script>
        function copyToClipboard(element) {
            // Get only the JSON text, exclude the indicator
            const codeText = element.childNodes[0].textContent || element.textContent;
            // Clean up the text - remove indicator text
            const cleanText = codeText.replace(/📋 Click to copy/g, '').trim();
            
            navigator.clipboard.writeText(cleanText).then(function() {
                // Show success feedback
                const originalBg = element.style.backgroundColor;
                element.style.backgroundColor = '#d4edda';
                element.style.borderColor = '#28a745';
                
                // Create and show copy notification
                const notification = document.createElement('div');
                notification.textContent = '✓ Copied!';
                notification.style.cssText = `
                    position: absolute;
                    top: 8px;
                    right: 8px;
                    background: #28a745;
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 0.8em;
                    z-index: 1000;
                `;
                element.style.position = 'relative';
                element.appendChild(notification);
                
                setTimeout(() => {
                    element.style.backgroundColor = originalBg;
                    element.style.borderColor = '#007bff';
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
                alert('Failed to copy to clipboard');
            });
        }

        // Add copy indicators to clickable code blocks
        document.addEventListener('DOMContentLoaded', function() {
            const copyableBlocks = document.querySelectorAll('.code[onclick]');
            copyableBlocks.forEach(block => {
                // Create wrapper for JSON content
                const jsonContent = block.textContent;
                block.innerHTML = '';
                
                // Add JSON content
                const jsonDiv = document.createElement('div');
                jsonDiv.textContent = jsonContent;
                block.appendChild(jsonDiv);
                
                // Add indicator as separate element
                const indicator = document.createElement('div');
                indicator.innerHTML = '📋 Click to copy';
                indicator.style.cssText = `
                    position: absolute;
                    top: -25px;
                    right: 0;
                    font-size: 0.8em;
                    color: #007bff;
                    background: white;
                    padding: 2px 6px;
                    border-radius: 4px;
                    border: 1px solid #007bff;
                    pointer-events: none;
                `;
                block.style.position = 'relative';
                block.appendChild(indicator);
            });
        });
    </script>
</body>
</html>