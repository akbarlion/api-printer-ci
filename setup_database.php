<?php
// Quick database setup
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
    
    // Create tables
    $sql = file_get_contents('setup_fixed.sql');
    $pdo->exec($sql);
    
    echo "✅ Tables created successfully!\n";
    echo "🚀 Database setup complete!\n";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>