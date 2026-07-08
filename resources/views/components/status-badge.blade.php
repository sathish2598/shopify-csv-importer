@props(['status'])

@php
    $classes = match ($status) {
        'pending' => 'bg-gray-100 text-gray-700',
        'processing' => 'bg-blue-100 text-blue-800',
        'completed', 'successful' => 'bg-green-100 text-green-800',
        'completed_with_errors' => 'bg-yellow-100 text-yellow-800',
        'failed' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium $classes"]) }}>
    {{ str_replace('_', ' ', $status) }}
</span>
