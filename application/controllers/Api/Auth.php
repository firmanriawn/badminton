<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;
use \Firebase\JWT\JWT;

class Auth extends RestController  {

    public function __construct() {
        parent::__construct();
        $this->load->model('user_model');
        $this->load->library('form_validation');
        $this->load->library('rate_limiter');
        $this->load->helper('jwt');
        $this->load->helper('security');
        $this->load->helper('encryption');
        $this->config->load('jwt');
    }

    
    public function signin_post() {
        if (!$this->rate_limiter->limit('login', 5, 60)) {
            $this->response([
                'code' => 429,
                'message' => 'TOO_MANY_REQUESTS',
                'error' => [
                    'message' => 'Too many login attempts. Please try again after 1 minute.'
                ]
            ], 429); 
            return;
        }

        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        $this->form_validation->set_rules('password', 'Password', 'required');

        if ($this->form_validation->run() == FALSE) {
            $errors = $this->form_validation->error_array();
            $this->response([
                'code' => 400,
                'message' => 'BAD_REQUEST',
                'error' => $errors
            ], 400);
            return;
        }

        $email = $this->security->xss_clean($this->input->post('email'));
        $password = $this->security->xss_clean($this->input->post('password'));

        $user = $this->user_model->get_user_by_email($email);

        if ($user && password_verify($password, $user['password'])) {
            $access_token = generate_jwt(encryption($user['id']));

            $this->response([
                'code' => 200,
                'message' => 'SUCCESS',
                'data' => [
                    'access_token' => $access_token
                ]
            ], 200);
        } else {
            $this->response([
                'code' => 401,
                'message' => 'UNAUTHORIZED',
                'error' => [
                    'message' => 'Email atau password salah'
                ]
            ], 401);
        }
    }

    public function signup_post() {
        if (!$this->rate_limiter->limit('signup', 5, 60)) {
            $this->response([
                'code' => 429,
                'message' => 'TOO_MANY_REQUESTS',
                'error' => [
                    'message' => 'Too many signup attempts. Please try again after 1 minute.'
                ]
            ], 429); 
            return;
        }

        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[users.email]',
            array('is_unique' => 'Email already registered')
        );
        $this->form_validation->set_rules('password', 'Password', 'required|min_length[8]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/]',
            array('regex_match' => 'Password must contain at least one uppercase letter, one lowercase letter, one number and one special character.')
        );
        $this->form_validation->set_rules('full_name', 'Full Name', 'required|max_length[100]');

        if ($this->form_validation->run() == FALSE) {
            $errors = $this->form_validation->error_array();
            $this->response([
                'code' => 400,
                'message' => 'BAD_REQUEST',
                'error' => $errors
            ], 400);
            return;
        }

        $email = $this->security->xss_clean($this->input->post('email'));
        $password = $this->security->xss_clean($this->input->post('password'));
        $full_name = $this->security->xss_clean($this->input->post('full_name'));

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $user_data = [
            'email' => $email,
            'password' => $hashed_password,
            'full_name' => $full_name,
            'role' => 'user',
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $user_id = $this->user_model->create_user($user_data);

        if ($user_id) {
            $access_token = generate_jwt(encryption($user_id));

            $this->response([
                'code' => 201,
                'message' => 'CREATED',
                'data' => [
                    'access_token' => $access_token
                ]
            ], 201);
        } else {
            $this->response([
                'code' => 500,
                'message' => 'INTERNAL_SERVER_ERROR',
                'error' => [
                    'message' => 'Failed to create user'
                ]
            ], 500);
        }
    }

    public function logout_post() {
        $token = $this->input->get_request_header('Authorization');
        if ($token) {
            $token = str_replace('Bearer ', '', $token);
            if (revoke_token($token)) {
                $this->response([
                    'code' => 200,
                    'message' => 'OK',
                    'data' => ['message' => 'Logout berhasil']
                ], 200);
            } else {
                $this->response([
                    'code' => 400,
                    'message' => 'BAD_REQUEST',
                    'error' => ['message' => 'Gagal melakukan logout. Token mungkin sudah tidak valid.']
                ], 400);
            }
        } else {
            $this->response([
                'code' => 401,
                'message' => 'UNAUTHORIZED',
                'error' => ['message' => 'Token tidak ditemukan']
            ], 401);
        }
    }
}