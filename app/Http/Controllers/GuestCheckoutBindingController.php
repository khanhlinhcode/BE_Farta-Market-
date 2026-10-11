<?php

namespace App\Http\Controllers;

use App\Services\GuestCheckoutBindingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class GuestCheckoutBindingController extends Controller
{
    public function __invoke(Request $request, GuestCheckoutBindingService $bindings): Response
    {
        $currentToken = $request->cookie(GuestCheckoutBindingService::COOKIE);
        if ($bindings->verify($currentToken) !== null) {
            return response()->noContent();
        }

        $issued = $bindings->rotate($currentToken);

        return response()->noContent(201)->withCookie($bindings->cookie($issued['token']));
    }
}
