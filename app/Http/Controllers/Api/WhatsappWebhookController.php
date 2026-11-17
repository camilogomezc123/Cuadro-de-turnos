<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        if ($request->header('X-Webhook-Secret') !== config('services.evolution.webhook_secret')) {
            return response()->noContent(401);
        }

        Log::info('WhatsApp webhook (evolution) recibido', [
            'event'    => $request->input('event'),
            'instance' => $request->input('instance'),
        ]);

        return response()->noContent();
    }
}
