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
                $value = $this->_snmp_get($ip_address, $community, $oid, $timeout, $retries);
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

    public function get_printer_details($ip_address, $community = 'public')
    {
        $timeout = 5000000;
        $retries = 2;
        $data = [];

        try {
            // 1. Printer Information
            $data['printer_info'] = [
                'name' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.1.5.0'), // sysName
                'model' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.25.3.2.1.3.1'), // hrDeviceDescr
                'serial_number' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.5.1.1.17.1'), // prtGeneralSerialNumber
                'engine_cycles' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.10.2.1.4.1.1'), // prtMarkerLifeCount
            ];

            // 2. Memory Information
            // Walking hrStorageTable finding RAM (usually type .1.3.6.1.2.1.25.2.1.2)
            $storageStats = $this->get_memory_info($ip_address, $community);
            $data['memory'] = $storageStats;

            // 3. Event Log (Generic)
            $data['event_log'] = [
                'max_entries' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.15.1.1.1.1'), // prtConsoleLogMaxEntries
                'current_entries' => 0 // Would need walk of table 1.3.6.1.2.1.43.15.1.1
            ];

            // 4. Paper Trays
            $data['paper_trays'] = $this->get_paper_trays($ip_address, $community);

            // 5. Cartridge Information
            $data['cartridges'] = $this->get_cartridge_info($ip_address, $community);

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
        return ($val !== false) ? trim(trim($val, '"'), ' ') : null;
    }

    private function get_memory_info($ip, $community)
    {
        // Simple walk on hrStorageDescr and hrStorageSize
        // OID base: 1.3.6.1.2.1.25.2.3.1
        $mem = ['on_board' => 'Unknown', 'total_usable' => 'Unknown'];

        // This is a simplification. Real impl needs walking hrStorageType to find RAM.
        // Assuming typical HP printer responses for now or generic:
        $sizes = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.25.2.3.1.5'); // hrStorageSize
        $descrs = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.25.2.3.1.3'); // hrStorageDescr

        if ($sizes && $descrs) {
            $total = 0;
            foreach ($sizes as $oid => $size) {
                // simple sum for now as example
                $total += (int) $size;
            }
            // Convert assuming units (often blocks). Hard to be precise without hrStorageAllocationUnits
            $mem['total_usable'] = $total . ' Allocation Units';
        }
        return $mem;
    }

    private function get_paper_trays($ip, $community)
    {
        $trays = [];
        // prtInputTable: 1.3.6.1.2.1.43.8.2.1
        // .10 = Name, .11 = Vendor Name, .12 = Model, .18 = Name (Input Name)
        // Capacity: 1.3.6.1.2.1.43.8.2.1.10 (max capacity)
        // Current Level: 1.3.6.1.2.1.43.8.2.1.10 (current level - distinct OID usually)

        $names = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.43.8.2.1.18'); // prtInputName
        $capacities = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.43.8.2.1.10'); // prtInputMaxCapacity

        if ($names) {
            foreach ($names as $oid => $val) {
                // Extract index
                $idx = substr($oid, strrpos($oid, '.') + 1);
                $trays[] = [
                    'name' => trim($val, '"'),
                    'capacity' => isset($capacities["iso.3.6.1.2.1.43.8.2.1.10.$idx"]) ? $capacities["iso.3.6.1.2.1.43.8.2.1.10.$idx"] : 'Unknown'
                ];
            }
        }
        return $trays;
    }

    private function get_cartridge_info($ip, $community)
    {
        // prtMarkerSuppliesTable: 1.3.6.1.2.1.43.11.1.1
        // .6 = Description
        // .8 = Max Capacity
        // .9 = Level

        $supplies = [];
        $descriptions = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.43.11.1.1.6');
        $levels = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.43.11.1.1.9');
        $maxs = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.43.11.1.1.8');

        if ($descriptions) {
            foreach ($descriptions as $oid => $val) {
                $idx = substr($oid, strrpos($oid, '.') + 1);

                // HP Specific for dates/pages usually in private MIBs
                // e.g. 1.3.6.1.4.1.11.2.3.9.4.2.1.4.1.2 (for HP)

                $supplies[] = [
                    'description' => trim($val, '"'),
                    'level' => isset($levels["iso.3.6.1.2.1.43.11.1.1.9.$idx"]) ? $levels["iso.3.6.1.2.1.43.11.1.1.9.$idx"] : 0,
                    'max' => isset($maxs["iso.3.6.1.2.1.43.11.1.1.8.$idx"]) ? $maxs["iso.3.6.1.2.1.43.11.1.1.8.$idx"] : 0
                ];
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
            // Return dummy data for specific OIDs
            $dummy_data = [
                '1.3.6.1.2.1.1.1.0' => 'HP LaserJet Pro M402dn - Simulation',
                '1.3.6.1.2.1.25.3.2.1.3.1' => 'HP LaserJet Pro M402dn',
                '1.3.6.1.2.1.25.3.5.1.1.1' => 3, // Idle
                '1.3.6.1.2.1.1.5.0' => 'PRINTER-01',
                '1.3.6.1.2.1.43.5.1.1.17.1' => 'VNC3D08881',
                '1.3.6.1.2.1.43.10.2.1.4.1.1' => 10500, // Page count
                '1.3.6.1.2.1.43.15.1.1.1.1' => 100,

                // Toners
                '1.3.6.1.2.1.43.11.1.1.9.1.1' => 45, // Black
                '1.3.6.1.2.1.43.11.1.1.9.1.2' => 80, // Cyan
                '1.3.6.1.2.1.43.11.1.1.9.1.3' => 75, // Magenta
                '1.3.6.1.2.1.43.11.1.1.9.1.4' => 10, // Yellow

                // Paper
                '1.3.6.1.2.1.43.8.2.1.10.1.1' => 500
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
                    'iso.3.6.1.2.1.43.11.1.1.6.1' => 'Black Cartridge',
                    'iso.3.6.1.2.1.43.11.1.1.6.2' => 'Cyan Cartridge',
                    'iso.3.6.1.2.1.43.11.1.1.6.3' => 'Magenta Cartridge',
                    'iso.3.6.1.2.1.43.11.1.1.6.4' => 'Yellow Cartridge',
                ],
                '1.3.6.1.2.1.43.11.1.1.9' => [ // Levels
                    'iso.3.6.1.2.1.43.11.1.1.9.1' => 45,
                    'iso.3.6.1.2.1.43.11.1.1.9.2' => 80,
                    'iso.3.6.1.2.1.43.11.1.1.9.3' => 75,
                    'iso.3.6.1.2.1.43.11.1.1.9.4' => 10,
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