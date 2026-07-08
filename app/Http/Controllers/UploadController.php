<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUploadRequest;
use App\Jobs\ProcessCsvUpload;
use App\Models\Upload;
use App\Services\ImportLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UploadController extends Controller
{
    public function create(): View
    {
        return view('uploads.create');
    }

    public function store(StoreUploadRequest $request, ImportLogger $logger): RedirectResponse
    {
        $file = $request->file('csv_file');
        $storedPath = $file->store('uploads');

        $upload = Upload::create([
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => Upload::STATUS_PENDING,
        ]);

        $logger->info("File \"{$upload->original_filename}\" uploaded, queued for processing", $upload->id);

        ProcessCsvUpload::dispatch($upload);

        return redirect()
            ->route('dashboard.index')
            ->with('success', "\"{$upload->original_filename}\" uploaded successfully and queued for import.");
    }
}
