<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if ((bool) session('logged_in')) {
            return null;
        }

        $path = trim($request->getUri()->getPath(), '/');
        $isApiRequest = str_starts_with($path, 'api');

        if ($isApiRequest) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Unauthorized. Please login first.',
                ]);
        }

        return redirect()->to('/login')->with('error', 'Silakan login terlebih dahulu.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
