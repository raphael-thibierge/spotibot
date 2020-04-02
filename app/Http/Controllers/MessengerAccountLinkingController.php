<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class MessengerAccountLinkingController extends Controller
{
    /**
     * Display login form for messenger account linking
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function showMessengerLoginForm(Request $request)
    {
        if ($request->has('redirect_uri') && $request->has('account_linking_token')){
            if (($user = Auth::user()) !== null){
                return Redirect::to(route('botman.confirm.show', [
                    'redirect_uri' => $request->get('redirect_uri'),
                    'account_linking_token' => $request->get('account_linking_token'),
                ]));
            }
        }
        return view('auth.login');
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     */
    public function confirm(Request $request){

        $this->validate($request, [
            'redirect_uri' => 'required'
        ]);

        $postConfirmUser = Auth::user();
        if ($request->session()->has('messenger_id')){

            $senderId = $request->session()->get('messenger_id');

            $messagerSenderUser = BotManController::findOrCreateUser($senderId);

            // not same account
            if ($postConfirmUser->messenger_id !== $messagerSenderUser->messenger_id){
                $messagerSenderUser->merge($messagerSenderUser);
                $messagerSenderUser->save();
                $postConfirmUser->delete();
            }
        }
        //dd($request->get('redirect_uri'));
        return Redirect::to($request->get('redirect_uri') . '&authorization_code=' . $postConfirmUser->id);
    }

    /**
     * Display confirm account linking
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showConfirm(){
        return view('auth.messenger-confirmation');
    }
}