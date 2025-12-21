-- Create database
CREATE DATABASE IF NOT EXISTS printer_monitoring;

USE printer_monitoring;

-- Users table
CREATE TABLE Users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'operator', 'viewer') DEFAULT 'viewer',
    isActive BOOLEAN DEFAULT TRUE,
    lastLogin DATETIME NULL,
    refresh_token VARCHAR(255) NULL,
    refresh_token_expires DATETIME NULL,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Printers table
CREATE TABLE Printers (
    id VARCHAR(36) PRIMARY KEY DEFAULT(UUID()),
    name VARCHAR(100) NOT NULL,
    ipAddress VARCHAR(15) NOT NULL UNIQUE,
    model VARCHAR(100),
    location VARCHAR(100),
    status ENUM(
        'online',
        'offline',
        'warning',
        'error'
    ) DEFAULT 'offline',
    snmpProfile VARCHAR(50) DEFAULT 'default',
    isActive BOOLEAN DEFAULT TRUE,
    lastPolled DATETIME NULL,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- PrinterMetrics table
CREATE TABLE PrinterMetrics (
    id VARCHAR(36) PRIMARY KEY DEFAULT(UUID()),
    printerId VARCHAR(36) NOT NULL,
    -- Ink levels for inkjet printers
    cyanLevel INT CHECK (
        cyanLevel >= 0
        AND cyanLevel <= 100
    ),
    magentaLevel INT CHECK (
        magentaLevel >= 0
        AND magentaLevel <= 100
    ),
    yellowLevel INT CHECK (
        yellowLevel >= 0
        AND yellowLevel <= 100
    ),
    blackLevel INT CHECK (
        blackLevel >= 0
        AND blackLevel <= 100
    ),
    -- Toner level for laser printers
    tonerLevel INT CHECK (
        tonerLevel >= 0
        AND tonerLevel <= 100
    ),
    -- Common fields
    paperTrayStatus VARCHAR(50),
    pageCounter INT DEFAULT 0,
    deviceStatus VARCHAR(50),
    printerType ENUM('inkjet', 'laser', 'unknown') DEFAULT 'unknown',
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (printerId) REFERENCES Printers (id) ON DELETE CASCADE
);

-- PrinterAlerts table (Fixed foreign key)
CREATE TABLE PrinterAlerts (
    id VARCHAR(36) PRIMARY KEY DEFAULT(UUID()),
    printerId VARCHAR(36) NOT NULL,
    printerName VARCHAR(100) NOT NULL,
    alertType ENUM(
        'toner_low',
        'paper_empty',
        'offline',
        'error'
    ) NOT NULL,
    severity ENUM(
        'low',
        'medium',
        'high',
        'critical'
    ) NOT NULL,
    message TEXT NOT NULL,
    isAcknowledged BOOLEAN DEFAULT FALSE,
    acknowledgedAt DATETIME NULL,
    acknowledgedBy INT NULL,  -- Changed to INT to match Users.id
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (printerId) REFERENCES Printers (id) ON DELETE CASCADE,
    FOREIGN KEY (acknowledgedBy) REFERENCES Users (id) ON DELETE SET NULL
);

-- SNMPProfiles table
CREATE TABLE SNMPProfiles (
    id VARCHAR(36) PRIMARY KEY DEFAULT(UUID()),
    name VARCHAR(50) NOT NULL UNIQUE,
    version ENUM('2c', '3') DEFAULT '2c',
    community VARCHAR(50) DEFAULT 'public',
    username VARCHAR(50),
    authProtocol ENUM('MD5', 'SHA'),
    authPassword VARCHAR(100),
    privProtocol ENUM('DES', 'AES'),
    privPassword VARCHAR(100),
    port INT DEFAULT 161,
    timeout INT DEFAULT 5000,
    retries INT DEFAULT 3,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123)
INSERT INTO
    Users (
        username,
        email,
        password,
        role
    )
VALUES (
        'admin',
        'admin@printer-monitoring.com',
        '$2a$10$CwTycUXWue0Thq9StjUM0uJ8Z8W4uDUO1V.jF1uYnTA.PfSROxtHO',
        'admin'
    );

-- Insert default SNMP profile
INSERT INTO
    SNMPProfiles (name, version, community)
VALUES ('default', '2c', 'public');

-- Create indexes for better performance
CREATE INDEX idx_printers_ip ON Printers (ipAddress);

CREATE INDEX idx_printers_status ON Printers (status);

CREATE INDEX idx_metrics_printer_created ON PrinterMetrics (printerId, createdAt);

CREATE INDEX idx_alerts_printer ON PrinterAlerts (printerId);

CREATE INDEX idx_alerts_acknowledged ON PrinterAlerts (isAcknowledged);