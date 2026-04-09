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
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));

        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[8]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $user = $this->users->where('email', $data['email'])->first();
        if (! $user || ! password_verify((string) $data['password'], (string) $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Email atau password tidak valid.');
        }

        if ((int) $user['is_active'] !== 1) {
            return redirect()->back()->withInput()->with('error', 'Akun tidak aktif. Hubungi admin.');
        }

        session()->regenerate();
        session()->set([
            'user_id'    => (int) $user['id'],
            'user_name'  => (string) $user['name'],
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
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));

        $rules = [
            'name'                  => 'required|min_length[3]|max_length[120]',
            'email'                 => 'required|valid_email|is_unique[users.email]',
            'password'              => 'required|min_length[8]|max_length[255]',
            'password_confirmation' => 'required|matches[password]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $this->users->insert([
            'name'          => $data['name'],
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
