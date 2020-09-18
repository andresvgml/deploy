<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;

class RedirectController extends Controller
{
    public function index()
    {
        Auth::logout();
        if (Auth::check()) {
            return Redirect::to('home');
        }
        return view('login.index');
    }
}