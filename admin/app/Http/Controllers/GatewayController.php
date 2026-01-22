<?php

namespace App\Http\Controllers;

class GatewayController extends Controller
{
    public function index()
    {

        return response()->json([
            'message' => 'Gateway is working',
            'code'    => 0
        ]);
    }
}
