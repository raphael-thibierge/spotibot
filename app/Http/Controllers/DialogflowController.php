<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DialogflowController extends Controller
{

    public function handleWebhookRequest(Request $request){
        // todo process request
        // check Edsound

        $data = [
            'status' => 'success'
        ];

        return response()->json($data);
    }

}
