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

class Printer extends CI_Controller
{
    private $jwt_key = '7f8a9b2c4d6e1f3a5b7c9d0e2f4a6b8c1d3e5f7a9b2c4d6e8f0a1b3c5d7e9f2a4b6c8d0e1f3a5b7c9d0e2f4a6b8c';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Printer_model');
        $this->load->library('Snmp_service');
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
            echo json_encode(['message' => 'Token required']);
            exit;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->jwt_key, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid token']);
            exit;
        }
    }

    public function index()
    {
        $method = $this->input->method();

        switch ($method) {
            case 'get':
                $this->get_all_printers();
                break;
            case 'post':
                $this->create_printer();
                break;
            default:
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
        }
    }

    private function get_all_printers()
    {
        try {
            $printers = $this->Printer_model->get_all_active();
            echo json_encode($printers);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Error fetching printers',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function show($id)
    {
        $method = $this->input->method();

        switch ($method) {
            case 'get':
                $this->get_printer($id);
                break;
            case 'put':
                $this->update_printer($id);
                break;
            case 'delete':
                $this->delete_printer($id);
                break;
            default:
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
        }
    }

    private function get_printer($id)
    {
        // $this->verify_token();

        try {
            $printer = $this->Printer_model->get_with_metrics($id);

            if (!$printer) {
                http_response_code(404);
                echo json_encode(['message' => 'Printer not found']);
                return;
            }

            echo json_encode($printer);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Error fetching printer',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function details($id)
    {
        // $this->verify_token();

        if ($this->input->method() !== 'get') {
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            return;
        }

        try {
            // Get printer from DB
            $printer = $this->Printer_model->get_by_id($id);

            if (!$printer) {
                http_response_code(404);
                echo json_encode(['message' => 'Printer not found']);
                return;
            }

            // Disable simulation for real data
            $this->snmp_service->set_simulation(false);

            // Fetch details via SNMP
            $details = $this->snmp_service->get_printer_details($printer['ipAddress'], $printer['snmpCommunity']);

            if ($details['success']) {
                echo json_encode($details['data']);
            } else {
                http_response_code(502); // Bad Gateway as upstream failed
                echo json_encode(['message' => $details['message'], 'error' => $details['error']]);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Error fetching printer details',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function create_printer()
    {
        // $this->verify_token();

        try {
            $input = json_decode($this->input->raw_input_stream, true);

            if (!$input) {
                $input = $this->input->post();
            }

            $success = $this->Printer_model->create($input);

            if ($success) {
                $printer_id = $this->Printer_model->get_insert_id();
                $printer = $this->Printer_model->get_by_id($printer_id);

                http_response_code(201);
                echo json_encode($printer);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Error creating printer']);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'message' => 'Error creating printer',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function update_printer($id)
    {
        // $this->verify_token();

        try {
            $input = json_decode($this->input->raw_input_stream, true);

            $updated = $this->Printer_model->update($id, $input);

            if (!$updated) {
                http_response_code(404);
                echo json_encode(['message' => 'Printer not found']);
                return;
            }

            $printer = $this->Printer_model->get_by_id($id);
            echo json_encode($printer);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'message' => 'Error updating printer',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function delete_printer($id)
    {
        // $this->verify_token();

        try {
            $deleted = $this->Printer_model->soft_delete($id);

            if (!$deleted) {
                http_response_code(404);
                echo json_encode(['message' => 'Printer not found']);
                return;
            }

            echo json_encode(['message' => 'Printer deleted successfully']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Error deleting printer',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function test_connection()
    {
        if ($this->input->method() !== 'post') {
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            return;
        }

        // $this->verify_token();

        try {
            $input = json_decode($this->input->raw_input_stream, true);

            $ip_address = $input['ipAddress'] ?? null;
            $snmp_profile = $input['snmpProfile'] ?? [];

            if (!$ip_address) {
                http_response_code(400);
                echo json_encode(['message' => 'IP address is required']);
                return;
            }

            $result = $this->snmp_service->test_connection($ip_address, $snmp_profile);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Connection test failed',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function delete_by_id($id)
    {
        if ($this->input->method() !== 'delete' && $this->input->method() !== 'post') {
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            return;
        }

        // $this->verify_token();

        try {
            $deleted = $this->Printer_model->soft_delete($id);

            if (!$deleted) {
                http_response_code(404);
                echo json_encode(['message' => 'Printer not found']);
                return;
            }

            echo json_encode(['message' => 'Printer deleted successfully']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Error deleting printer',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function test_snmp_oid()
    {
        if ($this->input->method() !== 'post') {
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            return;
        }

        try {
            $input = json_decode($this->input->raw_input_stream, true);

            $ip_address = $input['ip'] ?? null;
            $oid = $input['oid'] ?? null;
            $community = $input['community'] ?? 'public';

            if (!$ip_address || !$oid) {
                http_response_code(400);
                echo json_encode(['message' => 'IP address and OID are required']);
                return;
            }

            $result = $this->snmp_service->test_custom_oid($ip_address, $oid, $community);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'message' => 'SNMP test failed',
                'error' => $e->getMessage()
            ]);
        }
    }
}