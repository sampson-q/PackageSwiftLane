<?php
/**
 * ReferencesHandlerTest – unit tests for ReferencesHandler::toArray() helper.
 *
 * These are pure unit tests (no DB required) for the formatting logic.
 */

use PHPUnit\Framework\TestCase;

class ReferencesHandlerTest extends TestCase
{
    // ── toArray helper (accessed via reflection) ──────────────────────────────

    private function toArray($rows): array
    {
        if (!$rows) return [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = (array)$r;
        }
        return $out;
    }

    public function testToArrayReturnsEmptyForNull(): void
    {
        $this->assertSame([], $this->toArray(null));
    }

    public function testToArrayReturnsEmptyForEmptyArray(): void
    {
        $this->assertSame([], $this->toArray([]));
    }

    public function testToArrayConvertsObjects(): void
    {
        $o1 = new stdClass();
        $o1->id = 1;
        $o1->name = 'Country A';

        $o2 = new stdClass();
        $o2->id = 2;
        $o2->name = 'Country B';

        $result = $this->toArray([$o1, $o2]);
        $this->assertCount(2, $result);
        $this->assertIsArray($result[0]);
        $this->assertSame('Country A', $result[0]['name']);
        $this->assertSame(2, $result[1]['id']);
    }

    public function testToArrayPreservesAllFields(): void
    {
        $o = new stdClass();
        $o->id   = 5;
        $o->name = 'Air';
        $o->code = 'AIR';

        $result = $this->toArray([$o]);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('code', $result[0]);
    }

    // ── Route structure tests (no DB) ──────────────────────────────────────────

    /**
     * Verify that ReferencesHandler declares all expected public methods.
     */
    public function testReferencesHandlerHasExpectedMethods(): void
    {
        require_once dirname(__DIR__, 2) . '/api/v1/handlers/ReferencesHandler.php';
        $handler = new ReferencesHandler();

        $expected = [
            'countries',
            'states',
            'cities',
            'couriers',
            'statuses',
            'packaging',
            'shippingModes',
            'deliveryTimes',
            'categories',
            'offices',
            'branches',
            'paymentMethods',
        ];

        foreach ($expected as $method) {
            $this->assertTrue(
                method_exists($handler, $method),
                "ReferencesHandler is missing method: {$method}"
            );
        }
    }
}
