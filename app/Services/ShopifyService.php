<?php

namespace App\Services;

use App\Exceptions\ShopifyApiException;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

class ShopifyService
{
    protected string $storeUrl;

    protected string $accessToken;

    protected string $apiVersion;

    protected string $collectionId;

    protected ?string $locationId = null;

    protected const WEIGHT_UNITS = [
        'kg' => 'KILOGRAMS',
        'g' => 'GRAMS',
        'lb' => 'POUNDS',
        'oz' => 'OUNCES',
    ];

    public function __construct()
    {
        $config = config('services.shopify');

        $this->storeUrl = $config['store_url'];
        $this->accessToken = $config['access_token'];
        $this->apiVersion = $config['api_version'];
        $this->collectionId = $config['collection_id'];
    }

    /**
     * Import a product: create it on Shopify, or update it when the handle
     * already exists. Always ensures it belongs to the configured collection.
     *
     * @return array{shopify_product_id: string, action: string}
     */
    public function importProduct(Product $product): array
    {
        $existing = $this->findProductByHandle($product->handle);

        if ($existing) {
            $productGid = $this->updateProduct($existing, $product);
            $action = Product::ACTION_UPDATED;
        } else {
            $productGid = $this->createProduct($product);
            $action = Product::ACTION_CREATED;
        }

        $this->addToCollection($productGid);

        return ['shopify_product_id' => $productGid, 'action' => $action];
    }

    public function findProductByHandle(string $handle): ?array
    {
        $data = $this->graphql(<<<'GQL'
            query findProduct($query: String!) {
                products(first: 1, query: $query) {
                    nodes {
                        id
                        mediaCount { count }
                        variants(first: 1) {
                            nodes {
                                id
                                inventoryItem { id }
                            }
                        }
                    }
                }
            }
        GQL, ['query' => 'handle:"'.$handle.'"']);

        return $data['products']['nodes'][0] ?? null;
    }

    protected function createProduct(Product $product): string
    {
        $input = [
            'title' => $product->title,
            'handle' => $product->handle,
            'descriptionHtml' => $product->body_html ?? '',
            'vendor' => $product->vendor,
            'productType' => $product->product_type,
            'tags' => $this->parseTags($product->tags),
            'status' => $product->published ? 'ACTIVE' : 'DRAFT',
        ];

        $data = $this->graphql(<<<'GQL'
            mutation createProduct($input: ProductCreateInput!, $media: [CreateMediaInput!]) {
                productCreate(product: $input, media: $media) {
                    product {
                        id
                        variants(first: 1) {
                            nodes {
                                id
                                inventoryItem { id }
                            }
                        }
                    }
                    userErrors { field message }
                }
            }
        GQL, [
            'input' => $input,
            'media' => $this->buildMediaInput($product),
        ]);

        $this->assertNoUserErrors($data['productCreate'] ?? [], 'productCreate');

        $shopifyProduct = $data['productCreate']['product'];
        $variant = $shopifyProduct['variants']['nodes'][0];

        $this->updateVariant($shopifyProduct['id'], $variant['id'], $product);
        $this->setInventory($variant['inventoryItem']['id'], $product->inventory_qty);

        return $shopifyProduct['id'];
    }

    protected function updateProduct(array $existing, Product $product): string
    {
        $productGid = $existing['id'];

        $data = $this->graphql(<<<'GQL'
            mutation updateProduct($input: ProductUpdateInput!) {
                productUpdate(product: $input) {
                    product { id }
                    userErrors { field message }
                }
            }
        GQL, [
            'input' => [
                'id' => $productGid,
                'title' => $product->title,
                'descriptionHtml' => $product->body_html ?? '',
                'vendor' => $product->vendor,
                'productType' => $product->product_type,
                'tags' => $this->parseTags($product->tags),
                'status' => $product->published ? 'ACTIVE' : 'DRAFT',
            ],
        ]);

        $this->assertNoUserErrors($data['productUpdate'] ?? [], 'productUpdate');

        if (($existing['mediaCount']['count'] ?? 0) === 0 && $product->image_src) {
            $this->attachMedia($productGid, $product);
        }

        $variant = $existing['variants']['nodes'][0];
        $this->updateVariant($productGid, $variant['id'], $product);
        $this->setInventory($variant['inventoryItem']['id'], $product->inventory_qty);

        return $productGid;
    }

    protected function updateVariant(string $productGid, string $variantGid, Product $product): void
    {
        $variantInput = [
            'id' => $variantGid,
            'price' => (string) $product->price,
            'compareAtPrice' => $product->compare_at_price !== null ? (string) $product->compare_at_price : null,
            'inventoryPolicy' => 'DENY',
            'inventoryItem' => [
                'sku' => $product->sku,
                'tracked' => true,
                'requiresShipping' => true,
            ],
        ];

        if ($product->weight !== null) {
            $variantInput['inventoryItem']['measurement'] = [
                'weight' => [
                    'value' => (float) $product->weight,
                    'unit' => self::WEIGHT_UNITS[strtolower((string) $product->weight_unit)] ?? 'KILOGRAMS',
                ],
            ];
        }

        $data = $this->graphql(<<<'GQL'
            mutation updateVariant($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                    productVariants { id }
                    userErrors { field message }
                }
            }
        GQL, [
            'productId' => $productGid,
            'variants' => [$variantInput],
        ]);

        $this->assertNoUserErrors($data['productVariantsBulkUpdate'] ?? [], 'productVariantsBulkUpdate');
    }

    protected function setInventory(string $inventoryItemGid, int $quantity): void
    {
        $data = $this->graphql(<<<'GQL'
            mutation setInventory($input: InventorySetQuantitiesInput!) {
                inventorySetQuantities(input: $input) {
                    userErrors { field message }
                }
            }
        GQL, [
            'input' => [
                'name' => 'available',
                'reason' => 'correction',
                'ignoreCompareQuantity' => true,
                'quantities' => [[
                    'inventoryItemId' => $inventoryItemGid,
                    'locationId' => $this->locationId(),
                    'quantity' => $quantity,
                ]],
            ],
        ]);

        $this->assertNoUserErrors($data['inventorySetQuantities'] ?? [], 'inventorySetQuantities');
    }

    public function addToCollection(string $productGid): void
    {
        $data = $this->graphql(<<<'GQL'
            mutation addToCollection($id: ID!, $productIds: [ID!]!) {
                collectionAddProducts(id: $id, productIds: $productIds) {
                    collection { id }
                    userErrors { field message }
                }
            }
        GQL, [
            'id' => 'gid://shopify/Collection/'.$this->collectionId,
            'productIds' => [$productGid],
        ]);

        $userErrors = $data['collectionAddProducts']['userErrors'] ?? [];

        // Re-importing a product already in the collection is not an error.
        $userErrors = array_filter($userErrors, fn ($e) => ! str_contains(strtolower($e['message']), 'already'));

        if ($userErrors !== []) {
            throw new ShopifyApiException(
                'collectionAddProducts failed: '.collect($userErrors)->pluck('message')->implode('; '),
                $userErrors
            );
        }
    }

    protected function attachMedia(string $productGid, Product $product): void
    {
        $data = $this->graphql(<<<'GQL'
            mutation attachMedia($productId: ID!, $media: [CreateMediaInput!]!) {
                productCreateMedia(productId: $productId, media: $media) {
                    media { alt }
                    mediaUserErrors { field message }
                }
            }
        GQL, [
            'productId' => $productGid,
            'media' => $this->buildMediaInput($product),
        ]);

        $errors = $data['productCreateMedia']['mediaUserErrors'] ?? [];

        if ($errors !== []) {
            throw new ShopifyApiException(
                'productCreateMedia failed: '.collect($errors)->pluck('message')->implode('; '),
                $errors
            );
        }
    }

    protected function buildMediaInput(Product $product): array
    {
        if (! $product->image_src) {
            return [];
        }

        return [[
            'originalSource' => $product->image_src,
            'alt' => $product->image_alt ?? $product->title,
            'mediaContentType' => 'IMAGE',
        ]];
    }

    protected function locationId(): string
    {
        if ($this->locationId === null) {
            $data = $this->graphql('{ locations(first: 1) { nodes { id } } }');

            $this->locationId = $data['locations']['nodes'][0]['id']
                ?? throw new ShopifyApiException('No Shopify location found for inventory.');
        }

        return $this->locationId;
    }

    protected function parseTags(?string $tags): array
    {
        return collect(explode(',', (string) $tags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Execute a GraphQL request against the Shopify Admin API with
     * automatic retry on throttling.
     */
    public function graphql(string $query, array $variables = []): array
    {
        $endpoint = sprintf('https://%s/admin/api/%s/graphql.json', $this->storeUrl, $this->apiVersion);

        $attempts = 0;

        while (true) {
            $attempts++;

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($endpoint, [
                'query' => $query,
                'variables' => $variables === [] ? (object) [] : $variables,
            ]);

            $throttled = $response->status() === 429
                || collect($response->json('errors') ?? [])
                    ->contains(fn ($e) => ($e['extensions']['code'] ?? null) === 'THROTTLED');

            if ($throttled && $attempts < 4) {
                sleep(2 ** $attempts);

                continue;
            }

            if ($response->failed()) {
                throw new ShopifyApiException(
                    'Shopify API request failed with HTTP status '.$response->status(),
                    ['body' => $response->body()]
                );
            }

            $errors = $response->json('errors');

            if (! empty($errors)) {
                throw new ShopifyApiException(
                    'Shopify GraphQL error: '.collect($errors)->pluck('message')->implode('; '),
                    $errors
                );
            }

            return $response->json('data') ?? [];
        }
    }

    protected function assertNoUserErrors(array $payload, string $mutation): void
    {
        $userErrors = $payload['userErrors'] ?? [];

        if ($userErrors !== []) {
            throw new ShopifyApiException(
                $mutation.' failed: '.collect($userErrors)->pluck('message')->implode('; '),
                $userErrors
            );
        }
    }
}
