<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class AuthController extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    public function loginForm(): string
    {
        return view('auth/login', [
            'pageTitle'      => 'Login',
            'allowRegister'  => ! $this->hasAnyUser(),
        ]);
    }

    public function login(): RedirectResponse
    {
        $data = $this->request->getPost();
        $data['identity'] = trim((string) ($data['identity'] ?? ''));
        $identifier = strtolower($data['identity']);

        $rules = [
            'identity' => 'required|min_length[3]|max_length[160]',
            'password' => 'required|min_length[8]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $user = $this->users
            ->groupStart()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->groupEnd()
            ->first();
        if (! $user || ! password_verify((string) $data['password'], (string) $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Username/email atau password tidak valid.');
        }

        if ((int) $user['is_active'] !== 1) {
            return redirect()->back()->withInput()->with('error', 'Akun tidak aktif. Hubungi admin.');
        }

        session()->regenerate();
        session()->set([
            'user_id'    => (int) $user['id'],
            'user_name'  => (string) $user['name'],
            'username'   => (string) ($user['username'] ?? ''),
            'user_email' => (string) $user['email'],
            'logged_in'  => true,
        ]);

        $this->users->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        return redirect()->to('/')->with('success', 'Login berhasil. Selamat datang, ' . $user['name'] . '.');
    }

    public function registerForm(): RedirectResponse|string
    {
        if ($this->hasAnyUser()) {
            return redirect()->to('/login')->with('error', 'Registrasi publik sudah ditutup.');
        }

        return view('auth/register', [
            'pageTitle' => 'Register',
        ]);
    }

    public function register(): RedirectResponse
    {
        if ($this->hasAnyUser()) {
            return redirect()->to('/login')->with('error', 'Registrasi publik sudah ditutup.');
        }

        $data = $this->request->getPost();
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['username'] = strtolower(trim((string) ($data['username'] ?? '')));
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));

        $rules = [
            'name'                  => 'required|min_length[3]|max_length[120]',
            'username'              => 'required|min_length[3]|max_length[50]|alpha_dash|is_unique[users.username]',
            'email'                 => 'required|valid_email|is_unique[users.email]',
            'password'              => 'required|min_length[8]|max_length[255]',
            'password_confirmation' => 'required|matches[password]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $this->users->insert([
            'name'          => $data['name'],
            'username'      => $data['username'],
            'email'         => $data['email'],
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'is_active'     => 1,
        ]);

        return redirect()->to('/login')->with('success', 'Akun berhasil dibuat. Silakan login.');
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to('/login')->with('success', 'Anda sudah logout.');
    }

    private function hasAnyUser(): bool
    {
        return $this->users->countAllResults() > 0;
    }
}
