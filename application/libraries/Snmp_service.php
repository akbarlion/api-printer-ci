<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Snmp_service
{
    private $simulation = false;

    public function set_simulation($enabled)
    {
        $this->simulation = $enabled;
    }

    public function test_connection($ip_address, $snmp_profile = [])
    {
        // Default SNMP settings
        $community = $snmp_profile['community'] ?? 'public';
        $version = $snmp_profile['version'] ?? '2c';
        $timeout = $snmp_profile['timeout'] ?? 5000000; // 5 seconds in microseconds
        $retries = $snmp_profile['retries'] ?? 3;

        try {
            // Test basic SNMP connection
            $result = $this->_snmp_get($ip_address, $community, '1.3.6.1.2.1.1.1.0', $timeout, $retries);

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
            $model = $this->_snmp_get($ip_address, $community, '1.3.6.1.2.1.25.3.2.1.3.1', $timeout, $retries);
            if ($model) {
                $printer_info['model'] = trim($model, '"');
            }

            // Printer status
            $status = $this->_snmp_get($ip_address, $community, '1.3.6.1.2.1.25.3.5.1.1.1', $timeout, $retries);
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

    public function check_internet_connection($ip_address, $community = 'public')
    {
        try {
            // 1. Cek interface status (IF-MIB)
            // ifOperStatus.1 = up(1), down(2)
            $if_status = $this->snmp_get(
                $ip_address,
                $community,
                '1.3.6.1.2.1.2.2.1.8.1'
            );

            if (!$if_status || (int) $if_status !== 1) {
                return [
                    'connected' => false,
                    'reason' => 'Network interface down'
                ];
            }

            // 2. Cek default gateway
            // ipRouteNextHop
            $gateway = $this->snmp_get(
                $ip_address,
                $community,
                '1.3.6.1.2.1.4.21.1.7.0.0.0.0'
            );

            if (!$gateway) {
                return [
                    'connected' => false,
                    'reason' => 'No default gateway'
                ];
            }

            return [
                'connected' => true,
                'gateway' => $gateway,
                'message' => 'Printer connected to network (internet assumed available)'
            ];

        } catch (Exception $e) {
            return [
                'connected' => false,
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
                $level = $this->_snmp_get($ip_address, $community, $oid, $timeout, $retries);
                if ($level !== false) {
                    $color = ['black', 'cyan', 'magenta', 'yellow'][$i];
                    $metrics['toner'][$color] = (int) $level;
                }
            }

            // Paper levels
            $paper_level = $this->_snmp_get($ip_address, $community, '1.3.6.1.2.1.43.8.2.1.10.1.1', $timeout, $retries);
            if ($paper_level !== false) {
                $metrics['paper_level'] = (int) $paper_level;
            }

            // Printer status
            $status = $this->_snmp_get($ip_address, $community, '1.3.6.1.2.1.25.3.5.1.1.1', $timeout, $retries);
            if ($status !== false) {
                $metrics['status'] = $this->parse_printer_status($status);
            }

            // Page count
            $page_count = $this->_snmp_get($ip_address, $community, '1.3.6.1.2.1.43.10.2.1.4.1.1', $timeout, $retries);
            if ($page_count !== false) {
                $metrics['page_count'] = (int) $page_count;
            }

            return $metrics;
        } catch (Exception $e) {
            throw new Exception('Failed to get printer metrics: ' . $e->getMessage());
        }
    }

    public function test_custom_oid($ip_address, $oid, $community = 'public')
    {
        try {
            $timeout = 5000000;
            $retries = 3;

            $result = $this->_snmp_get($ip_address, $community, $oid, $timeout, $retries);

            if ($result === false) {
                return [
                    'success' => false,
                    'message' => 'SNMP query failed',
                    'oid' => $oid,
                    'ip' => $ip_address,
                    'community' => $community
                ];
            }

            // Clean the result
            $cleaned = $this->snmp_get($ip_address, $community, $oid);

            return [
                'success' => true,
                'message' => 'SNMP query successful',
                'oid' => $oid,
                'ip' => $ip_address,
                'community' => $community,
                'raw_value' => $result,
                'cleaned_value' => $cleaned
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SNMP query error',
                'oid' => $oid,
                'ip' => $ip_address,
                'error' => $e->getMessage()
            ];
        }
    }

    public function get_printer_details($ip_address, $community = 'public')
    {
        $data = [];

        try {
            // 1. Printer Information - Using standard Printer MIB OIDs
            $data['printer_info'] = [
                'name' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.1.5.0'), // sysName
                'model' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.25.3.2.1.3.1'), // hrDeviceDescr
                'serial_number' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.5.1.1.17.1'), // prtGeneralSerialNumber
                'printer_name' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.5.1.1.16.1'), // prtGeneralPrinterName (new in v2)
                'engine_cycles' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.10.2.1.4.1.1'), // prtMarkerLifeCount
                'firmware' => $this->snmp_get($ip_address, $community, '1.3.6.1.4.1.11.2.3.9.4.2.1.1.3.3.0'), // HP firmware
                'console_display' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.16.5.1.2.1.1'), // prtConsoleDisplayBufferText
            ];

            // 2. Memory Information
            $data['memory'] = $this->get_memory_info($ip_address, $community);

            // 3. Event Log - Using correct Printer MIB OIDs
            $data['event_log'] = [
                'max_entries' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.18.1.1.2.1'), // prtAlertTableMaximumSize
                'current_entries' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.18.1.1.1.1'), // prtAlertIndex (count)
                'critical_events' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.5.1.1.18.1'), // prtAlertCriticalEvents (new in v2)
                'all_events' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.5.1.1.19.1'), // prtAlertAllEvents (new in v2)
            ];

            // 4. Paper Trays
            $data['paper_trays'] = $this->get_enhanced_paper_trays($ip_address, $community);

            // 5. Basic supplies info (toner levels)
            $data['supplies'] = $this->get_basic_supplies($ip_address, $community);

            return [
                'success' => true,
                'data' => $data
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch details',
                'error' => $e->getMessage()
            ];
        }
    }

    private function snmp_get($ip, $community, $oid)
    {
        $val = $this->_snmp_get($ip, $community, $oid, 2000000, 2);
        if ($val !== false) {
            // Clean SNMP response format (remove STRING:, Counter32:, etc.)
            $cleaned = trim(trim($val, '"'), ' ');
            // Remove SNMP type prefixes
            $cleaned = preg_replace('/^(STRING|Counter32|INTEGER|Gauge32|TimeTicks):\s*"?/', '', $cleaned);
            $cleaned = trim($cleaned, '"');
            return $cleaned;
        }
        return null;
    }

    private function get_memory_info($ip, $community)
    {
        // Try multiple memory OIDs for better compatibility
        $memory_oids = [
            'hp_onboard' => '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.4.1.0', // HP onboard memory
            'prt_memory' => '1.3.6.1.2.1.43.5.1.1.3.1', // prtGeneralCurrentLocalization memory
            'hr_memory' => '1.3.6.1.2.1.25.2.3.1.5.1', // hrStorageSize RAM
        ];
        
        $onboard = null;
        foreach ($memory_oids as $type => $oid) {
            $result = $this->snmp_get($ip, $community, $oid);
            if ($result !== null) {
                $onboard = $result;
                break;
            }
        }

        // Try to get storage info with SNMP walk
        $sizes = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.25.2.3.1.5'); // hrStorageSize
        $descrs = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.25.2.3.1.3'); // hrStorageDescr

        $total = 0;
        if ($sizes && is_array($sizes)) {
            foreach ($sizes as $oid => $size) {
                if (is_numeric($size)) {
                    $total += (int) $size;
                }
            }
        }

        return [
            'on_board' => $onboard ? $onboard . ' MB' : 'Not available',
            'total_usable' => $total > 0 ? $total . ' Allocation Units' : 'Not available'
        ];
    }

    private function get_enhanced_paper_trays($ip, $community)
    {
        $trays = [];

        // Standard printer MIB OIDs for input trays
        $tray_oids = [
            'name' => '1.3.6.1.2.1.43.8.2.1.18',      // prtInputName
            'type' => '1.3.6.1.2.1.43.8.2.1.2',       // prtInputType
            'capacity' => '1.3.6.1.2.1.43.8.2.1.9',   // prtInputMaxCapacity
            'current' => '1.3.6.1.2.1.43.8.2.1.10',   // prtInputCurrentLevel
            'status' => '1.3.6.1.2.1.43.8.2.1.11',    // prtInputStatus
            'media' => '1.3.6.1.2.1.43.8.2.1.12',     // prtInputMediaName
        ];

        $names = $this->_snmp_walk($ip, $community, $tray_oids['name']);
        $capacities = $this->_snmp_walk($ip, $community, $tray_oids['capacity']);
        $currents = $this->_snmp_walk($ip, $community, $tray_oids['current']);
        $medias = $this->_snmp_walk($ip, $community, $tray_oids['media']);

        if ($names && is_array($names)) {
            foreach ($names as $oid => $val) {
                $idx = substr($oid, strrpos($oid, '.') + 1);
                $cleanName = preg_replace('/^(STRING|Counter32|INTEGER):\s*"?/', '', $val);
                $cleanName = trim($cleanName, '"');

                // Try different OID formats for capacity and current level
                $capacity_key = "iso.3.6.1.2.1.43.8.2.1.9.$idx";
                $current_key = "iso.3.6.1.2.1.43.8.2.1.10.$idx";
                $media_key = "iso.3.6.1.2.1.43.8.2.1.12.$idx";
                
                // Alternative key formats
                if (!isset($capacities[$capacity_key])) {
                    $capacity_key = "1.3.6.1.2.1.43.8.2.1.9.$idx";
                }
                if (!isset($currents[$current_key])) {
                    $current_key = "1.3.6.1.2.1.43.8.2.1.10.$idx";
                }
                if (!isset($medias[$media_key])) {
                    $media_key = "1.3.6.1.2.1.43.8.2.1.12.$idx";
                }

                $trays[] = [
                    'name' => $cleanName,
                    'capacity' => isset($capacities[$capacity_key]) ? (int) $capacities[$capacity_key] : 'Unknown',
                    'current_level' => isset($currents[$current_key]) ? (int) $currents[$current_key] : 'Unknown',
                    'media_type' => isset($medias[$media_key]) ? trim($medias[$media_key], '"') : 'Plain'
                ];
            }
        }
        return $trays;
    }

    private function get_basic_supplies($ip, $community)
    {
        $supplies = [];
        
        // Try multiple supply level OIDs for better compatibility
        $supply_oids = [
            'black' => ['1.3.6.1.2.1.43.11.1.1.9.1.1', '1.3.6.1.2.1.43.11.1.1.9.1'],
            'cyan' => ['1.3.6.1.2.1.43.11.1.1.9.1.2', '1.3.6.1.2.1.43.11.1.1.9.2'], 
            'magenta' => ['1.3.6.1.2.1.43.11.1.1.9.1.3', '1.3.6.1.2.1.43.11.1.1.9.3'],
            'yellow' => ['1.3.6.1.2.1.43.11.1.1.9.1.4', '1.3.6.1.2.1.43.11.1.1.9.4']
        ];
        
        foreach ($supply_oids as $color => $oids) {
            $level = null;
            // Try each OID until we get a valid response
            foreach ($oids as $oid) {
                $result = $this->snmp_get($ip, $community, $oid);
                if ($result !== null && is_numeric($result)) {
                    $level = (int) $result;
                    break;
                }
            }
            
            // If still no result, try HP-specific OIDs
            if ($level === null) {
                $hp_oid = '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.' . (array_search($color, array_keys($supply_oids)) + 1) . '.0';
                $result = $this->snmp_get($ip, $community, $hp_oid);
                if ($result !== null && is_numeric($result)) {
                    $level = (int) $result;
                }
            }
            
            $supplies[$color] = $level !== null ? $level : 'Unknown';
        }
        
        return $supplies;
    }

    private function get_printer_supplies($ip, $community)
    {
        // Detect printer type from model name
        $model = $this->snmp_get($ip, $community, '1.3.6.1.2.1.25.3.2.1.3.1');
        $isLaser = $this->is_laser_printer($model);

        if ($isLaser) {
            return $this->get_laser_toner_info($ip, $community);
        } else {
            return $this->get_hp_ink_info($ip, $community);
        }
    }

    private function is_laser_printer($model)
    {
        if (!$model)
            return false;

        $laserKeywords = ['LaserJet', 'Laser', 'M404', 'M402', 'M403', 'P1102', 'P2055', 'CP1025'];
        $inkjetKeywords = ['Smart Tank', 'OfficeJet', 'DeskJet', 'Envy', 'Photosmart'];

        foreach ($laserKeywords as $keyword) {
            if (stripos($model, $keyword) !== false) {
                return true;
            }
        }

        foreach ($inkjetKeywords as $keyword) {
            if (stripos($model, $keyword) !== false) {
                return false;
            }
        }

        // Default to laser if unknown
        return true;
    }

    private function get_laser_toner_info($ip, $community)
    {
        $supplies = [];

        // HP LaserJet toner OIDs
        $hp_toner_oids = [
            '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.1.0' => ['color' => 'Black', 'type' => 'toner'],
            '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.2.0' => ['color' => 'Cyan', 'type' => 'toner'],
            '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.3.0' => ['color' => 'Magenta', 'type' => 'toner'],
            '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.4.0' => ['color' => 'Yellow', 'type' => 'toner'],
        ];

        // Try HP-specific toner level OIDs first
        foreach ($hp_toner_oids as $oid => $info) {
            $level = $this->snmp_get($ip, $community, $oid);
            if ($level !== null) {
                $supplies[] = [
                    'description' => $info['color'] . ' Toner Cartridge',
                    'level' => (int) $level,
                    'max' => 100,
                    'serial_number' => $this->snmp_get($ip, $community, str_replace('.3.', '.6.', $oid)),
                    'pages_printed' => $this->snmp_get($ip, $community, str_replace('.3.', '.7.', $oid)),
                    'install_date' => $this->snmp_get($ip, $community, str_replace('.3.', '.8.', $oid))
                ];
            }
        }

        // Fallback to standard printer MIB for laser printers
        if (empty($supplies)) {
            $descriptions = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.43.11.1.1.6');
            $levels = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.43.11.1.1.9');
            $maxs = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.43.11.1.1.8');

            if ($descriptions) {
                foreach ($descriptions as $oid => $val) {
                    $idx = substr($oid, strrpos($oid, '.') + 1);
                    $cleanDesc = preg_replace('/^(STRING|Counter32|INTEGER):\s*"?/', '', $val);
                    $cleanDesc = trim($cleanDesc, '"');

                    // Skip non-toner supplies (like waste toner, drum, etc.)
                    if (stripos($cleanDesc, 'toner') !== false || stripos($cleanDesc, 'cartridge') !== false) {
                        $supplies[] = [
                            'description' => $cleanDesc,
                            'level' => isset($levels["iso.3.6.1.2.1.43.11.1.1.9.$idx"]) ? (int) $levels["iso.3.6.1.2.1.43.11.1.1.9.$idx"] : null,
                            'max' => isset($maxs["iso.3.6.1.2.1.43.11.1.1.8.$idx"]) ? (int) $maxs["iso.3.6.1.2.1.43.11.1.1.8.$idx"] : null,
                            'serial_number' => null,
                            'pages_printed' => null,
                            'install_date' => null
                        ];
                    }
                }
            }
        }
    }

    private function get_hp_ink_info($ip, $community)
    {
        $supplies = [];

        // HP Smart Tank specific OIDs for ink levels
        $hp_ink_oids = [
            '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.1.0' => ['color' => 'Black', 'type' => 'ink'],
            '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.2.0' => ['color' => 'Cyan', 'type' => 'ink'],
            '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.3.0' => ['color' => 'Magenta', 'type' => 'ink'],
            '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.4.0' => ['color' => 'Yellow', 'type' => 'ink'],
        ];

        // Try HP-specific ink level OIDs first
        foreach ($hp_ink_oids as $oid => $info) {
            $level = $this->snmp_get($ip, $community, $oid);
            if ($level !== null) {
                $supplies[] = [
                    'description' => $info['color'] . ' Ink Tank',
                    'level' => (int) $level,
                    'max' => 100,
                    'serial_number' => $this->snmp_get($ip, $community, str_replace('.3.', '.6.', $oid)),
                    'pages_printed' => $this->snmp_get($ip, $community, str_replace('.3.', '.7.', $oid)),
                    'install_date' => $this->snmp_get($ip, $community, str_replace('.3.', '.8.', $oid))
                ];
            }
        }

        // Fallback to standard printer MIB if HP-specific fails
        if (empty($supplies)) {
            $descriptions = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.43.11.1.1.6');
            $levels = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.43.11.1.1.9');
            $maxs = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.43.11.1.1.8');

            if ($descriptions) {
                foreach ($descriptions as $oid => $val) {
                    $idx = substr($oid, strrpos($oid, '.') + 1);
                    $cleanDesc = preg_replace('/^(STRING|Counter32|INTEGER):\s*"?/', '', $val);
                    $cleanDesc = trim($cleanDesc, '"');

                    $supplies[] = [
                        'description' => $cleanDesc,
                        'level' => isset($levels["iso.3.6.1.2.1.43.11.1.1.9.$idx"]) ? (int) $levels["iso.3.6.1.2.1.43.11.1.1.9.$idx"] : null,
                        'max' => isset($maxs["iso.3.6.1.2.1.43.11.1.1.8.$idx"]) ? (int) $maxs["iso.3.6.1.2.1.43.11.1.1.8.$idx"] : null,
                        'serial_number' => null,
                        'pages_printed' => null,
                        'install_date' => null
                    ];
                }
            }
        }

        return $supplies;
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

    private function _snmp_get($ip, $community, $oid, $timeout = 1000000, $retries = 2)
    {
        if ($this->simulation) {
            // Add HP-specific OIDs for simulation
            $dummy_data = [
                '1.3.6.1.2.1.1.1.0' => 'HP Smart Tank 750 - Simulation',
                '1.3.6.1.2.1.25.3.2.1.3.1' => 'HP Smart Tank 750',
                '1.3.6.1.2.1.25.3.5.1.1.1' => 3, // Idle
                '1.3.6.1.2.1.1.5.0' => 'HP-Smart-Tank-750',
                '1.3.6.1.2.1.43.5.1.1.17.1' => 'CN' . strtoupper(substr(md5($ip . 'hp750'), 0, 8)),
                '1.3.6.1.2.1.43.10.2.1.4.1.1' => 2450, // Page count
                '1.3.6.1.2.1.43.15.1.1.1.1' => 50,

                // HP-specific memory
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.4.1.0' => 128, // Onboard memory in MB

                // Ink levels for inkjet
                '1.3.6.1.2.1.43.11.1.1.9.1.1' => 90, // Black
                '1.3.6.1.2.1.43.11.1.1.9.1.2' => 75, // Cyan
                '1.3.6.1.2.1.43.11.1.1.9.1.3' => 80, // Magenta
                '1.3.6.1.2.1.43.11.1.1.9.1.4' => 65, // Yellow

                // HP-specific cartridge data (unique per IP)
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.6.1.0' => 'HP-' . strtoupper(substr(md5($ip . 'black'), 0, 8)), // Black serial
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.6.2.0' => 'HP-' . strtoupper(substr(md5($ip . 'cyan'), 0, 8)), // Cyan serial
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.6.3.0' => 'HP-' . strtoupper(substr(md5($ip . 'magenta'), 0, 8)), // Magenta serial
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.6.4.0' => 'HP-' . strtoupper(substr(md5($ip . 'yellow'), 0, 8)), // Yellow serial

                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.7.1.0' => 1250, // Black pages
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.7.2.0' => 850, // Cyan pages
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.7.3.0' => 920, // Magenta pages
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.7.4.0' => 1100, // Yellow pages

                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.8.1.0' => '2025-12-15', // Black install
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.8.2.0' => '2025-11-20', // Cyan install
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.8.3.0' => '2025-10-10', // Magenta install
                '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.8.4.0' => '2025-09-05', // Yellow install

                // Paper
                '1.3.6.1.2.1.43.8.2.1.10.1.1' => 100
            ];

            return $dummy_data[$oid] ?? false;
        }

        return @snmp2_get($ip, $community, $oid, $timeout, $retries);
    }

    private function _snmp_walk($ip, $community, $oid, $timeout = 1000000, $retries = 2)
    {
        if ($this->simulation) {
            // Return dummy arrays for walks
            $dummy_walks = [
                '1.3.6.1.2.1.43.8.2.1.18' => [ // prtInputName
                    'iso.3.6.1.2.1.43.8.2.1.18.1' => 'Tray 1',
                    'iso.3.6.1.2.1.43.8.2.1.18.2' => 'Tray 2'
                ],
                '1.3.6.1.2.1.43.8.2.1.10' => [ // prtInputMaxCapacity
                    'iso.3.6.1.2.1.43.8.2.1.10.1' => 100,
                    'iso.3.6.1.2.1.43.8.2.1.10.2' => 500
                ],
                // Cartridges
                '1.3.6.1.2.1.43.11.1.1.6' => [ // Descriptions
                    'iso.3.6.1.2.1.43.11.1.1.6.1' => 'Black Ink Cartridge',
                    'iso.3.6.1.2.1.43.11.1.1.6.2' => 'Cyan Ink Cartridge',
                    'iso.3.6.1.2.1.43.11.1.1.6.3' => 'Magenta Ink Cartridge',
                    'iso.3.6.1.2.1.43.11.1.1.6.4' => 'Yellow Ink Cartridge',
                ],
                '1.3.6.1.2.1.43.11.1.1.9' => [ // Levels
                    'iso.3.6.1.2.1.43.11.1.1.9.1' => 90,
                    'iso.3.6.1.2.1.43.11.1.1.9.2' => 75,
                    'iso.3.6.1.2.1.43.11.1.1.9.3' => 80,
                    'iso.3.6.1.2.1.43.11.1.1.9.4' => 65,
                ],
                '1.3.6.1.2.1.43.11.1.1.8' => [ // Max
                    'iso.3.6.1.2.1.43.11.1.1.8.1' => 100,
                    'iso.3.6.1.2.1.43.11.1.1.8.2' => 100,
                    'iso.3.6.1.2.1.43.11.1.1.8.3' => 100,
                    'iso.3.6.1.2.1.43.11.1.1.8.4' => 100,
                ],
                // Storage
                '1.3.6.1.2.1.25.2.3.1.5' => [ // Size
                    'iso.3.6.1.2.1.25.2.3.1.5.1' => 1024,
                    'iso.3.6.1.2.1.25.2.3.1.5.2' => 2048,
                ],
                '1.3.6.1.2.1.25.2.3.1.3' => [ // Descr
                    'iso.3.6.1.2.1.25.2.3.1.3.1' => 'RAM',
                    'iso.3.6.1.2.1.25.2.3.1.3.2' => 'Flash',
                ]
            ];

            return $dummy_walks[$oid] ?? false;
        }

        return @snmp2_real_walk($ip, $community, $oid, $timeout, $retries);
    }
}