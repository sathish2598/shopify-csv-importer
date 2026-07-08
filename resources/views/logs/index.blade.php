@extends('layouts.app')

@section('title', 'Import Logs — Shopify CSV Importer')

@section('content')
    <h1 class="mb-6 text-2xl font-bold">Import Logs</h1>

    <form method="GET" action="{{ route('logs.index') }}" class="mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label for="level" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Level</label>
            <select name="level" id="level" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">All levels</option>
                @foreach (['info', 'warning', 'error'] as $level)
                    <option value="{{ $level }}" @selected(request('level') === $level)>{{ ucfirst($level) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="upload_id" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Upload</label>
            <select name="upload_id" id="upload_id" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">All uploads</option>
                @foreach ($uploads as $upload)
                    <option value="{{ $upload->id }}" @selected(request('upload_id') == $upload->id)>
                        #{{ $upload->id }} — {{ $upload->original_filename }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
            Filter
        </button>
        @if (request()->hasAny(['level', 'upload_id']))
            <a href="{{ route('logs.index') }}" class="py-2 text-sm text-indigo-600 hover:underline">Clear</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Level</th>
                    <th class="px-4 py-3">Upload</th>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Message</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ $log->created_at->format('M j, H:i:s') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium
                                {{ match ($log->level) {
                                    'error' => 'bg-red-100 text-red-800',
                                    'warning' => 'bg-yellow-100 text-yellow-800',
                                    default => 'bg-blue-100 text-blue-800',
                                } }}">
                                {{ $log->level }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            @if ($log->upload)
                                <a href="{{ route('dashboard.show', $log->upload) }}" class="text-indigo-600 hover:underline">
                                    #{{ $log->upload->id }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $log->product?->title ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $log->message }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-500">No log entries found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
