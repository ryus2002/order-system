<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderPageController extends Controller
{
    public function index()
    {
        return view('order-page');
    }
}