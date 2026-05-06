<?php

function secureSessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', '1');
        }

        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookieParams['path'] ?? '/',
            'domain' => $cookieParams['domain'] ?? '',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function getCsrfToken(): string {
    secureSessionStart();
    if (!isset($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function validateCsrfToken(string $token): bool {
    secureSessionStart();
    return isset($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
}

function requireAuth(array $roles = []): void {
    secureSessionStart();
    if (!isset($_SESSION['role'])) {
        http_response_code(401);
        jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    if (!empty($roles) && !in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
    }
}

function filterStringInput(string $key): string {
    $value = filter_input(INPUT_POST, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    return trim((string) $value);
}

function filterJsonString(array $data, string $key): string {
    if (!isset($data[$key])) {
        return '';
    }
    return trim(htmlspecialchars((string) $data[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

function jsonResponse(mixed $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function destroySession(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
