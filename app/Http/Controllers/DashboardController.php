<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $uploads = Upload::latest()->paginate(10);

        return view('dashboard.index', compact('uploads'));
    }

    public function show(Upload $upload): View
    {
        $products = $upload->products()->orderBy('id')->paginate(25);

        return view('dashboard.show', compact('upload', 'products'));
    }
}
