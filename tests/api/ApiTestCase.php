<?php
/**
 * ApiTestCase – base class for all API unit tests.
 *
 * Provides helpers for capturing ApiResponse output and asserting on
 * the JSON envelope without requiring a running web server or database.
 */

use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    /**
     * Capture the JSON output emitted by a callable that calls ApiResponse::send()
     * (which normally calls exit). The callable is run in a way that intercepts
     * both the HTTP status code and the JSON body.
     *
     * Returns ['status' => int, 'body' => array].
     */
    protected function captureResponse(callable $fn): array
    {
        // Patch http_response_code to capture status without sending headers.
        $status = 200;

        ob_start();
        try {
            $fn();
        } catch (ApiTestExit $e) {
            $status = $e->statusCode;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $output = ob_get_clean();
        $body   = json_decode($output, true) ?? [];

        return ['status' => $status, 'body' => $body];
    }

    protected function assertSuccess(array $response, int $expectedStatus = 200): void
    {
        $this->assertSame($expectedStatus, $response['status'],
            "Expected HTTP {$expectedStatus}, got {$response['status']}. Body: " . json_encode($response['body']));
        $this->assertTrue($response['body']['success'] ?? false,
            'Expected success=true. Body: ' . json_encode($response['body']));
    }

    protected function assertError(array $response, int $expectedStatus): void
    {
        $this->assertSame($expectedStatus, $response['status'],
            "Expected HTTP {$expectedStatus}, got {$response['status']}. Body: " . json_encode($response['body']));
        $this->assertFalse($response['body']['success'] ?? true,
            'Expected success=false. Body: ' . json_encode($response['body']));
    }

    protected function assertValidationError(array $response): void
    {
        $this->assertError($response, 422);
        $this->assertSame('validation_error', $response['body']['error'] ?? '');
        $this->assertNotEmpty($response['body']['errors'] ?? []);
    }
}

/**
 * Thrown by ApiResponse::send() shim during tests to stop execution
 * and propagate the HTTP status code.
 */
class ApiTestExit extends \RuntimeException
{
    public int $statusCode;

    public function __construct(int $statusCode)
    {
        $this->statusCode = $statusCode;
        parent::__construct("API response sent with HTTP {$statusCode}");
    }
}
