<?php
/**
 * ShipmentsHandlerTest – unit tests for ShipmentsHandler formatRow.
 *
 * The formatter is a pure PHP function with no DB dependency, so it can be
 * tested without @group integration.
 */

use PHPUnit\Framework\TestCase;

class ShipmentsHandlerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/api/v1/handlers/ShipmentsHandler.php';
    }

    // ── formatRow ─────────────────────────────────────────────────────────────

    public function testFormatRowReturnsEmptyArrayForNull(): void
    {
        $result = ShipmentsHandler::formatRow(null);
        $this->assertSame([], $result);
    }

    public function testFormatRowMapsBasicFields(): void
    {
        $row                      = new stdClass();
        $row->order_id            = 7;
        $row->order_prefix        = 'SHP-';
        $row->order_no            = '00007';
        $row->order_date          = '2025-01-15 10:00:00';
        $row->sender_id           = 3;
        $row->sender_name         = 'Alice Smith';
        $row->receiver_id         = 5;
        $row->receiver_name       = 'Bob Jones';
        $row->agency              = 2;
        $row->origin_off          = 1;
        $row->order_courier       = 1;
        $row->order_service_options = 2;
        $row->order_deli_time     = 1;
        $row->order_payment_method = 1;
        $row->driver_id           = 0;
        $row->status_courier      = 3;
        $row->status_label        = 'In Transit';
        $row->status_color        = '#00bcd4';
        $row->status_invoice      = 1;
        $row->is_pickup           = 0;
        $row->is_consolidate      = 0;
        $row->total_weight        = 2.5;
        $row->sub_total           = 50.00;
        $row->total_tax           = 5.00;
        $row->total_tax_insurance = 0;
        $row->total_tax_discount  = 0;
        $row->total_tax_custom_tariffis = 0;
        $row->total_reexp         = 0;
        $row->total_fixed_value   = 0;
        $row->total_declared_value= 100.00;
        $row->total_order         = 55.00;
        $row->due_date            = '2025-02-15 10:00:00';
        $row->order_incomplete    = 0;

        $result = ShipmentsHandler::formatRow($row);

        $this->assertSame(7, $result['id']);
        $this->assertSame('SHP-00007', $result['tracking_number']);
        $this->assertSame('Alice Smith', $result['sender_name']);
        $this->assertSame('In Transit', $result['status_label']);
        $this->assertFalse($result['is_pickup']);
        $this->assertSame(55.0, $result['total_order']);
    }

    public function testFormatRowComputesTrackingNumber(): void
    {
        $row               = new stdClass();
        $row->order_id     = 1;
        $row->order_prefix = 'X-';
        $row->order_no     = '00099';
        $row->order_date   = null;
        $row->sender_id    = 0;
        $row->sender_name  = null;
        $row->receiver_id  = 0;
        $row->receiver_name = null;
        foreach (['agency','origin_off','order_courier','order_service_options',
                  'order_deli_time','order_payment_method','driver_id',
                  'status_courier','status_invoice'] as $f) {
            $row->$f = 0;
        }
        $row->status_label        = null;
        $row->status_color        = null;
        $row->is_pickup           = 0;
        $row->is_consolidate      = 0;
        $row->total_weight        = 0;
        $row->sub_total           = 0;
        $row->total_tax           = 0;
        $row->total_tax_insurance = 0;
        $row->total_tax_discount  = 0;
        $row->total_tax_custom_tariffis = 0;
        $row->total_reexp         = 0;
        $row->total_fixed_value   = 0;
        $row->total_declared_value= 0;
        $row->total_order         = 0;
        $row->due_date            = null;
        $row->order_incomplete    = 0;

        $result = ShipmentsHandler::formatRow($row);
        $this->assertSame('X-00099', $result['tracking_number']);
    }

    public function testFormatRowCastsNumericFields(): void
    {
        $row                       = new stdClass();
        $row->order_id             = '42';
        $row->order_prefix         = 'A';
        $row->order_no             = '001';
        $row->order_date           = null;
        $row->sender_id            = '10';
        $row->sender_name          = null;
        $row->receiver_id          = '20';
        $row->receiver_name        = null;
        $row->agency               = '5';
        $row->origin_off           = '1';
        $row->order_courier        = '3';
        $row->order_service_options= '2';
        $row->order_deli_time      = '1';
        $row->order_payment_method = '1';
        $row->driver_id            = '7';
        $row->status_courier       = '4';
        $row->status_label         = 'Delivered';
        $row->status_color         = '#4caf50';
        $row->status_invoice       = '1';
        $row->is_pickup            = '1';
        $row->is_consolidate       = '0';
        $row->total_weight         = '3.14';
        $row->sub_total            = '100.50';
        $row->total_tax            = '10.05';
        $row->total_tax_insurance  = '0';
        $row->total_tax_discount   = '0';
        $row->total_tax_custom_tariffis = '0';
        $row->total_reexp          = '0';
        $row->total_fixed_value    = '0';
        $row->total_declared_value = '200.00';
        $row->total_order          = '110.55';
        $row->due_date             = null;
        $row->order_incomplete     = '0';

        $result = ShipmentsHandler::formatRow($row);

        $this->assertIsInt($result['id']);
        $this->assertIsInt($result['sender_id']);
        $this->assertIsFloat($result['total_weight']);
        $this->assertIsFloat($result['total_order']);
        $this->assertTrue($result['is_pickup']);
        $this->assertFalse($result['is_consolidate']);
    }
}
