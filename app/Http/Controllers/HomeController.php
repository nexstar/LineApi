<?php

namespace App\Http\Controllers;

use App\BotUserProfile;
use App\Order;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $botUserProfiles = BotUserProfile::all();
        $orders = Order::all();

        return view('home', compact('botUserProfiles', 'orders'));
    }
}
