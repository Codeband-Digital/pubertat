<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use \App\Models\EmailLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Telegram\Bot\Objects\Document;
use \App\Models\Transaction;


class AuthController extends Controller
{
    public function login(Request $request)
    {
        $paid = false;
        $paymentSystem = $request->payment_system;
        if (!$paymentSystem) {
            $paymentSystem = 'robokassa';
        }
        if($request->email == ""){
            return response()->json([
                "status" => false,
                "message" => "Не введен email",
                "data" => []
            ]);
        }
        if($request->resend == "y" && $request->email != ""){
            $issetUserEmail = EmailLogin::where('email', $request->email)->first();
            if($issetUserEmail){
                $url = route('email-authenticate', [
                    'token' => $issetUserEmail->token
                ]);

                Mail::send('emails.email-login', ['url' => $url], function ($m) use ($request) {
                    $m->from(config('mail.from.address'),  config('app.title'));
                    $m->to($request->input('email'))->subject("Ссылка для входа в кейс");
                });
            }

            return response()->json([
                "status" => true,
                "message" => "Проверьте почту",
                "data" => []
            ]);
        }

        //проверка на существование пользователя
        $user = \App\Models\User::where("email", $request->email)->first();
        if($user){
            // поиск заказа пользователя
            $transaction = Transaction::where('user_id', $user->id)->first();
            if($transaction){
                $inv_id = $transaction->id;
            }else{
                //создание заказа
                $newTransaction = new Transaction;
                $newTransaction->out_sum = CASE_PRICE;
                $newTransaction->user_id = $user->id;
                $newTransaction->save();
                $inv_id = $newTransaction->id;

            }


            //создание ссылки на оплату
            // TODO: вынести в хелпер
            $mrh_login = config('robokassa.login');      // your login here
            $mrh_pass1 = config('robokassa.testpass1');   // merchant pass1 here

            // order properties
            $inv_desc  = "Оплата кейса №1";    // invoice desc
            $out_summ  = CASE_PRICE;    // invoice summ
            // build CRC value
            $crc  = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1");
            // build URL
            $urlPayment =
                "https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=$mrh_login&".
                "OutSum=$out_summ&InvId=$inv_id&Description=$inv_desc&SignatureValue=$crc";


            $stripe = new \Stripe\StripeClient(
                'sk_test_51M4jjJJvZKDEPMw58ZRmpNdR2fYBkCRfkR6vyNka9tjbmgJrSFAXFV2hYCoqOZift18ReBb9ALuPcFrQEqlBR3AL00GPoFcD1K'
            );
            $stripeSession = $stripe->checkout->sessions->create([
                'success_url' => 'https://api.pbrtt.ru/stripeSuccess?session_id={CHECKOUT_SESSION_ID}&inv_id=' . $inv_id,
                'cancel_url' => 'https://api.pbrtt.ru/fail',
                'line_items' => [
                    [
                        'price' => 'price_1M6SxIJvZKDEPMw5sLkkm79T',
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
            ]);
            $urlPaymentStripe = $stripeSession->url;



            /*Log::info("login".$mrh_login);
            Log::info("pass1".$mrh_pass1);
            Log::info("invid".$inv_id);
            Log::info("outsum".$out_summ);*/

            // проверка наличия номера счета в истории операций
            if($transaction){
                if($transaction->success)
                    $paid = true;
            }

            $issetUserEmail = EmailLogin::where('email', $request->email)->first();
            $url = route('email-authenticate', [
                'token' => $issetUserEmail->token
            ]);

            Mail::send('emails.email-login', ['url' => $url], function ($m) use ($request) {
                $m->from(config('mail.from.address'),  config('app.title'));
                $m->to($request->input('email'))->subject("Ссылка для входа в кейс");
            });

            return response()->json([
                "status" => true,
                "message" => "Пользователь найден",
                "data" => [
                    "user_id" => $user->id,
                    "payment_link" => $urlPayment,
                    "paid" => $paid,
                    "registered" => true,
                    "payment_urls" => [
                        'robokassa' => $urlPayment,
                        'stripe' => $urlPaymentStripe
                    ]
                ]
            ]);

        }else{
            $newUser = new \App\Models\User();
            $newUser->email = $request->email;
            $newUser->name = $request->email;
            $newUser->password = \Illuminate\Support\Facades\Hash::make(Str::random(8));
            $newUser->save();

            $inv_id = "";
            if($newUser){
                $userId = $newUser->id;
                //создание заказа
                $newTransaction = new Transaction;
                $newTransaction->out_sum = CASE_PRICE;
                $newTransaction->user_id = $newUser->id;
                $newTransaction->save();
                $inv_id = $newTransaction->id;
            }


            //создаем авторизационный хеш к email и отправляем письмо-подтверждение авторизации
            $emailLogin = EmailLogin::createForEmail($request->email);
            $url = route('email-authenticate', [
                'token' => $emailLogin->token
            ]);

            Mail::send('emails.email-login', ['url' => $url], function ($m) use ($request) {
                $m->from(config('mail.from.address'),  config('app.title'));
                $m->to($request->input('email'))->subject(config('app.title'));
            });


            //создание ссылки на оплату
            // TODO: вынести в хелпер
            $mrh_login = config('robokassa.login');      // your login here
            $mrh_pass1 = config('robokassa.testpass1');   // merchant pass1 here
            $inv_desc  = "Оплата кейса №1";    // invoice desc
            $out_summ  = CASE_PRICE;
            // build CRC value
            $crc  = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1");
            // build URL
            $urlPayment =
                "https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=$mrh_login&".
                "OutSum=$out_summ&InvId=$inv_id&Description=$inv_desc&SignatureValue=$crc";

            $stripe = new \Stripe\StripeClient(
                'sk_test_51M4jjJJvZKDEPMw58ZRmpNdR2fYBkCRfkR6vyNka9tjbmgJrSFAXFV2hYCoqOZift18ReBb9ALuPcFrQEqlBR3AL00GPoFcD1K'
            );
            $stripeSession = $stripe->checkout->sessions->create([
                'success_url' => 'https://api.pbrtt.ru/stripeSuccess?session_id={CHECKOUT_SESSION_ID}&inv_id=' . $inv_id,
                'cancel_url' => 'https://api.pbrtt.ru/fail',
                'line_items' => [
                    [
                        'price' => 'price_1M6SxIJvZKDEPMw5sLkkm79T',
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
            ]);
            $urlPaymentStripe = $stripeSession->url;


            return response()->json([
                "status" => true,
                "message" => "Пользователь создан. Проверьте почту",
                "data" => [
                    "user_id" => $userId,
                    "payment_link" => $urlPayment,
                    "registered" => false,
                    "paid" => false,
                    "payment_urls" => [
                        'robokassa' => $urlPayment,
                        'stripe' => $urlPaymentStripe
                    ]
                ]
            ]);


        }

    }

    public function getPaymentLinks(){
        $price = CASE_PRICE;
        $auth = false;
        $user = null;
        $paidTransaction = false;
        $user = Auth::user();
        if (Auth::check()) {
            $auth = true;
            $user = Auth::user();
            $transaction = Transaction::where('user_id', $user->id)->first();
            if($transaction){
                if($transaction->success){
                    $paidTransaction = true;
                }
                $inv_id = $transaction->id;

                //создание ссылки на оплату
                // TODO: вынести в хелпер
                $mrh_login = config('robokassa.login');      // your login here
                $mrh_pass1 = config('robokassa.testpass1');   // merchant pass1 here

                // order properties
                $inv_desc  = "Оплата кейса №1";    // invoice desc
                $out_summ  = CASE_PRICE;    // invoice summ
                // build CRC value
                $crc  = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1");
                // build URL
                $urlPayment =
                    "https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=$mrh_login&".
                    "OutSum=$out_summ&InvId=$inv_id&Description=$inv_desc&SignatureValue=$crc";


                $stripe = new \Stripe\StripeClient(
                    'sk_test_51M4jjJJvZKDEPMw58ZRmpNdR2fYBkCRfkR6vyNka9tjbmgJrSFAXFV2hYCoqOZift18ReBb9ALuPcFrQEqlBR3AL00GPoFcD1K'
                );
                $stripeSession = $stripe->checkout->sessions->create([
                    'success_url' => 'https://api.pbrtt.ru/stripeSuccess?session_id={CHECKOUT_SESSION_ID}&inv_id=' . $inv_id,
                    'cancel_url' => 'https://api.pbrtt.ru/fail',
                    'line_items' => [
                        [
                            'price' => 'price_1M6SxIJvZKDEPMw5sLkkm79T',
                            'quantity' => 1,
                        ],
                    ],
                    'mode' => 'payment',
                ]);
                $urlPaymentStripe = $stripeSession->url;
            }
        }

        return response()->json([
            "status" => true,
            "message" => "",
            "data" => [
                "user" => $user,
                "auth" => $auth,
                "paid"  =>  $paidTransaction,
                "price" => $price,
                "payment_urls" => [
                    "robokassa" => $urlPayment,
                    "stripe" => $urlPaymentStripe
                ]
            ]
        ]);
    }

    public function getAuth(){
        $price = CASE_PRICE;
        $auth = false;
        $user = null;
        $paidTransaction = false;
        $user = Auth::user();
        if (Auth::check()) {
            $auth = true;
            $user = Auth::user();
            $transaction = Transaction::where('user_id', $user->id)->first();
            if($transaction){
                if($transaction->success){
                    $paidTransaction = true;
                }
            }
        }

        return response()->json([
            "status" => true,
            "message" => "",
            "data" => [
                "user" => $user,
                "auth" => $auth,
                "paid"  =>  $paidTransaction,
                "price" => $price
            ]
        ]);
    }

    public function logout(){
        dd("2");
        Auth::logout();
    }



}
