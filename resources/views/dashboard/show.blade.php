@extends('layouts.app')

@section('title', $upload->original_filename.' — Shopify CSV Importer')

@section('content')
    <div class="mb-2">
        <a href="{{ route('dashboard.index') }}" class="text-sm text-indigo-600 hover:underline">← Back to dashboard</a>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-4">
        <h1 class="text-2xl font-bold">{{ $upload->original_filename }}</h1>
        <x-status-badge :status="$upload->status"/>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach ([
            'Total rows' => [$upload->total_rows, 'text-gray-900'],
            'Processed' => [$upload->processed_rows, 'text-blue-700'],
            'Successful' => [$upload->successful_rows, 'text-green-700'],
            'Failed' => [$upload->failed_rows, $upload->failed_rows > 0 ? 'text-red-700' : 'text-gray-400'],
        ] as $label => [$value, $color])
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold {{ $color }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @if ($upload->error_message)
        <div class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $upload->error_message }}
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Handle</th>
                    <th class="px-4 py-3">SKU</th>
                    <th class="px-4 py-3">Price</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($products as $product)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $product->title }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $product->handle }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $product->sku ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $product->price !== null ? '$'.$product->price : '—' }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$product->status"/></td>
                        <td class="px-4 py-3 text-gray-500">{{ $product->action ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($product->status === 'failed')
                                <span class="text-xs text-red-700">{{ $product->error_message }}</span>
                            @elseif ($product->shopifyAdminUrl())
                                <a href="{{ $product->shopifyAdminUrl() }}" target="_blank" rel="noopener"
                                   class="text-xs font-medium text-indigo-600 hover:underline">View in Shopify ↗</a>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                            @if ($upload->isProcessing())
                                Parsing CSV… products will appear shortly.
                            @else
                                No products found for this upload.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>

    @if ($upload->isProcessing())
        <p class="mt-4 text-center text-xs text-gray-500">Import in progress — this page refreshes automatically every 5 seconds.</p>
    @endif
@endsection

@push('scripts')
    @if ($upload->isProcessing())
        <script>setTimeout(() => window.location.reload(), 5000);</script>
    @endif
@endpush
