{{-- ARCHIVED / NON-CANONICAL: Smart Tools Blade demo --}}
{{-- No registered runtime route. Former smart-tools demo called legacy /api/universal-frame/* paths and orphan endpoints. --}}
{{-- Do not revive without a new ownership decision and explicit API/route reconciliation. --}}

@extends('layouts.universal-frame')

@section('title', 'Archived Smart Tools Demo')

@section('breadcrumb-root', 'Test')
@php
    $breadcrumbs = ['Smart Tools', 'Archived'];
@endphp

@section('content')
<div class="space-y-6">
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <i class="fas fa-triangle-exclamation text-amber-600 mt-1"></i>
            <div>
                <h3 class="text-lg font-semibold text-amber-900">Archived Smart Tools Demo</h3>
                <p class="text-amber-800 mt-1">
                    This Blade surface is deprecated and non-canonical. It is retained only as an archive marker for the
                    former smart-tools demo and is not a valid runtime owner.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Deprecation Summary</h3>
        <ul class="space-y-3 text-sm text-gray-700">
            <li>The smart-tools Blade stack is non-canonical and archive-target only.</li>
            <li>The four component files were only confirmed from this demo page, not from a live route.</li>
            <li>The former implementation depended on legacy <code>/api/universal-frame/*</code> paths instead of the current <code>/api/v1/universal-frame/*</code> runtime surface.</li>
            <li>Two orphan calls were identified: <code>/api/universal-frame/user/role</code> and <code>/api/universal-frame/export/analysis</code>.</li>
            <li>Do not restore this page as a demo or product surface without a fresh ownership decision.</li>
        </ul>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Status</h3>
        <div class="space-y-3 text-sm text-gray-700">
            <p><span class="font-semibold text-gray-900">Canonical owner:</span> none confirmed.</p>
            <p><span class="font-semibold text-gray-900">Runtime route:</span> not registered.</p>
            <p><span class="font-semibold text-gray-900">Disposition:</span> archived demo retained only as a deprecation marker.</p>
            <p><span class="font-semibold text-gray-900">Requirement to revive:</span> explicit new ownership decision plus route/API reconciliation.</p>
        </div>
    </div>
</div>
@endsection
