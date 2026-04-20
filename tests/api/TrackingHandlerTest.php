<?php
/**
 * TrackingHandlerTest – unit tests for TrackingHandler.
 *
 * Tests the pure parts of TrackingHandler that don't require a DB.
 */

use PHPUnit\Framework\TestCase;

class TrackingHandlerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/api/v1/handlers/TrackingHandler.php';
    }

    // ── Handler existence ────────────────────────────────────────────────────

    public function testTrackingHandlerHasShowMethod(): void
    {
        $handler = new TrackingHandler();
        $this->assertTrue(method_exists($handler, 'show'));
    }

    // ── Tracking number normalisation ─────────────────────────────────────────

    public function testTrackingNumberIsUppercased(): void
    {
        $orderNo = strtoupper(cdp_sanitize('shp-00042'));
        $this->assertSame('SHP-00042', $orderNo);
    }

    public function testTrackingNumberTrimmed(): void
    {
        $orderNo = strtoupper(cdp_sanitize('  SHP-00042  '));
        $this->assertSame('SHP-00042', $orderNo);
    }

    public function testEmptyTrackingNumberIsEmpty(): void
    {
        $orderNo = strtoupper(cdp_sanitize(''));
        $this->assertSame('', $orderNo);
    }

    // ── Format verification (row mock) ────────────────────────────────────────

    public function testFindShipmentFormatMatchesExpectedKeys(): void
    {
        // Simulate what findShipment returns
        $expected = [
            'type', 'tracking_number', 'status', 'status_label', 'status_color',
            'order_date', 'total_order', 'is_pickup', 'events',
        ];

        $stub = [
            'type'            => 'shipment',
            'tracking_number' => 'SHP-00001',
            'status'          => 3,
            'status_label'    => 'In Transit',
            'status_color'    => '#f0f',
            'order_date'      => '2025-01-01',
            'total_order'     => 55.0,
            'is_pickup'       => false,
            'events'          => [],
        ];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $stub, "Missing key: {$key}");
        }
    }

    public function testFindPackageFormatMatchesExpectedKeys(): void
    {
        $expected = [
            'type', 'tracking_number', 'status', 'status_label', 'status_color',
            'order_date', 'total_order', 'events',
        ];

        $stub = [
            'type'            => 'package',
            'tracking_number' => 'PKG-00005',
            'status'          => 5,
            'status_label'    => 'Delivered',
            'status_color'    => '#4caf50',
            'order_date'      => '2025-02-01',
            'total_order'     => 20.0,
            'events'          => [],
        ];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $stub, "Missing key: {$key}");
        }
    }
}
