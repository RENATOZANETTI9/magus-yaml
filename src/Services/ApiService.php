<?php

namespace Magus\Yaml\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ApiService
{
    public static function sendRequest(array $request)
    {
        // dump('sendRequest');
        //  dump($request);
        
        try {
            $headers = isset($request['headers']) ? $request['headers'] : [];
            // echo json_encode($request);
            // Obtém o array JSON da requisição
            // $requestData = $request->json()->all();
            // Realiza a chamada à API usando o cliente HTTP do Laravel
            if($request['method']=='POST') { 
                $response = Http::withHeaders(
                    $headers
                )->post($request['url'], $request['body']);
            } else if($request['method']=='GET') {
                $response = Http::withHeaders(
                    $headers
                )->get($request['url']);
            }
            // if($request['url']=='http://localhost/create_or_update_table') {
            //     // dump($request['body']);
            //     dump('REQUEST:');
            //     dump($request);
            //     dump('RESPONSE:');
            //     dd($response->json());
            // }
            // Retorna a resposta da API
            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            // Em caso de erro, retorna uma resposta de erro
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
