<?php

namespace App\Http\Controllers;

class RouteController extends Controller
{
    public function index()
    {
        $routes = [
            [
                'uri' => 'account-info',
                'title' => 'Coincall get user information',
                'description' => 'Get User Info(SIGNED)',
            ],
            [
                'uri' => 'summary-info',
                'title' => 'Coincall get account summary',
                'description' => 'Get Account Summary(SIGNED)',
            ],
        ];

        return view('routes.index', ['routes' => $routes]);
    }
}
