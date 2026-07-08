<?php

namespace App\Http\Controllers;

use App\Models\ImportLog;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ImportLog::with(['upload', 'product'])
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->input('level')))
            ->when($request->filled('upload_id'), fn ($q) => $q->where('upload_id', $request->input('upload_id')))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $uploads = Upload::latest()->get(['id', 'original_filename']);

        return view('logs.index', compact('logs', 'uploads'));
    }
}
