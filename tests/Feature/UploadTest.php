<?php

namespace Tests\Feature;

use App\Jobs\ProcessCsvUpload;
use App\Models\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_page_renders(): void
    {
        $this->get(route('uploads.create'))
            ->assertOk()
            ->assertSee('Import Products');
    }

    public function test_valid_csv_is_stored_and_job_dispatched(): void
    {
        Queue::fake();
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('products.csv', "Handle,Title\nfoo,Bar");

        $this->post(route('uploads.store'), ['csv_file' => $file])
            ->assertRedirect(route('dashboard.index'))
            ->assertSessionHas('success');

        $upload = Upload::first();

        $this->assertNotNull($upload);
        $this->assertSame('products.csv', $upload->original_filename);
        $this->assertSame(Upload::STATUS_PENDING, $upload->status);
        Storage::assertExists($upload->stored_path);

        Queue::assertPushed(ProcessCsvUpload::class, fn ($job) => $job->upload->is($upload));
    }

    public function test_non_csv_file_is_rejected(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->create('products.pdf', 10, 'application/pdf');

        $this->post(route('uploads.store'), ['csv_file' => $file])
            ->assertSessionHasErrors('csv_file');

        $this->assertDatabaseCount('uploads', 0);
        Queue::assertNothingPushed();
    }

    public function test_oversized_file_is_rejected(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->create('products.csv', 3000, 'text/csv');

        $this->post(route('uploads.store'), ['csv_file' => $file])
            ->assertSessionHasErrors('csv_file');

        $this->assertDatabaseCount('uploads', 0);
        Queue::assertNothingPushed();
    }

    public function test_missing_file_is_rejected(): void
    {
        $this->post(route('uploads.store'), [])
            ->assertSessionHasErrors('csv_file');
    }
}
