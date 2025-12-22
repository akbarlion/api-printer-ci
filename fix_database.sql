-- Add missing columns to Printers table
ALTER TABLE Printers 
ADD COLUMN printerType ENUM('inkjet', 'laser', 'unknown') DEFAULT 'unknown' AFTER model,
ADD COLUMN snmpCommunity VARCHAR(50) DEFAULT 'public' AFTER snmpProfile;