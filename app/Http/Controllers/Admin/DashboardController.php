<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function home()
    {
        return view('Dashboard.dashboard');
    }

    public function settings()
    {
        return view('Settings.settings');
    }

    public function profile()
    {
        return view('Profile.profile');
    }
}
