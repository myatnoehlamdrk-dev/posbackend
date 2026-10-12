<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleStockDeductionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function makeProduct(int $stock = 10, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Cappuccino',
            'stock' => $stock,
        ], $overrides));
    }

    private function salePayload(Product $product, int $quantity, array $overrides = []): array
    {
        return array_merge([
            'userName' => $this->user->name,
            'voucherNo' => 'INV-TEST-1',
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
        ], $overrides);
    }

    public function test_sale_without_server_order_deducts_stock(): void
    {
        $product = $this->makeProduct(10);

        $response = $this->postJson('/api/sales', $this->salePayload($product, 3));

        $response->assertStatus(201);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(1, Sale::count());
    }

    public function test_sale_with_local_card_order_id_deducts_stock(): void
    {
        $product = $this->makeProduct(10);

        $response = $this->postJson(
            '/api/sales',
            $this->salePayload($product, 2, ['orderId' => 'local:abc-123'])
        );

        $response->assertStatus(201);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_sale_converting_draft_order_deducts_stock_exactly_once(): void
    {
        $product = $this->makeProduct(10);

        $orderResponse = $this->postJson('/api/orders', $this->salePayload($product, 3, [
            'orderId' => 'ORD-1',
            'status' => 'draft',
        ]));
        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('id');
        $this->assertSame(7, $product->fresh()->stock);

        $saleResponse = $this->postJson(
            '/api/sales',
            $this->salePayload($product, 3, ['orderId' => $orderId])
        );

        $saleResponse->assertStatus(201);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
        $this->assertSame(1, Sale::count());
    }

    public function test_sale_converting_cancelled_order_deducts_stock(): void
    {
        $product = $this->makeProduct(10);

        $orderResponse = $this->postJson('/api/orders', $this->salePayload($product, 3, [
            'orderId' => 'ORD-2',
            'status' => 'draft',
        ]));
        $orderId = $orderResponse->json('id');

        $this->patchJson("/api/orders/{$orderId}", ['status' => 'cancelled'])
            ->assertOk();
        $this->assertSame(10, $product->fresh()->stock);

        $saleResponse = $this->postJson(
            '/api/sales',
            $this->salePayload($product, 3, ['orderId' => $orderId])
        );

        $saleResponse->assertStatus(201);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
    }

    public function test_sale_without_order_insufficient_stock_returns_422_and_creates_no_sale(): void
    {
        $product = $this->makeProduct(2);

        $response = $this->postJson('/api/sales', $this->salePayload($product, 5));

        $response->assertStatus(422)
            ->assertJsonPath('product', 'Cappuccino')
            ->assertJsonPath('requested', 5)
            ->assertJsonPath('available', 2);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }

    public function test_multi_item_sale_rolls_back_all_deductions_when_one_item_fails(): void
    {
        $ok = $this->makeProduct(10, ['name' => 'Latte']);
        $short = $this->makeProduct(1, ['name' => 'Mocha']);

        $response = $this->postJson('/api/sales', [
            'userName' => $this->user->name,
            'voucherNo' => 'INV-TEST-2',
            'grandTotal' => 30,
            'items' => [
                ['productId' => $ok->id, 'productName' => 'Latte', 'quantity' => 2, 'unitPrice' => 10, 'subtotal' => 20],
                ['productId' => $short->id, 'productName' => 'Mocha', 'quantity' => 3, 'unitPrice' => 10, 'subtotal' => 30],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(10, $ok->fresh()->stock);
        $this->assertSame(1, $short->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }

    public function test_order_insufficient_stock_returns_422_and_creates_no_order(): void
    {
        $product = $this->makeProduct(2);

        $response = $this->postJson('/api/orders', $this->salePayload($product, 5, [
            'orderId' => 'ORD-3',
            'status' => 'draft',
        ]));

        $response->assertStatus(422)->assertJsonPath('available', 2);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_variant_size_insufficient_stock_returns_422(): void
    {
        $product = $this->makeProduct(0, [
            'product_type' => 'variant',
            'variants' => [
                ['size' => 'M', 'color' => 'Red', 'quantity' => 3],
                ['size' => 'L', 'color' => 'Blue', 'quantity' => 2],
            ],
        ]);

        $response = $this->postJson('/api/sales', $this->salePayload($product, 4, [
            'items' => [[
                'productId' => $product->id,
                'productName' => $product->name,
                'quantity' => 4,
                'unitPrice' => 10,
                'subtotal' => 40,
                'size' => 'M',
            ]],
        ]));

        $response->assertStatus(422)->assertJsonPath('available', 3);
        $variants = $product->fresh()->variants;
        $this->assertSame(3, $variants[0]['quantity']);
    }

    public function test_variant_aggregate_insufficient_stock_returns_422(): void
    {
        $product = $this->makeProduct(0, [
            'product_type' => 'variant',
            'variants' => [
                ['size' => 'M', 'color' => 'Red', 'quantity' => 3],
                ['size' => 'L', 'color' => 'Blue', 'quantity' => 2],
            ],
        ]);

        $response = $this->postJson('/api/sales', $this->salePayload($product, 6));

        $response->assertStatus(422)->assertJsonPath('available', 5);
    }

    public function test_bundle_insufficient_component_returns_422_and_deducts_nothing(): void
    {
        $beans = $this->makeProduct(4, ['name' => 'Beans']);
        $milk = $this->makeProduct(2, ['name' => 'Milk']);
        $bundle = $this->makeProduct(0, [
            'name' => 'Combo',
            'product_type' => 'bundle',
            'bundle_components' => [
                ['product_id' => $beans->id, 'quantity' => 1],
                ['product_id' => $milk->id, 'quantity' => 2],
            ],
        ]);

        $response = $this->postJson('/api/sales', $this->salePayload($bundle, 2));

        $response->assertStatus(422);
        $this->assertSame(4, $beans->fresh()->stock);
        $this->assertSame(2, $milk->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }

    public function test_bundle_sale_deducts_each_component(): void
    {
        $beans = $this->makeProduct(10, ['name' => 'Beans']);
        $milk = $this->makeProduct(10, ['name' => 'Milk']);
        $bundle = $this->makeProduct(0, [
            'name' => 'Combo',
            'product_type' => 'bundle',
            'bundle_components' => [
                ['product_id' => $beans->id, 'quantity' => 1],
                ['product_id' => $milk->id, 'quantity' => 2],
            ],
        ]);

        $response = $this->postJson('/api/sales', $this->salePayload($bundle, 3));

        $response->assertStatus(201);
        $this->assertSame(7, $beans->fresh()->stock);
        $this->assertSame(4, $milk->fresh()->stock);
    }

    private function itemsPayload(array $items): array
    {
        return array_map(static function (array $item) {
            $row = [
                'productId' => $item['productId'],
                'productName' => $item['productName'] ?? 'Item',
                'quantity' => $item['quantity'],
                'unitPrice' => $item['unitPrice'] ?? 10,
                'subtotal' => ($item['unitPrice'] ?? 10) * $item['quantity'],
            ];
            if (array_key_exists('size', $item)) {
                $row['size'] = $item['size'];
            }
            if (array_key_exists('color', $item)) {
                $row['color'] = $item['color'];
            }

            return $row;
        }, $items);
    }

    private function draftAndConvert(int $draftQty, int $saleQty, Product $product): void
    {
        $order = $this->postJson('/api/orders', [
            'userName' => $this->user->name,
            'voucherNo' => 'ORD-DRAFT',
            'orderId' => 'ORD-DRAFT-1',
            'grandTotal' => 10 * $draftQty,
            'status' => 'draft',
            'items' => $this->itemsPayload([
                ['productId' => $product->id, 'productName' => $product->name, 'quantity' => $draftQty],
            ]),
        ]);
        $order->assertStatus(201);

        $this->postJson('/api/sales', [
            'userName' => $this->user->name,
            'voucherNo' => 'INV-CONVERT',
            'orderId' => $order->json('id'),
            'grandTotal' => 10 * $saleQty,
            'items' => $this->itemsPayload([
                ['productId' => $product->id, 'productName' => $product->name, 'quantity' => $saleQty],
            ]),
        ])->assertStatus(201);
    }

    public function test_converting_draft_with_more_items_deducts_the_added_quantity(): void
    {
        $product = $this->makeProduct(10);

        $this->draftAndConvert(3, 5, $product);

        // Draft pinned 3; the cart grew to 5, so the extra 2 are deducted
        // on convert. The sale's pin must cover all 5.
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(1, Sale::count());
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_converting_draft_with_fewer_items_returns_the_removed_quantity(): void
    {
        $product = $this->makeProduct(10);

        $this->draftAndConvert(5, 2, $product);

        // Draft pinned 5; the cart shrank to 2, so 3 come back on convert
        // instead of staying stuck down forever.
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(1, Sale::count());
    }

    public function test_converting_draft_with_swapped_items_restores_and_deducts_each(): void
    {
        $a = $this->makeProduct(10, ['name' => 'Latte']);
        $b = $this->makeProduct(10, ['name' => 'Mocha']);

        $order = $this->postJson('/api/orders', [
            'userName' => $this->user->name,
            'voucherNo' => 'ORD-DRAFT',
            'orderId' => 'ORD-DRAFT-2',
            'grandTotal' => 30,
            'status' => 'draft',
            'items' => $this->itemsPayload([
                ['productId' => $a->id, 'productName' => 'Latte', 'quantity' => 3],
            ]),
        ]);
        $order->assertStatus(201);

        $this->postJson('/api/sales', [
            'userName' => $this->user->name,
            'voucherNo' => 'INV-CONVERT',
            'orderId' => $order->json('id'),
            'grandTotal' => 20,
            'items' => $this->itemsPayload([
                ['productId' => $a->id, 'productName' => 'Latte', 'quantity' => 2],
                ['productId' => $b->id, 'productName' => 'Mocha', 'quantity' => 1],
            ]),
        ])->assertStatus(201);

        $this->assertSame(8, $a->fresh()->stock);
        $this->assertSame(9, $b->fresh()->stock);
        $this->assertSame(1, Sale::count());
    }

    public function test_converting_diverged_draft_rejects_when_added_stock_is_unavailable(): void
    {
        $product = $this->makeProduct(5);

        $order = $this->postJson('/api/orders', [
            'userName' => $this->user->name,
            'voucherNo' => 'ORD-DRAFT',
            'orderId' => 'ORD-DRAFT-3',
            'grandTotal' => 30,
            'status' => 'draft',
            'items' => $this->itemsPayload([
                ['productId' => $product->id, 'productName' => $product->name, 'quantity' => 3],
            ]),
        ]);
        $order->assertStatus(201);
        $orderId = $order->json('id');
        $this->assertSame(2, $product->fresh()->stock);

        // Cart grew to 6 but only 2 remain: the whole convert rolls back,
        // the draft survives for a retry, and nothing is oversold.
        $this->postJson('/api/sales', [
            'userName' => $this->user->name,
            'voucherNo' => 'INV-CONVERT',
            'orderId' => $orderId,
            'grandTotal' => 60,
            'items' => $this->itemsPayload([
                ['productId' => $product->id, 'productName' => $product->name, 'quantity' => 6],
            ]),
        ])->assertStatus(422);

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame(0, Sale::count());
        $this->assertDatabaseHas('orders', ['id' => $orderId]);
    }

    public function test_converting_draft_moves_stock_between_variants(): void
    {
        $product = $this->makeProduct(0, [
            'product_type' => 'variant',
            'variants' => [
                ['size' => 'M', 'color' => 'Red', 'quantity' => 3],
                ['size' => 'L', 'color' => 'Blue', 'quantity' => 2],
            ],
        ]);

        $order = $this->postJson('/api/orders', [
            'userName' => $this->user->name,
            'voucherNo' => 'ORD-DRAFT',
            'orderId' => 'ORD-DRAFT-4',
            'grandTotal' => 20,
            'status' => 'draft',
            'items' => $this->itemsPayload([
                ['productId' => $product->id, 'productName' => $product->name, 'quantity' => 2, 'size' => 'M'],
            ]),
        ]);
        $order->assertStatus(201);
        $this->assertSame(1, $product->fresh()->variants[0]['quantity']);

        // The cashier swapped the size: M comes back, L is deducted.
        $this->postJson('/api/sales', [
            'userName' => $this->user->name,
            'voucherNo' => 'INV-CONVERT',
            'orderId' => $order->json('id'),
            'grandTotal' => 20,
            'items' => $this->itemsPayload([
                ['productId' => $product->id, 'productName' => $product->name, 'quantity' => 2, 'size' => 'L'],
            ]),
        ])->assertStatus(201);

        $variants = $product->fresh()->variants;
        $this->assertSame(3, $variants[0]['quantity']);
        $this->assertSame(0, $variants[1]['quantity']);
    }
}
