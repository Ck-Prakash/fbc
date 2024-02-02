<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(){
        return view("firebase"); 
    }

    public function sendNotification(){

        $token = "cSNPdBjjHr8:APA91bHLERgwAo_cnosmqbC_lPs1qs-bB2awfQwdsDoeF0zSYPgKPFcGEDamS-jLqSkO7Y-bGviJkwpXTD9DbfUOhwGe34pkcICEQR03s9KHR-9dxfe68C7BTTisfd2e9L3Cs9e7Gesc";  
        $from = "AAAApxhsRkc:APA91bHK9S3FgJabgpt8382hhu__M-TvNgj-srp3oiFfYPHd0qxQcpsyt-hOeezLSv2gl8TjC6J9L8iohxVDAopUydeDWlVAVJSIyX2Y5vWG6ea1iXnZadtnP1XYt8Ej9HMFk_eKjHjB";
        $msg = array
              (
                'body'  => "demo",
                'title' => "Hi, From prakash",
                'receiver' => 'erw',
                'sound' => 'mySound'/*Default sound*/
              );

        $fields = array
                (
                    'to'        => $token,
                    'notification'  => $msg
                );

        $headers = array
                (
                    'Authorization: key=' . $from,
                    'Content-Type: application/json'
                );
        //#Send Reponse To FireBase Server 
        $ch = curl_init();
        curl_setopt( $ch,CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send' );
        curl_setopt( $ch,CURLOPT_POST, true );
        curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
        $result = curl_exec($ch );
        curl_close( $ch );
        // dd($result);
        
    }

}
