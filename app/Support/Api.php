<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class Api
{
    protected const API_PREFIX = '/api/v1';

    public static function token(): ?string
    {
        return Session::get('api_token');
    }

    public static function setToken(string $token): void
    {
        Session::put('api_token', $token);
    }

    public static function forgetToken(): void
    {
        Session::forget('api_token');
    }

    public static function get(string $path, array $query = []): array
    {
        return self::dispatch('GET', $path, $query);
    }

    public static function post(string $path, array $body = []): array
    {
        return self::dispatch('POST', $path, $body);
    }

    public static function put(string $path, array $body = []): array
    {
        return self::dispatch('PUT', $path, $body);
    }

    public static function patch(string $path, array $body = []): array
    {
        return self::dispatch('PATCH', $path, $body);
    }

    public static function delete(string $path, array $body = []): array
    {
        return self::dispatch('DELETE', $path, $body);
    }

    protected static function dispatch(string $method, string $path, array $payload): array
    {
        $uri = self::API_PREFIX . '/' . ltrim($path, '/');

        $request = Request::create($uri, $method, $method === 'GET' ? $payload : []);

        if ($method !== 'GET') {
            $request->headers->set('Content-Type', 'application/json');
            $request->setContent(json_encode($payload));
        }

        $request->headers->set('Accept', 'application/json');

        if ($token = self::token()) {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = app()->handle($request);
        $status   = $response->getStatusCode();
        $json     = json_decode($response->getContent(), true) ?? [];

        return [
            'ok'      => $status >= 200 && $status < 300,
            'status'  => $status,
            'data'    => $json['data']    ?? null,
            'errors'  => $json['errors']  ?? null,
            'message' => $json['message'] ?? null,
            'raw'     => $json,
        ];
    }

    public static function upload(string $path, array $body = [], array $files = []): array
    {
        $uri = self::API_PREFIX . '/' . ltrim($path, '/');

        $request = Request::create($uri, 'POST', $body);

        $request->headers->set('Accept', 'application/json');

        if ($token = self::token()) {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        $request->files->replace($files);

        $response = app()->handle($request);
        $status   = $response->getStatusCode();
        $json     = json_decode($response->getContent(), true) ?? [];

        return [
            'ok'      => $status >= 200 && $status < 300,
            'status'  => $status,
            'data'    => $json['data']    ?? null,
            'errors'  => $json['errors']  ?? null,
            'message' => $json['message'] ?? null,
            'raw'     => $json,
        ];
    }
}