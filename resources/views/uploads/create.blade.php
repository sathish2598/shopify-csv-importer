@extends('layouts.app')

@section('title', 'Upload CSV — Shopify CSV Importer')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-2 text-2xl font-bold">Import Products</h1>
        <p class="mb-6 text-sm text-gray-600">
            Upload a Shopify product CSV. The file is processed in the background and every
            product is imported into your Shopify store and added to the configured collection.
        </p>

        <form method="POST" action="{{ route('uploads.store') }}" enctype="multipart/form-data"
              id="upload-form" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @csrf

            <label for="csv_file" id="drop-zone"
                   class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 px-6 py-12 text-center transition hover:border-indigo-400 hover:bg-indigo-50">
                <svg class="mb-3 h-10 w-10 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                </svg>
                <span class="text-sm font-medium text-gray-700">
                    Drag &amp; drop your CSV here, or <span class="text-indigo-600">browse</span>
                </span>
                <span class="mt-1 text-xs text-gray-500">CSV only, max 2 MB</span>
                <span id="file-name" class="mt-3 hidden rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-800"></span>
            </label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" class="hidden" required>

            <p id="client-error" class="mt-3 hidden text-sm text-red-600"></p>

            <button type="submit" id="submit-btn" disabled
                    class="mt-6 w-full rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40">
                Upload &amp; Import
            </button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    const input = document.getElementById('csv_file');
    const dropZone = document.getElementById('drop-zone');
    const fileName = document.getElementById('file-name');
    const clientError = document.getElementById('client-error');
    const submitBtn = document.getElementById('submit-btn');
    const MAX_BYTES = 2 * 1024 * 1024;

    function validateAndShow(file) {
        clientError.classList.add('hidden');
        fileName.classList.add('hidden');
        submitBtn.disabled = true;

        if (!file) return;

        if (!file.name.toLowerCase().endsWith('.csv')) {
            clientError.textContent = 'Only .csv files are allowed.';
            clientError.classList.remove('hidden');
            input.value = '';
            return;
        }

        if (file.size > MAX_BYTES) {
            clientError.textContent = 'The file may not be larger than 2 MB.';
            clientError.classList.remove('hidden');
            input.value = '';
            return;
        }

        fileName.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        fileName.classList.remove('hidden');
        submitBtn.disabled = false;
    }

    input.addEventListener('change', () => validateAndShow(input.files[0]));

    ['dragover', 'dragenter'].forEach(evt => dropZone.addEventListener(evt, e => {
        e.preventDefault();
        dropZone.classList.add('border-indigo-400', 'bg-indigo-50');
    }));

    ['dragleave', 'drop'].forEach(evt => dropZone.addEventListener(evt, e => {
        e.preventDefault();
        dropZone.classList.remove('border-indigo-400', 'bg-indigo-50');
    }));

    dropZone.addEventListener('drop', e => {
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            validateAndShow(input.files[0]);
        }
    });
</script>
@endpush
