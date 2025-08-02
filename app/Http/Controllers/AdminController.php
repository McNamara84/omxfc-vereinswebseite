<?php

namespace App\Http\Controllers;

use App\Models\PageVisit;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index()
    {
        $visitData = PageVisit::select('path', DB::raw('COUNT(*) as total'))
            ->groupBy('path')
            ->orderByDesc('total')
            ->get();

        return view('admin.index', compact('visitData'));
    }
}
