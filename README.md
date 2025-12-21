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

## API Endpoints

- `POST /auth/login` - User authentication
- `GET /printer` - Get printer list
- `GET /printer/{id}` - Get specific printer details
- `POST /printer` - Add new printer
- `GET /alert` - Get alerts
- `GET /snmp/{ip}` - Get SNMP data from printer

## Configuration

Update configuration files in `application/config/`:
- `config.php` - Base URL and encryption key
- `database.php` - Database connection settings
- `rest.php` - REST API configuration

## License

MIT License