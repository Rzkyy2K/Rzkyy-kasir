<?php

namespace App\Http\Controllers\Api;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BaseApiController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected function ok(mixed $data = null, string $message = 'Berhasil'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function created(mixed $data = null, string $message = 'Data berhasil disimpan'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], 201);
    }

    protected function fail(string $message, int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $code);
    }

    protected function storeError(QueryException $e): JsonResponse
    {
        report($e);

        return $this->fail('Data gagal disimpan. Silakan coba lagi.', 500);
    }
}
