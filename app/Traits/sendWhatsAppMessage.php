<?php

namespace App\Traits;
use GuzzleHttp\Client;

trait sendWhatsAppMessage
{
    public function sendWhatsAppMessage($to, $message)
    {
        //online kQtjl1dW4t0kOZoW24AjWVRpmHJRHwCj
        //test C8WRCwascAAetTbJo3EZGHRlxbyKey9d
        $client = new Client();
        $apiKey = env('WHAPI_API_KEY');

        $response = $client->post('https://gate.whapi.cloud/messages/text', [
            'headers' => [
                'accept' => 'application/json',
                'authorization' => 'Bearer kQtjl1dW4t0kOZoW24AjWVRpmHJRHwCj',
                'content-type' => 'application/json',
            ],
            'json' => [
                'typing_time' => 0,
                'body' => $message,
                'to' => '964' . $to,
            ],
        ]);

        return response()->json([
            'status' => $response->getStatusCode(),
            'body' => json_decode($response->getBody()->getContents())
        ]);
    }

    public function sendWhatsAppDocument($to, $filename,$pdfBase64)
    {
        $client = new Client();
        $apiKey = env('WHAPI_API_KEY');

        $requestBody = json_encode([
            'to' => '964' . $to,
            'media' => 'data:application/pdf;base64,' . $pdfBase64,
            'filename' => $filename,
        ]);

        $response = $client->post('https://gate.whapi.cloud/messages/document', [
            'headers' => [
                'accept' => 'application/json',
                'authorization' => 'Bearer kQtjl1dW4t0kOZoW24AjWVRpmHJRHwCj',
                'content-type' => 'application/json',
            ],

            'body' => $requestBody,
        ]);

        return response()->json([
            'sent' => $response->getStatusCode() === 200, // or based on actual API response
            'body' => json_decode($response->getBody()->getContents())
        ]);
    }
}
