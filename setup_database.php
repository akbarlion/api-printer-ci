<?php
// Database setup and migration script
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect without database
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS printer_monitoring");
    echo "✅ Database 'printer_monitoring' created successfully!\n";
    
    // Use database
    $pdo->exec("USE printer_monitoring");
    
    // Create tables from setup_fixed.sql
    $sql = file_get_contents('setup_fixed.sql');
    $pdo->exec($sql);
    echo "✅ Tables created successfully!\n";
    
    // Apply additional fixes/migrations
    echo "🔧 Applying database fixes...\n";
    
    // Check if columns exist before adding
    $result = $pdo->query("SHOW COLUMNS FROM Printers LIKE 'printerType'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE Printers ADD COLUMN printerType ENUM('inkjet', 'laser', 'unknown') DEFAULT 'unknown' AFTER model");
        echo "✅ Added printerType column\n";
    }
    
    $result = $pdo->query("SHOW COLUMNS FROM Printers LIKE 'snmpCommunity'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE Printers ADD COLUMN snmpCommunity VARCHAR(50) DEFAULT 'public' AFTER snmpProfile");
        echo "✅ Added snmpCommunity column\n";
    }
    
    echo "🚀 Database setup and migration complete!\n";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>