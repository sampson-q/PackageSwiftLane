<?php
/**
 * ApiResponse – standardised JSON response helpers.
 *
 * Every response envelope looks like:
 *   {
 *     "success": bool,
 *     "data":    mixed|null,
 *     "meta":    object|null,   // pagination, etc.
 *     "error":   string|null,   // machine-readable error code
 *     "message": string|null,
 *     "errors":  object|null    // field-level validation errors
 *   }
 */
class ApiResponse
{
    // ── Core sender ───────────────────────────────────────────────────────────

    /**
     * Send a JSON response and halt.
     *
     * @param array $body
     * @param int   $status HTTP status code
     */
    public static function send(array $body, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ── Success helpers ───────────────────────────────────────────────────────

    /**
     * 200 OK with data payload.
     *
     * @param mixed $data
     * @param array $meta   Optional metadata (pagination, etc.)
     * @param int   $status
     */
    public static function success($data = null, array $meta = [], int $status = 200): void
    {
        $body = ['success' => true, 'data' => $data];
        if (!empty($meta)) {
            $body['meta'] = $meta;
        }
        self::send($body, $status);
    }

    /**
     * 201 Created.
     *
     * @param mixed  $data
     * @param string $message
     */
    public static function created($data = null, string $message = 'Resource created'): void
    {
        self::send(['success' => true, 'data' => $data, 'message' => $message], 201);
    }

    /**
     * 200 with paginated list.
     *
     * @param array $items      Current page items
     * @param int   $total      Total records
     * @param int   $page       Current page (1-based)
     * @param int   $perPage    Items per page
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): void
    {
        $lastPage = max(1, (int)ceil($total / $perPage));
        self::send([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'from'         => $total === 0 ? null : (($page - 1) * $perPage) + 1,
                'to'           => $total === 0 ? null : min($page * $perPage, $total),
            ],
        ], 200);
    }

    // ── Error helpers ─────────────────────────────────────────────────────────

    /**
     * Generic error.
     *
     * @param string $error   Machine-readable code (e.g. "not_found")
     * @param string $message Human-readable explanation
     * @param int    $status
     * @param array  $errors  Field-level errors
     */
    public static function error(
        string $error,
        string $message,
        int $status = 400,
        array $errors = []
    ): void {
        $body = ['success' => false, 'error' => $error, 'message' => $message];
        if (!empty($errors)) {
            $body['errors'] = $errors;
        }
        self::send($body, $status);
    }

    /** 400 Bad Request (generic). */
    public static function badRequest(string $message = 'Bad request', array $errors = []): void
    {
        self::error('bad_request', $message, 400, $errors);
    }

    /** 400 Validation error with field-level detail. */
    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::error('validation_error', $message, 422, $errors);
    }

    /** 401 Unauthorized. */
    public static function unauthorized(string $message = 'Authentication required'): void
    {
        self::error('unauthorized', $message, 401);
    }

    /** 403 Forbidden. */
    public static function forbidden(string $message = 'Insufficient permissions'): void
    {
        self::error('forbidden', $message, 403);
    }

    /** 404 Not Found. */
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error('not_found', $message, 404);
    }

    /** 405 Method Not Allowed. */
    public static function methodNotAllowed(string $method = ''): void
    {
        $msg = $method ? "Method {$method} not allowed on this endpoint" : 'Method not allowed';
        self::error('method_not_allowed', $msg, 405);
    }

    /** 409 Conflict. */
    public static function conflict(string $message = 'Resource already exists'): void
    {
        self::error('conflict', $message, 409);
    }

    /** 500 Internal Server Error. */
    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error('server_error', $message, 500);
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    /**
     * Parse the raw JSON request body into an array.
     * Returns empty array on invalid or empty body.
     */
    public static function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Merge JSON body with $_POST, giving priority to JSON.
     */
    public static function getRequestData(): array
    {
        $json = self::parseJsonBody();
        return array_merge($_POST, $json);
    }
}
