<?php

use Illuminate\Support\Facades\Route;

// Legacy redirects (301) - Single source of truth
// Each redirect is defined exactly once

// Dashboard redirects
Route::permanentRedirect('/dashboard', '/app/dashboard');

// Project redirects
Route::permanentRedirect('/projects', '/app/projects');

// Task redirects
Route::permanentRedirect('/tasks', '/app/tasks');

// Client redirects
Route::permanentRedirect('/clients', '/app/clients');

// Quote redirects
Route::permanentRedirect('/quotes', '/app/quotes');

// Team redirects
Route::permanentRedirect('/team', '/app/team');

// Calendar redirects
Route::permanentRedirect('/calendar', '/app/calendar');

// Settings redirects
Route::permanentRedirect('/settings', '/app/settings');

// Documents redirects
Route::permanentRedirect('/documents', '/app/documents');

// Templates redirects
Route::permanentRedirect('/templates', '/app/templates');

// Invitation redirects (301)
Route::permanentRedirect('/invite/accept/{token}', '/app/invitations/accept/{token}');
Route::permanentRedirect('/invite/decline/{token}', '/app/invitations/decline/{token}');

// Health/Performance redirects (301)
Route::permanentRedirect('/health', '/_debug/health');
Route::permanentRedirect('/metrics', '/_debug/metrics');
Route::permanentRedirect('/health-check', '/_debug/health-check');
Route::permanentRedirect('/clear-cache', '/_debug/clear-cache');
