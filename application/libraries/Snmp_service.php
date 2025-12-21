<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Snmp_service
{
    public function test_connection($ip_address, $snmp_profile = [])
    {
        // Default SNMP settings
        $community = $snmp_profile['community'] ?? 'public';
        $version = $snmp_profile['version'] ?? '2c';
        $timeout = $snmp_profile['timeout'] ?? 5000000; // 5 seconds in microseconds
        $retries = $snmp_profile['retries'] ?? 3;

        try {
            // Test basic SNMP connection
            $result = snmp2_get($ip_address, $community, '1.3.6.1.2.1.1.1.0', $timeout, $retries);
            
            if ($result === false) {
                return [
                    'success' => false,
                    'message' => 'SNMP connection failed',
                    'error' => 'Unable to connect to device'
                ];
            }

            // Try to get printer-specific OIDs
            $printer_info = [];
            
            // Printer model
            $model = @snmp2_get($ip_address, $community, '1.3.6.1.2.1.25.3.2.1.3.1', $timeout, $retries);
            if ($model) {
                $printer_info['model'] = trim($model, '"');
            }

            // Printer status
            $status = @snmp2_get($ip_address, $community, '1.3.6.1.2.1.25.3.5.1.1.1', $timeout, $retries);
            if ($status) {
                $printer_info['status'] = $this->parse_printer_status($status);
            }

            return [
                'success' => true,
                'message' => 'SNMP connection successful',
                'data' => [
                    'system_description' => trim($result, '"'),
                    'printer_info' => $printer_info,
                    'ip_address' => $ip_address,
                    'community' => $community
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SNMP connection test failed',
                'error' => $e->getMessage()
            ];
        }
    }

    public function get_printer_metrics($ip_address, $community = 'public')
    {
        try {
            $metrics = [];
            $timeout = 5000000;
            $retries = 3;

            // Toner levels (HP OIDs)
            $toner_oids = [
                '1.3.6.1.2.1.43.11.1.1.9.1.1', // Black toner
                '1.3.6.1.2.1.43.11.1.1.9.1.2', // Cyan toner
                '1.3.6.1.2.1.43.11.1.1.9.1.3', // Magenta toner
                '1.3.6.1.2.1.43.11.1.1.9.1.4'  // Yellow toner
            ];

            foreach ($toner_oids as $i => $oid) {
                $level = @snmp2_get($ip_address, $community, $oid, $timeout, $retries);
                if ($level !== false) {
                    $color = ['black', 'cyan', 'magenta', 'yellow'][$i];
                    $metrics['toner'][$color] = (int)$level;
                }
            }

            // Paper levels
            $paper_level = @snmp2_get($ip_address, $community, '1.3.6.1.2.1.43.8.2.1.10.1.1', $timeout, $retries);
            if ($paper_level !== false) {
                $metrics['paper_level'] = (int)$paper_level;
            }

            // Printer status
            $status = @snmp2_get($ip_address, $community, '1.3.6.1.2.1.25.3.5.1.1.1', $timeout, $retries);
            if ($status !== false) {
                $metrics['status'] = $this->parse_printer_status($status);
            }

            // Page count
            $page_count = @snmp2_get($ip_address, $community, '1.3.6.1.2.1.43.10.2.1.4.1.1', $timeout, $retries);
            if ($page_count !== false) {
                $metrics['page_count'] = (int)$page_count;
            }

            return $metrics;
        } catch (Exception $e) {
            throw new Exception('Failed to get printer metrics: ' . $e->getMessage());
        }
    }

    public function scan_hp_ink_oids($ip_address, $community = 'public')
    {
        try {
            $results = [];
            $timeout = 5000000;
            $retries = 3;

            // HP specific ink OIDs
            $hp_oids = [
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.1.0' => 'Black Ink',
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.2.0' => 'Cyan Ink',
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.3.0' => 'Magenta Ink',
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.4.0' => 'Yellow Ink',
                '1.3.6.1.4.1.11.2.3.9.4.2.1.4.1.2.1.0' => 'Black Cartridge Status',
                '1.3.6.1.4.1.11.2.3.9.4.2.1.4.1.2.2.0' => 'Cyan Cartridge Status',
                '1.3.6.1.4.1.11.2.3.9.4.2.1.4.1.2.3.0' => 'Magenta Cartridge Status',
                '1.3.6.1.4.1.11.2.3.9.4.2.1.4.1.2.4.0' => 'Yellow Cartridge Status'
            ];

            foreach ($hp_oids as $oid => $description) {
                $value = @snmp2_get($ip_address, $community, $oid, $timeout, $retries);
                $results[] = [
                    'oid' => $oid,
                    'description' => $description,
                    'value' => $value !== false ? trim($value, '"') : 'N/A',
                    'success' => $value !== false
                ];
            }

            return $results;
        } catch (Exception $e) {
            throw new Exception('Failed to scan HP ink OIDs: ' . $e->getMessage());
        }
    }

    private function parse_printer_status($status_code)
    {
        $status_map = [
            1 => 'other',
            2 => 'unknown',
            3 => 'idle',
            4 => 'printing',
            5 => 'warmup'
        ];

        return $status_map[$status_code] ?? 'unknown';
    }
}