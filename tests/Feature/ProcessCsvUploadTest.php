<?php

namespace Tests\Feature;

use App\Jobs\ProcessCsvUpload;
use App\Models\Product;
use App\Models\Upload;
use App\Notifications\ImportFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessCsvUploadTest extends TestCase
{
    use RefreshDatabase;

    protected const CSV_HEADER = 'Handle,Title,Body HTML,Vendor,Product Type,Tags,Published,Variant SKU,Variant Price,Variant Compare At Price,Variant Requires Shipping,Variant Taxable,Variant Inventory Tracker,Variant Inventory Qty,Variant Inventory Policy,Variant Fulfillment Service,Variant Weight,Variant Weight Unit,Image Src,Image Position,Image Alt Text';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();
    }

    protected function fakeShopify(bool $productExists = false): void
    {
        Http::fake([
            '*/graphql.json' => function (Request $request) use ($productExists) {
                $query = $request->data()['query'];

                if (str_contains($query, 'query findProduct')) {
                    $nodes = $productExists ? [[
                        'id' => 'gid://shopify/Product/111',
                        'mediaCount' => ['count' => 1],
                        'variants' => ['nodes' => [[
                            'id' => 'gid://shopify/ProductVariant/222',
                            'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/333'],
                        ]]],
                    ]] : [];

                    return Http::response(['data' => ['products' => ['nodes' => $nodes]]]);
                }

                if (str_contains($query, 'productCreate(product:')) {
                    return Http::response(['data' => ['productCreate' => [
                        'product' => [
                            'id' => 'gid://shopify/Product/111',
                            'variants' => ['nodes' => [[
                                'id' => 'gid://shopify/ProductVariant/222',
                                'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/333'],
                            ]]],
                        ],
                        'userErrors' => [],
                    ]]]);
                }

                if (str_contains($query, 'productUpdate(product:')) {
                    return Http::response(['data' => ['productUpdate' => [
                        'product' => ['id' => 'gid://shopify/Product/111'],
                        'userErrors' => [],
                    ]]]);
                }

                if (str_contains($query, 'productVariantsBulkUpdate')) {
                    return Http::response(['data' => ['productVariantsBulkUpdate' => [
                        'productVariants' => [['id' => 'gid://shopify/ProductVariant/222']],
                        'userErrors' => [],
                    ]]]);
                }

                if (str_contains($query, 'inventorySetQuantities')) {
                    return Http::response(['data' => ['inventorySetQuantities' => ['userErrors' => []]]]);
                }

                if (str_contains($query, 'collectionAddProducts')) {
                    return Http::response(['data' => ['collectionAddProducts' => [
                        'collection' => ['id' => 'gid://shopify/Collection/1234567890'],
                        'userErrors' => [],
                    ]]]);
                }

                if (str_contains($query, 'locations(first: 1)')) {
                    return Http::response(['data' => ['locations' => ['nodes' => [['id' => 'gid://shopify/Location/1']]]]]);
                }

                return Http::response(['errors' => [['message' => 'Unexpected query in test: '.$query]]], 500);
            },
        ]);
    }

    protected function createUpload(string $csvContent): Upload
    {
        Storage::put('uploads/test.csv', $csvContent);

        return Upload::create([
            'original_filename' => 'test.csv',
            'stored_path' => 'uploads/test.csv',
            'status' => Upload::STATUS_PENDING,
        ]);
    }

    public function test_valid_csv_creates_products_on_shopify_and_adds_to_collection(): void
    {
        $this->fakeShopify();

        $csv = self::CSV_HEADER."\n".
            'test-lamp,"Test Lamp","<p>Nice</p>",Acme,Lighting,"lamp,desk",TRUE,TL-001,39.99,49.99,TRUE,TRUE,shopify,25,deny,manual,1.2,kg,https://example.com/img.jpg,1,"Alt text"';

        $upload = $this->createUpload($csv);

        (new ProcessCsvUpload($upload))->handle(app(\App\Services\ShopifyService::class), app(\App\Services\ImportLogger::class));

        $upload->refresh();
        $product = Product::first();

        $this->assertSame(Upload::STATUS_COMPLETED, $upload->status);
        $this->assertSame(1, $upload->successful_rows);
        $this->assertSame(0, $upload->failed_rows);
        $this->assertSame(Product::STATUS_SUCCESSFUL, $product->status);
        $this->assertSame(Product::ACTION_CREATED, $product->action);
        $this->assertSame('gid://shopify/Product/111', $product->shopify_product_id);

        Http::assertSent(fn (Request $r) => str_contains($r->data()['query'], 'collectionAddProducts')
            && $r->data()['variables']['id'] === 'gid://shopify/Collection/1234567890');
    }

    public function test_existing_product_is_updated_not_duplicated(): void
    {
        $this->fakeShopify(productExists: true);

        $csv = self::CSV_HEADER."\n".
            'test-lamp,"Test Lamp","<p>Nice</p>",Acme,Lighting,"lamp",TRUE,TL-001,39.99,,TRUE,TRUE,shopify,25,deny,manual,1.2,kg,,1,';

        $upload = $this->createUpload($csv);

        (new ProcessCsvUpload($upload))->handle(app(\App\Services\ShopifyService::class), app(\App\Services\ImportLogger::class));

        $this->assertSame(Product::ACTION_UPDATED, Product::first()->action);

        Http::assertNotSent(fn (Request $r) => str_contains($r->data()['query'], 'productCreate(product:'));
        Http::assertSent(fn (Request $r) => str_contains($r->data()['query'], 'productUpdate(product:'));
    }

    public function test_invalid_rows_are_marked_failed_and_notification_sent(): void
    {
        $this->fakeShopify();

        $csv = self::CSV_HEADER."\n".
            'bad-product,,"<p>No title</p>",Acme,Misc,"x",TRUE,BP-001,oops,,TRUE,TRUE,shopify,5,deny,manual,1,kg,,1,'."\n".
            'good-product,"Good Product","<p>Ok</p>",Acme,Misc,"x",TRUE,GP-001,9.99,,TRUE,TRUE,shopify,5,deny,manual,1,kg,,1,';

        $upload = $this->createUpload($csv);

        (new ProcessCsvUpload($upload))->handle(app(\App\Services\ShopifyService::class), app(\App\Services\ImportLogger::class));

        $upload->refresh();

        $this->assertSame(Upload::STATUS_COMPLETED_WITH_ERRORS, $upload->status);
        $this->assertSame(1, $upload->successful_rows);
        $this->assertSame(1, $upload->failed_rows);

        $failed = Product::where('handle', 'bad-product')->first();
        $this->assertSame(Product::STATUS_FAILED, $failed->status);
        $this->assertStringContainsString('Title is required', $failed->error_message);

        $this->assertDatabaseHas('import_logs', ['upload_id' => $upload->id, 'level' => 'error']);

        Notification::assertSentOnDemand(ImportFailedNotification::class);
    }

    public function test_csv_with_wrong_headers_fails_the_upload(): void
    {
        $this->fakeShopify();

        $upload = $this->createUpload("Foo,Bar\n1,2");

        (new ProcessCsvUpload($upload))->handle(app(\App\Services\ShopifyService::class), app(\App\Services\ImportLogger::class));

        $upload->refresh();

        $this->assertSame(Upload::STATUS_FAILED, $upload->status);
        $this->assertStringContainsString('missing required columns', $upload->error_message);
        Http::assertNothingSent();
    }
}
