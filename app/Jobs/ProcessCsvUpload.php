<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Upload;
use App\Notifications\ImportFailedNotification;
use App\Services\ImportLogger;
use App\Services\ShopifyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessCsvUpload implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    protected const EXPECTED_HEADERS = [
        'Handle', 'Title', 'Body HTML', 'Vendor', 'Product Type', 'Tags', 'Published',
        'Variant SKU', 'Variant Price', 'Variant Compare At Price', 'Variant Requires Shipping',
        'Variant Taxable', 'Variant Inventory Tracker', 'Variant Inventory Qty',
        'Variant Inventory Policy', 'Variant Fulfillment Service', 'Variant Weight',
        'Variant Weight Unit', 'Image Src', 'Image Position', 'Image Alt Text',
    ];

    public function __construct(public Upload $upload)
    {
    }

    public function handle(ShopifyService $shopify, ImportLogger $logger): void
    {
        $this->upload->update(['status' => Upload::STATUS_PROCESSING]);
        $logger->info("Started processing \"{$this->upload->original_filename}\"", $this->upload->id);

        try {
            $rows = $this->parseCsv();
        } catch (Throwable $e) {
            $this->upload->update([
                'status' => Upload::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
            $logger->error('CSV parsing failed: '.$e->getMessage(), $this->upload->id);

            return;
        }

        $this->upload->update(['total_rows' => count($rows)]);

        foreach ($rows as $lineNumber => $row) {
            $this->processRow($row, $lineNumber, $shopify, $logger);
        }

        $this->finalize($logger);
    }

    /**
     * @return array<int, array<string, string>> rows keyed by CSV line number
     */
    protected function parseCsv(): array
    {
        $path = Storage::path($this->upload->stored_path);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open the uploaded CSV file.');
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                throw new \RuntimeException('The CSV file is empty.');
            }

            // Strip UTF-8 BOM from the first header if present.
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

            $missing = array_diff(self::EXPECTED_HEADERS, $headers);

            if ($missing !== []) {
                throw new \RuntimeException('CSV is missing required columns: '.implode(', ', $missing));
            }

            $rows = [];
            $lineNumber = 1;

            while (($line = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $rows[$lineNumber] = array_combine($headers, array_pad($line, count($headers), ''));
            }

            if ($rows === []) {
                throw new \RuntimeException('The CSV file contains no product rows.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    protected function processRow(array $row, int $lineNumber, ShopifyService $shopify, ImportLogger $logger): void
    {
        $validationError = $this->validateRow($row);

        $product = Product::create([
            'upload_id' => $this->upload->id,
            'handle' => $row['Handle'] ?: 'row-'.$lineNumber,
            'title' => $row['Title'] ?: '(missing title)',
            'body_html' => $row['Body HTML'] ?: null,
            'vendor' => $row['Vendor'] ?: null,
            'product_type' => $row['Product Type'] ?: null,
            'tags' => $row['Tags'] ?: null,
            'published' => strtoupper(trim($row['Published'])) !== 'FALSE',
            'sku' => $row['Variant SKU'] ?: null,
            'price' => is_numeric($row['Variant Price']) ? $row['Variant Price'] : null,
            'compare_at_price' => is_numeric($row['Variant Compare At Price']) ? $row['Variant Compare At Price'] : null,
            'inventory_qty' => is_numeric($row['Variant Inventory Qty']) ? (int) $row['Variant Inventory Qty'] : 0,
            'weight' => is_numeric($row['Variant Weight']) ? $row['Variant Weight'] : null,
            'weight_unit' => $row['Variant Weight Unit'] ?: null,
            'image_src' => $row['Image Src'] ?: null,
            'image_alt' => $row['Image Alt Text'] ?: null,
            'status' => Product::STATUS_PROCESSING,
        ]);

        if ($validationError !== null) {
            $this->markFailed($product, "Row {$lineNumber}: {$validationError}", $logger);

            return;
        }

        try {
            $result = $shopify->importProduct($product);

            $product->update([
                'status' => Product::STATUS_SUCCESSFUL,
                'shopify_product_id' => $result['shopify_product_id'],
                'action' => $result['action'],
            ]);

            $this->upload->increment('successful_rows');
            $logger->info(
                "Product \"{$product->title}\" {$result['action']} on Shopify and added to collection",
                $this->upload->id,
                $product->id,
                ['shopify_product_id' => $result['shopify_product_id']]
            );
        } catch (Throwable $e) {
            $this->markFailed($product, $e->getMessage(), $logger);
        } finally {
            $this->upload->increment('processed_rows');
        }
    }

    protected function validateRow(array $row): ?string
    {
        if (trim($row['Handle']) === '') {
            return 'Handle is required.';
        }

        if (trim($row['Title']) === '') {
            return 'Title is required.';
        }

        if (! is_numeric($row['Variant Price']) || (float) $row['Variant Price'] < 0) {
            return 'Variant Price must be a non-negative number.';
        }

        if ($row['Variant Compare At Price'] !== '' && ! is_numeric($row['Variant Compare At Price'])) {
            return 'Variant Compare At Price must be a number.';
        }

        if ($row['Variant Inventory Qty'] !== '' && ! is_numeric($row['Variant Inventory Qty'])) {
            return 'Variant Inventory Qty must be a number.';
        }

        if ($row['Image Src'] !== '' && ! filter_var($row['Image Src'], FILTER_VALIDATE_URL)) {
            return 'Image Src must be a valid URL.';
        }

        return null;
    }

    protected function markFailed(Product $product, string $message, ImportLogger $logger): void
    {
        $product->update([
            'status' => Product::STATUS_FAILED,
            'error_message' => $message,
        ]);

        $this->upload->increment('failed_rows');
        $logger->error("Product \"{$product->title}\" failed: {$message}", $this->upload->id, $product->id);
    }

    protected function finalize(ImportLogger $logger): void
    {
        $this->upload->refresh();

        $status = $this->upload->failed_rows > 0
            ? ($this->upload->successful_rows > 0 ? Upload::STATUS_COMPLETED_WITH_ERRORS : Upload::STATUS_FAILED)
            : Upload::STATUS_COMPLETED;

        $this->upload->update(['status' => $status]);

        $logger->info(
            "Finished \"{$this->upload->original_filename}\": {$this->upload->successful_rows} successful, {$this->upload->failed_rows} failed",
            $this->upload->id
        );

        if ($this->upload->failed_rows > 0) {
            Notification::route('mail', config('mail.from.address'))
                ->notify(new ImportFailedNotification($this->upload));
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->upload->update([
            'status' => Upload::STATUS_FAILED,
            'error_message' => $exception?->getMessage() ?? 'Unknown error',
        ]);
    }
}
