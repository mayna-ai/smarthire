<?php

class Request
{
    public array $body;
    public array $query;
    public array $params = []; // rempli par le Router (ex: {id})
    public ?array $user = null; // rempli par AuthMiddleware si authentifié

    public function __construct()
    {
        $this->query = $_GET ?? [];

        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        $this->body = is_array($decoded) ? $decoded : [];
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

public function bearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }

    if (preg_match('/Bearer\s+(\S+)/', $header, $m)) {
        return $m[1];
    }
    return null;
}
    /** Pagination standard : ?page=1&per_page=20 (bornée pour éviter les abus) */
    public function pagination(int $defaultPerPage = 20, int $maxPerPage = 100): array
    {
        $page = max(1, (int) $this->input('page', 1));
        $perPage = min($maxPerPage, max(1, (int) $this->input('per_page', $defaultPerPage)));
        return ['page' => $page, 'perPage' => $perPage, 'offset' => ($page - 1) * $perPage];
    }
}
