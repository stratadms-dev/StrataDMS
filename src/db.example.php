<?php
/**
 * Database Configuration Template
 * 
 * Instructions:
 * 1. Copy this file and rename it to 'db.php'.
 * 2. Update the variables below with your actual PostgreSQL database credentials.
 */

$host = 'localhost';
$dbname = 'stratadms';
$user = 'your_database_user';
$password = 'your_database_password';

$dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
    
    // Auto-migrate settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(255) PRIMARY KEY,
            setting_value TEXT
        );
        INSERT INTO settings (setting_key, setting_value) VALUES 
        ('min_password_length', '8'),
        ('logo_path', '/assets/images/StrataDMSLogo.png')
        ON CONFLICT (setting_key) DO NOTHING;
        
        UPDATE settings SET setting_value = '/assets/images/StrataDMSLogo.png' WHERE setting_key = 'logo_path' AND setting_value = '/assets/stratadms_logo.png';
    ");
} catch (\PDOException $e) {
    // In a production environment, log this error instead of echoing it
    die("Database connection failed: " . $e->getMessage());
}
?>
