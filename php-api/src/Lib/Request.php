<?php

namespace Lib;

class Request {
    private array $params = [];
    private array $body = [];
    private array $query = [];
    private array $headers = [];
    private array $files = [];

    public function __construct() {
        $this->query = $_GET;
        $this->files = $_FILES;

        // Parse JSON body if content-type is application/json
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $this->body = json_decode($input, true) ?? [];
        } else {
            $this->body = $_POST;
        }

        // Parse headers
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $this->headers[strtolower($headerName)] = $value;
            }
        }
    }

    /**
     * Get request parameter by key
     */
    public function getParam(string $key, mixed $default = null): mixed {
        return $this->params[$key] ?? $default;
    }

    /**
     * Set route parameters
     */
    public function setParams(array $params): void {
        $this->params = $params;
    }

    /**
     * Get body parameter by key
     */
    public function getBody(string $key = null, mixed $default = null): mixed {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    /**
     * Get query parameter by key
     */
    public function getQuery(string $key = null, mixed $default = null): mixed {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    /**
     * Get header by key
     */
    public function getHeader(string $key): ?string {
        return $this->headers[strtolower($key)] ?? null;
    }

    /**
     * Get uploaded file by key
     */
    public function getFile(string $key): ?array {
        return $this->files[$key] ?? null;
    }

    /**
     * Check if request has file
     */
    public function hasFile(string $key): bool {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Get request method
     */
    public function getMethod(): string {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
}