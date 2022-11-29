<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;

class PaymentController extends Controller
{
    public function fail(Request $request){
        Log::info("FAIL");

        Log::info($request);
        $inv_id = $request->InvId;
        if(isset($inv_id)){
            $transaction = Transaction::where('id', $inv_id)->first();
            $transaction->success = false;
            $transaction->save();
        }

        return redirect(config('app.urlfront').'/fail');


    }
    
    public function stripeSuccess(Request $request) {
        $session_id = $request->get('session_id');
        $inv_id = $request->get('inv_id');
        $stripe = new \Stripe\StripeClient('sk_test_51M4jjJJvZKDEPMw58ZRmpNdR2fYBkCRfkR6vyNka9tjbmgJrSFAXFV2hYCoqOZift18ReBb9ALuPcFrQEqlBR3AL00GPoFcD1K');
        try {
            $session = $stripe->checkout->sessions->retrieve($session_id);
            // проверка наличия номера счета в истории операций
            $transaction = Transaction::where('id', $inv_id)->first();
            if ($session && $transaction) {
                $transaction->success = false;
                $transaction->save();
                return redirect(config('app.urlfront').'/success');
            }

        } catch (Error $e) {
            return redirect(config('app.urlfront').'/fail');
        }

    }

    public function success(Request $request){
        // регистрационная информация (пароль #1)
        $mrh_pass1 = config('robokassa.testpass1');


        // чтение параметров
        // read parameters
        $out_summ = $request->OutSum;
        $inv_id = $request->InvId;
        $shp_item = $request->Shp_item;
        $crc = $request->SignatureValue;

        $crc = strtoupper($crc);


        $my_crc = strtoupper(md5("$out_summ:$inv_id:$mrh_pass1"));

        // проверка корректности подписи
        // check signature
        Log::info("SUCCESS");
        Log::info($request);
        if ($my_crc != $crc)
        {

            return redirect(config('app.urlfront').'/fail');

        }

        // проверка наличия номера счета в истории операций
        $transaction = Transaction::where('id', $inv_id)->where('success', true)->first();

        if($transaction){
            return redirect(config('app.urlfront').'/success');

        }

    }

    public function result(Request $request){
        // регистрационная информация (пароль #2)
        $mrh_pass2 = env('ROBOKASSA_TEST_PASS_2');
        Log::info("RESULT");
        Log::info($request);

        // чтение параметров
        // read parameters
        $out_summ = $request->OutSum;
        $inv_id = $request->InvId;
        $shp_item = $request->Shp_item;
        $crc = $request->SignatureValue;

        $crc = strtoupper($crc);

        $my_crc = strtoupper(md5("$out_summ:$inv_id:$mrh_pass2"));

        if($inv_id > 0){
            $transaction = Transaction::where('id', $inv_id)->first();
        }

        // проверка корректности подписи
        // check signature
        if ($my_crc !=$crc)
        {
            $transaction->success = false;
            $transaction->save();
            //return redirect(config('app.urlfront').'/fail');


        }
        // запись информации о проведенной операции
        $transaction->success = true;
        $transaction->save();

    }
}
