<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . '/libraries/JWTExceptionWithPayloadInterface.php';
require_once APPPATH . '/libraries/JWT.php';
require_once APPPATH . '/libraries/ExpiredException.php';
require_once APPPATH . '/libraries/BeforeValidException.php';
require_once APPPATH . '/libraries/SignatureInvalidException.php';
require_once APPPATH . '/libraries/JWK.php';
require_once APPPATH . '/libraries/Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Bulk extends CI_Controller
{
    private $jwt_key = '7f8a9b2c4d6e1f3a5b7c9d0e2f4a6b8c1d3e5f7a9b2c4d6e8f0a1b3c5d7e9f2a4b6c8d0e1f3a5b7c9d0e2f4a6b8c';

    public function __construct()
    {
        parent::__construct();
        $this->load->library('Snmp_service');
        $this->load->library('Hp_web_service');
        $this->load->helper('url');
        header('Content-Type: application/json');
    }

    private function verify_token()
    {
        $headers = $this->input->request_headers();
        $token = null;

        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (strpos($auth, 'Bearer ') === 0) {
                $token = substr($auth, 7);
            }
        }

        if (!$token) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Token required']);
            exit;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->jwt_key, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid token']);
            exit;
        }
    }

    public function test($ip)
    {
        if ($this->input->method() !== 'get') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }

        $this->verify_token();

        try {
            $community = $this->input->get('community') ?: 'public';
            $result = $this->snmp_service->test_connection($ip, ['community' => $community]);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function metrics($ip)
    {
        if ($this->input->method() !== 'get') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }

        $this->verify_token();

        try {
            $community = $this->input->get('community') ?: 'public';
            $metrics = $this->snmp_service->get_printer_metrics($ip, $community);

            echo json_encode([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function scan_hp($ip)
    {
        if ($this->input->method() !== 'get') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }

        $this->verify_token();

        try {
            $community = $this->input->get('community') ?: 'public';
            $results = $this->snmp_service->scan_hp_ink_oids($ip, $community);

            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function hp_web($ip)
    {
        if ($this->input->method() !== 'get') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }

        $this->verify_token();

        try {
            $result = $this->hp_web_service->get_ink_levels($ip);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function profiles()
    {
        $method = $this->input->method();

        switch ($method) {
            case 'get':
                $this->get_profiles();
                break;
            case 'post':
                $this->create_profile();
                break;
            default:
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        }
    }

    public function bulk_query()
    {
        if ($this->input->method() !== 'post') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }

        // $this->verify_token();

        try {
            $input = json_decode($this->input->raw_input_stream, true);
            if (!$input) {
                $input = $this->input->post();
            }

            $targets = $input['ips'] ?? $input['targets'] ?? $input;

            if (empty($targets) || !is_array($targets)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ips (array) required']);
                return;
            }

            $results = [];
            foreach ($targets as $t) {
                // allow either string IP or object {ip, community}
                if (is_string($t)) {
                    $ip = $t;
                    $community = 'public';
                } elseif (is_array($t)) {
                    $ip = $t['ip'] ?? $t['ipAddress'] ?? null;
                    $community = $t['community'] ?? $t['snmpCommunity'] ?? 'public';
                } else {
                    $results[] = ['ip' => null, 'success' => false, 'error' => 'invalid target format'];
                    continue;
                }

                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $results[] = ['ip' => $ip, 'success' => false, 'error' => 'invalid ip'];
                    continue;
                }

                try {
                    $this->snmp_service->set_simulation(false);
                    $data = $this->snmp_service->check_internet_connection($ip, $community);
                    $results[] = ['ip' => $ip, 'success' => true, 'data' => $data];
                } catch (Exception $e) {
                    $results[] = ['ip' => $ip, 'success' => false, 'error' => $e->getMessage()];
                }
            }

            echo json_encode(['success' => true, 'results' => $results]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }


    private function get_profiles()
    {
        // Placeholder - return empty array for now
        echo json_encode([]);
    }

    private function create_profile()
    {
        // Placeholder - return success message
        echo json_encode(['message' => 'SNMP profile created']);
    }

    public function test_full_info()
    {
        if ($this->input->method() !== 'post') {
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            return;
        }

        try {
            $input = json_decode($this->input->raw_input_stream, true);

            $ip_address = $input['ipAddress'] ?? $input['ip'] ?? null;
            $community = $input['community'] ?? 'public';

            if (!$ip_address) {
                http_response_code(400);
                echo json_encode(['message' => 'IP address is required']);
                return;
            }

            // Disable simulation for real data
            $this->snmp_service->set_simulation(false);

            // Get full printer details
            $result = $this->snmp_service->get_printer_details($ip_address, $community);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Failed to get printer info',
                'error' => $e->getMessage()
            ]);
        }
    }

}