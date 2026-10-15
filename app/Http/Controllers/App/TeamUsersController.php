<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;

class TeamUsersController extends Controller
{
    public function index()
    {
        return view('app.team.users');
    }
}
