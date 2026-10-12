<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function makeProduct(int $stock = 10): Product
    {
        return Product::create(['name' => 'Cappuccino', 'stock' => $stock]);
    }

    private function salePayload(Product $product, int $quantity): array
    {
        return [
            'userName' => $this->user->name,
            'voucherNo' => 'INV-IDEM-1',
            'grandTotal' => 10 * $quantity,
            'discount' => 0,
            'payMethod' => 'cash',
            'items' => [[
                'productId' => $product->id,
                'productName' => $product->name,
                'quantity' => $quantity,
                'unitPrice' => 10,
                'subtotal' => 10 * $quantity,
            ]],
        ];
    }

    private function idempotencyCacheKey(string $method, string $path, string $key): string
    {
        return 'idempotency:'.sha1($method.'|'.$path.'|'.$key);
    }

    public function test_repeated_key_replays_response_and_creates_one_sale(): void
    {
        $product = $this->makeProduct(10);
        $headers = ['Idempotency-Key' => 'key-1'];

        $first = $this->postJson('/api/sales', $this->salePayload($product, 3), $headers);
        $first->assertStatus(201);

        $second = $this->postJson('/api/sales', $this->salePayload($product, 3), $headers);
        $second->assertStatus(201);
        $second->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($first->json(), $second->json());

        $this->assertSame(1, Sale::count());
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_different_keys_create_two_sales(): void
    {
        $product = $this->makeProduct(10);

        $this->postJson('/api/sales', $this->salePayload($product, 2), ['Idempotency-Key' => 'key-a'])
            ->assertStatus(201);
        $this->postJson('/api/sales', $this->salePayload($product, 2), ['Idempotency-Key' => 'key-b'])
            ->assertStatus(201);

        $this->assertSame(2, Sale::count());
        $this->assertSame(6, $product->fresh()->stock);
    }

    public function test_request_without_key_always_executes(): void
    {
        $product = $this->makeProduct(10);

        $this->postJson('/api/sales', $this->salePayload($product, 2))->assertStatus(201);
        $this->postJson('/api/sales', $this->salePayload($product, 2))->assertStatus(201);

        $this->assertSame(2, Sale::count());
        $this->assertSame(6, $product->fresh()->stock);
    }

    public function test_failed_validation_does_not_block_retry_with_same_key(): void
    {
        $product = $this->makeProduct(10);
        $headers = ['Idempotency-Key' => 'key-retry'];

        $invalid = $this->salePayload($product, 2);
        unset($invalid['grandTotal']);
        $this->postJson('/api/sales', $invalid, $headers)->assertStatus(422);

        $retry = $this->postJson('/api/sales', $this->salePayload($product, 2), $headers);
        $retry->assertStatus(201);
        $retry->assertHeaderMissing('Idempotency-Replayed');

        $this->assertSame(1, Sale::count());
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_same_key_on_orders_and_sales_both_execute(): void
    {
        $product = $this->makeProduct(10);
        $headers = ['Idempotency-Key' => 'key-pair'];

        $order = $this->postJson('/api/orders', $this->salePayload($product, 3) + [
            'orderId' => 'ORD-1',
            'status' => 'draft',
        ], $headers);
        $order->assertStatus(201);
        $orderId = $order->json('id');

        $sale = $this->postJson('/api/sales', $this->salePayload($product, 3) + [
            'orderId' => $orderId,
        ], $headers);
        $sale->assertStatus(201);
        $sale->assertHeaderMissing('Idempotency-Replayed');

        $this->assertSame(1, Sale::count());
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_in_flight_duplicate_returns_503_and_creates_nothing(): void
    {
        $product = $this->makeProduct(10);

        Cache::put($this->idempotencyCacheKey('POST', 'api/sales', 'key-flight'), 'pending', 120);

        $response = $this->postJson('/api/sales', $this->salePayload($product, 3), [
            'Idempotency-Key' => 'key-flight',
        ]);

        $response->assertStatus(503);
        $response->assertHeader('Retry-After', '2');
        $this->assertSame(0, Sale::count());
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_expired_pending_claim_is_reclaimed_by_a_later_retry(): void
    {
        $product = $this->makeProduct(10);

        // A process that died mid-request leaves its claim behind; the
        // claim's TTL is what lets a later retry through instead of
        // being locked out forever.
        Cache::put($this->idempotencyCacheKey('POST', 'api/sales', 'key-stale'), 'pending', 120);

        Carbon::setTestNow(now()->addSeconds(121));

        $response = $this->postJson('/api/sales', $this->salePayload($product, 3), [
            'Idempotency-Key' => 'key-stale',
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, Sale::count());
        $this->assertSame(7, $product->fresh()->stock);
    }
}
