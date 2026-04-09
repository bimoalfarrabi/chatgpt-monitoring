<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends BaseController
{
    protected function requestData(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json) && $json !== []) {
            return $json;
        }

        $post = $this->request->getPost();
        if (is_array($post) && $post !== []) {
            return $post;
        }

        $raw = $this->request->getRawInput();
        return is_array($raw) ? $raw : [];
    }

    protected function validationErrorResponse(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status' => 'error',
            'errors' => $errors,
        ]);
    }

    protected function ok(array $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    protected function failMessage(string $message, int $status = 400): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'status'  => 'error',
            'message' => $message,
        ]);
    }
}
