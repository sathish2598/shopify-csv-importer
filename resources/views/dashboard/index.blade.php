@extends('layouts.app')

@section('title', 'Dashboard — Shopify CSV Importer')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Import Dashboard</h1>
        <a href="{{ route('uploads.create') }}"
           class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            New Import
        </a>
    </div>

    @php $anyProcessing = $uploads->contains(fn ($u) => $u->isProcessing()); @endphp

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">File</th>
                    <th class="px-4 py-3">Uploaded</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Progress</th>
                    <th class="px-4 py-3 text-center">Successful</th>
                    <th class="px-4 py-3 text-center">Failed</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($uploads as $upload)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $upload->original_filename }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $upload->created_at->format('M j, Y H:i') }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$upload->status"/></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                                    <div class="h-full rounded-full bg-indigo-500 transition-all"
                                         style="width: {{ $upload->progressPercent() }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500">
                                    {{ $upload->processed_rows }}/{{ $upload->total_rows }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center font-medium text-green-700">{{ $upload->successful_rows }}</td>
                        <td class="px-4 py-3 text-center font-medium {{ $upload->failed_rows > 0 ? 'text-red-700' : 'text-gray-400' }}">
                            {{ $upload->failed_rows }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('dashboard.show', $upload) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                Details →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                            No imports yet. <a href="{{ route('uploads.create') }}" class="text-indigo-600 hover:underline">Upload a CSV</a> to get started.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $uploads->links() }}</div>

    @if ($anyProcessing)
        <p class="mt-4 text-center text-xs text-gray-500">An import is in progress — this page refreshes automatically every 5 seconds.</p>
    @endif
@endsection

@push('scripts')
    @if ($anyProcessing)
        <script>setTimeout(() => window.location.reload(), 5000);</script>
    @endif
@endpush
