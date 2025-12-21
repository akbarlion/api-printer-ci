<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Hp_web_service
{
    public function get_ink_levels($ip_address)
    {
        try {
            // HP printer web interface URLs
            $urls = [
                "http://{$ip_address}/hp/device/InkCartridge/Index",
                "http://{$ip_address}/hp/device/this.LCDispatcher?nav=hp.Usage",
                "http://{$ip_address}/DevMgmt/ConsumableConfigDyn.xml",
                "http://{$ip_address}/hp/device/webAccess/index.htm"
            ];

            $ink_data = [];
            
            foreach ($urls as $url) {
                $response = $this->fetch_url($url);
                if ($response) {
                    $parsed = $this->parse_hp_response($response);
                    if (!empty($parsed)) {
                        $ink_data = array_merge($ink_data, $parsed);
                        break; // Stop at first successful parse
                    }
                }
            }

            if (empty($ink_data)) {
                return [
                    'success' => false,
                    'message' => 'Unable to retrieve ink levels from HP web interface',
                    'data' => []
                ];
            }

            return [
                'success' => true,
                'message' => 'Ink levels retrieved successfully',
                'data' => $ink_data
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error accessing HP web interface',
                'error' => $e->getMessage()
            ];
        }
    }

    private function fetch_url($url, $timeout = 10)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($http_code == 200) ? $response : false;
    }

    private function parse_hp_response($html)
    {
        $ink_levels = [];

        // Try to parse XML format first
        if (strpos($html, '<?xml') !== false) {
            $ink_levels = $this->parse_xml_response($html);
        } else {
            // Parse HTML format
            $ink_levels = $this->parse_html_response($html);
        }

        return $ink_levels;
    }

    private function parse_xml_response($xml)
    {
        try {
            $ink_levels = [
                'black' => null,
                'cyan' => null,
                'magenta' => null,
                'yellow' => null
            ];

            // Extract ConsumableLabelCode and ConsumablePercentageLevelRemaining pairs
            preg_match_all('/<dd:ConsumableLabelCode>([^<]+)<\/dd:ConsumableLabelCode>/', $xml, $label_matches);
            preg_match_all('/<dd:ConsumablePercentageLevelRemaining>([^<]+)<\/dd:ConsumablePercentageLevelRemaining>/', $xml, $level_matches);

            $labels = $label_matches[1] ?? [];
            $levels = array_map('intval', $level_matches[1] ?? []);

            // Match labels with levels - prioritize first occurrence
            for ($i = 0; $i < count($labels) && $i < count($levels); $i++) {
                $label = $labels[$i];
                $level = $levels[$i];

                switch ($label) {
                    case 'K':
                        if ($ink_levels['black'] === null) $ink_levels['black'] = $level;
                        break;
                    case 'C':
                        if ($ink_levels['cyan'] === null) $ink_levels['cyan'] = $level;
                        break;
                    case 'M':
                        if ($ink_levels['magenta'] === null) $ink_levels['magenta'] = $level;
                        break;
                    case 'Y':
                        if ($ink_levels['yellow'] === null) $ink_levels['yellow'] = $level;
                        break;
                    case 'CMY':
                        // CMY cartridge - use for all colors if individual not set
                        if ($ink_levels['cyan'] === null) $ink_levels['cyan'] = $level;
                        if ($ink_levels['magenta'] === null) $ink_levels['magenta'] = $level;
                        if ($ink_levels['yellow'] === null) $ink_levels['yellow'] = $level;
                        break;
                }
            }

            return [
                'blackLevel' => $ink_levels['black'],
                'cyanLevel' => $ink_levels['cyan'],
                'magentaLevel' => $ink_levels['magenta'],
                'yellowLevel' => $ink_levels['yellow'],
                'tonerLevel' => null,
                'printerType' => 'inkjet',
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [];
        }
    }

    private function parse_html_response($html)
    {
        $ink_levels = [];
        
        // Common patterns for HP ink levels in HTML
        $patterns = [
            '/(\w+).*?(\d+)%/i',
            '/ink.*?(\w+).*?(\d+)/i',
            '/cartridge.*?(\w+).*?(\d+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if (count($match) >= 3) {
                        $color = strtolower($match[1]);
                        $level = (int)$match[2];
                        
                        if ($level <= 100 && in_array($color, ['black', 'cyan', 'magenta', 'yellow'])) {
                            $ink_levels[] = [
                                'color' => $color,
                                'level' => $level,
                                'status' => $level > 20 ? 'OK' : ($level > 5 ? 'Low' : 'Very Low')
                            ];
                        }
                    }
                }
                
                if (!empty($ink_levels)) {
                    break; // Stop at first successful pattern match
                }
            }
        }

        return $ink_levels;
    }
}