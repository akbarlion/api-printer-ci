# API Printer CI

A CodeIgniter-based REST API for printer monitoring and management system.

## Features

- Printer status monitoring via SNMP
- Alert management system
- JWT authentication
- RESTful API endpoints
- Database integration for printer data storage

## Requirements

- PHP 5.6 or newer
- MySQL/MariaDB
- SNMP extension for PHP
- Composer

## Installation

1. Clone the repository:
```bash
git clone https://github.com/akbarlion/api-printer-ci.git
cd api-printer-ci
```

2. Install dependencies:
```bash
composer install
```

3. Configure database settings in `application/config/database.php`

4. Run database setup:
```bash
php setup_database.php
```

## Default Login

- Username: `admin`
- Email: `admin@printer-monitoring.com`
- Password: `admin123`
- Role: `admin`

## API Endpoints

- `POST /api/auth/login` - User authentication
- `POST /api/auth/register` - User registration
- `POST /api/auth/refresh` - Refresh JWT token
- `GET /api/printers` - Get printer list
- `GET /api/printers/{id}` - Get specific printer details
- `POST /api/printers` - Add new printer
- `GET /api/alerts` - Get alerts
- `GET /api/snmp/test/{ip}` - Test SNMP connection
- `GET /api/snmp/metrics/{ip}` - Get SNMP metrics

## Configuration

Update configuration files in `application/config/`:
- `config.php` - Base URL and timezone (Asia/Jakarta)
- `database.php` - Database connection settings
- `rest.php` - REST API configuration

## Database

- Database: `printer_monitoring`
- Tables: Users, Printers, PrinterMetrics, PrinterAlerts, SNMPProfiles
- Setup file: `setup_fixed.sql`

## License

MIT License