<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    $response = Http::get(env("API_URL"), ["userId" => env("USER_ID")]);
    if($response->status() == 200){
        $dt = $response->json();
        dd($dt);
        if($dt){
            $store_name = $dt["store_name"];
            $store_address = $dt["store_address"];
            $store_phone = $dt["store_address"];
            $store_email = $dt["store_address"];
            $store_website = $dt["store_address"];
            $currency = $dt["currency"];
        }
    }
    // $ch = curl_init();
    // // Will return the response, if false it print the response
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // // Set the url
    // $url = env("API_URL") . "?userId=" . env("USER_ID");
    // curl_setopt($ch, CURLOPT_URL,$url);
    // // Execute
    // $result=curl_exec($ch);
    // // Closing
    // curl_close($ch);
    // dd($result);

    // // // Will dump a beauty json :3
    // // echo $url;
    // // var_dump(json_decode($result, true));
    return '';
});
