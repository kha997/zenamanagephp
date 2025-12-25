<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SimpleDocumentController extends Controller
{
    /**
     * Placeholder controller used by legacy routes.
     */
    public function index(Request $request)
    {
        return response()->noContent();
    }

    public function store(Request $request)
    {
        return response()->noContent();
    }

    public function show(Request $request, $id)
    {
        return response()->noContent();
    }

    public function update(Request $request, $id)
    {
        return response()->noContent();
    }

    public function destroy(Request $request, $id)
    {
        return response()->noContent();
    }
}
