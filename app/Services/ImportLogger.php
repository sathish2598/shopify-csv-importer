<?php

namespace App\Services;

use App\Models\ImportLog;
use Illuminate\Support\Facades\Log;

class ImportLogger
{
    public function info(string $message, ?int $uploadId = null, ?int $productId = null, array $context = []): void
    {
        $this->write(ImportLog::LEVEL_INFO, $message, $uploadId, $productId, $context);
    }

    public function warning(string $message, ?int $uploadId = null, ?int $productId = null, array $context = []): void
    {
        $this->write(ImportLog::LEVEL_WARNING, $message, $uploadId, $productId, $context);
    }

    public function error(string $message, ?int $uploadId = null, ?int $productId = null, array $context = []): void
    {
        $this->write(ImportLog::LEVEL_ERROR, $message, $uploadId, $productId, $context);
    }

    protected function write(string $level, string $message, ?int $uploadId, ?int $productId, array $context): void
    {
        ImportLog::create([
            'upload_id' => $uploadId,
            'product_id' => $productId,
            'level' => $level,
            'message' => $message,
            'context' => $context ?: null,
        ]);

        Log::channel('imports')->{$level}($message, array_merge($context, [
            'upload_id' => $uploadId,
            'product_id' => $productId,
        ]));
    }
}
