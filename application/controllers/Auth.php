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

class Auth extends CI_Controller
{
    private $jwt_key = '7f8a9b2c4d6e1f3a5b7c9d0e2f4a6b8c1d3e5f7a9b2c4d6e8f0a1b3c5d7e9f2a4b6c8d0e1f3a5b7c9d0e2f4a6b8c';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('User_model');
        $this->load->helper('url');
        header('Content-Type: application/json');
    }

    private function generate_tokens($user)
    {
        $access_token = JWT::encode([
            'userId' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + (15 * 60) // 15 minutes
        ], $this->jwt_key, 'HS256');

        $refresh_token = bin2hex(random_bytes(64));
        
        return [
            'accessToken' => $access_token,
            'refreshToken' => $refresh_token
        ];
    }

    public function login()
    {
        if ($this->input->method() !== 'post') {
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            return;
        }

        $input = json_decode($this->input->raw_input_stream, true);
        $username = $input['username'] ?? $this->input->post('username');
        $password = $input['password'] ?? $this->input->post('password');

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['message' => 'Username and password are required']);
            return;
        }

        try {
            $user = $this->User_model->get_by_username($username);

            if (!$user) {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid credentials']);
                return;
            }

            if (!password_verify($password, $user['password'])) {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid credentials']);
                return;
            }

            $tokens = $this->generate_tokens($user);
            
            $refresh_token_expires = date('Y-m-d H:i:s', strtotime('+7 days'));
            $this->User_model->update_refresh_token(
                $user['id'], 
                $tokens['refreshToken'], 
                $refresh_token_expires
            );

            echo json_encode([
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ],
                'accessToken' => $tokens['accessToken'],
                'refreshToken' => $tokens['refreshToken']
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Login failed', 'error' => $e->getMessage()]);
        }
    }

    public function refresh_token()
    {
        if ($this->input->method() !== 'post') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode($this->input->raw_input_stream, true);
        $refresh_token = $input['refreshToken'] ?? $this->input->post('refreshToken');

        if (!$refresh_token) {
            http_response_code(401);
            echo json_encode(['error' => 'Refresh token required']);
            return;
        }

        try {
            $user = $this->User_model->get_by_refresh_token($refresh_token);

            if (!$user) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid or expired refresh token']);
                return;
            }

            $tokens = $this->generate_tokens($user);
            
            $refresh_token_expires = date('Y-m-d H:i:s', strtotime('+7 days'));
            $this->User_model->update_refresh_token(
                $user['id'], 
                $tokens['refreshToken'], 
                $refresh_token_expires
            );

            echo json_encode([
                'accessToken' => $tokens['accessToken'],
                'refreshToken' => $tokens['refreshToken']
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function logout()
    {
        if ($this->input->method() !== 'post') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode($this->input->raw_input_stream, true);
        $refresh_token = $input['refreshToken'] ?? $this->input->post('refreshToken');

        try {
            if ($refresh_token) {
                $this->User_model->clear_refresh_token($refresh_token);
            }

            echo json_encode(['message' => 'Logged out successfully']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function register()
    {
        if ($this->input->method() !== 'post') {
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            return;
        }

        $input = json_decode($this->input->raw_input_stream, true);
        $username = $input['username'] ?? $this->input->post('username');
        $email = $input['email'] ?? $this->input->post('email');
        $password = $input['password'] ?? $this->input->post('password');
        $role = $input['role'] ?? $this->input->post('role') ?? 'viewer';

        if (!$username || !$email || !$password) {
            http_response_code(400);
            echo json_encode(['message' => 'Username, email, and password are required']);
            return;
        }

        try {
            if ($this->User_model->check_user_exists($username, $email)) {
                http_response_code(400);
                echo json_encode(['message' => 'Username or email already exists']);
                return;
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $user_data = [
                'username' => $username,
                'email' => $email,
                'password' => $hashed_password,
                'role' => $role
            ];

            $this->User_model->create_user($user_data);
            $user_id = $this->User_model->get_insert_id();

            echo json_encode([
                'message' => 'User created successfully',
                'user' => [
                    'id' => $user_id,
                    'username' => $username,
                    'email' => $email,
                    'role' => $role
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['message' => 'Registration failed', 'error' => $e->getMessage()]);
        }
    }

    public function verify()
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
            echo json_encode(['error' => 'Token required']);
            return;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->jwt_key, 'HS256'));
            echo json_encode([
                'success' => true,
                'user' => $decoded
            ]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
        }
    }
}