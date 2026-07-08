# Shopify CSV Product Importer

A Laravel 12 application that imports products into a Shopify store from an uploaded CSV file. Files are processed asynchronously through Laravel's queue system, and every imported product is automatically added to a configured Shopify collection. A dashboard tracks the status of each upload and each product row in real time.

## Features

- **CSV upload interface** — drag & drop upload with client-side and server-side validation (CSV only, max 2 MB)
- **Background processing** — CSV parsing and Shopify imports run in a queued job, never blocking the request
- **Shopify GraphQL Admin API** — products are created via `productCreate`, variants via `productVariantsBulkUpdate`, stock via `inventorySetQuantities` (the REST product APIs are deprecated by Shopify)
- **Automatic collection assignment** — every imported product is added to the configured collection via `collectionAddProducts`
- **Update if exists** *(bonus)* — products are matched by handle; existing products are updated instead of duplicated
- **Import dashboard** — per-upload progress, per-product status (pending / processing / successful / failed), created vs updated action, direct links to the product in Shopify admin, readable error messages; auto-refreshes every 5 s while an import is running
- **Comprehensive logging** *(bonus)* — every import event is written to the `import_logs` table and to a dedicated `storage/logs/imports-*.log` channel; a log viewer page supports filtering by level and upload
- **Failure notifications** *(bonus)* — an email notification is sent when an upload finishes with failed rows
- **Resilience** — automatic retry with exponential backoff on Shopify rate-limit (throttle) responses; row-level failures never abort the rest of the file

## Requirements

- PHP 8.2+ (built on 8.4)
- Composer
- MySQL 8
- Node.js + npm (for building assets)
- A Shopify store with an Admin API access token (scopes: `read_products`, `write_products`, `read_inventory`, `write_inventory`, `read_locations`)

## Setup

```bash
git clone <repo-url>
cd shopify-csv-importer

composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
```

Configure your database and Shopify credentials in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shopify_csv_importer
DB_USERNAME=your_user
DB_PASSWORD=your_password

SHOPIFY_STORE_URL=your-store.myshopify.com
SHOPIFY_ACCESS_TOKEN=shpat_xxxxxxxxxxxx
SHOPIFY_API_VERSION=2025-07
SHOPIFY_COLLECTION_ID=464337174767
```

Then run the migrations:

```bash
php artisan migrate
```

## Running the application

Two processes are required — the web server and a queue worker:

```bash
php artisan serve          # http://127.0.0.1:8000
php artisan queue:work     # processes the CSV imports
```

Open http://127.0.0.1:8000, upload a product CSV (see `sample/shopify-products-csv.csv` for the expected format), and watch the import progress on the **Dashboard**. Import events are visible on the **Logs** page.

### Expected CSV format

The standard Shopify product CSV columns are expected:

```
Handle, Title, Body HTML, Vendor, Product Type, Tags, Published,
Variant SKU, Variant Price, Variant Compare At Price, Variant Requires Shipping,
Variant Taxable, Variant Inventory Tracker, Variant Inventory Qty,
Variant Inventory Policy, Variant Fulfillment Service, Variant Weight,
Variant Weight Unit, Image Src, Image Position, Image Alt Text
```

## Testing

Tests run against a dedicated MySQL database — create it once, then run the suite:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS shopify_csv_importer_test"
php artisan test
```

All Shopify HTTP traffic is faked in tests (`Http::fake()`), so no real API calls are made. Coverage includes:

- upload validation (file type, size, missing file)
- job dispatching on successful upload
- full import flow: products created on Shopify and added to the collection
- update-if-exists path (no duplicate `productCreate` calls)
- row-level validation failures, error logging, and failure notification
- malformed CSV header handling

## Architecture

| Piece | Location | Responsibility |
|---|---|---|
| `UploadController` | `app/Http/Controllers` | Validates and stores the CSV, dispatches the job |
| `ProcessCsvUpload` | `app/Jobs` | Parses the CSV, validates rows, orchestrates the import, finalizes counters/status |
| `ShopifyService` | `app/Services` | All Shopify GraphQL calls: find/create/update product, variant + inventory updates, collection assignment, throttle retry |
| `ImportLogger` | `app/Services` | Writes every import event to the DB and the `imports` log channel |
| `Upload`, `Product`, `ImportLog` | `app/Models` | Import state tracking |
| Dashboard / Logs views | `resources/views` | Status monitoring UI (Blade + Tailwind CSS) |

## Design decisions & assumptions

- **GraphQL over REST** — Shopify has deprecated the REST product endpoints; all API interaction uses the GraphQL Admin API (bonus requirement).
- **Match by handle** — a product "already exists" when a product with the same handle is found in the store. Updates refresh title, description, vendor, type, tags, status, price, SKU, weight, and inventory. An image is only attached on update if the product has no media yet (prevents duplicate images on re-import).
- **Row-level isolation** — each CSV row succeeds or fails independently; one bad row never blocks the rest of the file.
- **Single-variant products** — the sample CSV contains one variant per product, so the importer maps each row to the product's default variant. Multi-variant option columns are out of scope.
- **Inventory** — quantities are set at the store's first location using absolute values (`inventorySetQuantities` with `ignoreCompareQuantity`).
- **Published flag** — `TRUE` maps to product status `ACTIVE`, anything else to `DRAFT`.
