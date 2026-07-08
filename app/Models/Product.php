<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESSFUL = 'successful';
    public const STATUS_FAILED = 'failed';

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';

    protected $fillable = [
        'upload_id',
        'handle',
        'title',
        'body_html',
        'vendor',
        'product_type',
        'tags',
        'published',
        'sku',
        'price',
        'compare_at_price',
        'inventory_qty',
        'weight',
        'weight_unit',
        'image_src',
        'image_alt',
        'status',
        'shopify_product_id',
        'action',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'weight' => 'decimal:3',
            'inventory_qty' => 'integer',
        ];
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ImportLog::class);
    }

    public function shopifyAdminUrl(): ?string
    {
        if (! $this->shopify_product_id) {
            return null;
        }

        $numericId = str_replace('gid://shopify/Product/', '', $this->shopify_product_id);

        return 'https://'.config('services.shopify.store_url').'/admin/products/'.$numericId;
    }
}
