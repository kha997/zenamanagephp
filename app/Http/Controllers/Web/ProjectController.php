<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        // Simple fallback - return empty data to let Alpine.js handle it
        return view('app.projects.index', [
            'projects' => [],
            'clients' => collect(),
            'kpis' => [],
            'viewMode' => 'card',
            'filters' => [],
            'error' => null
        ]);
    }
}
