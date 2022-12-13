<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Services\GetresponseService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;

class PaymentController extends Controller
{
    private $getresponseService;

    public function __construct(GetresponseService $getresponseService)
    {
        $this->getresponseService = $getresponseService;
    }

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
        $stripeKey = config('payments.stripe.api_key');

        try {
            $stripe = new \Stripe\StripeClient($stripeKey);
            $session = $stripe->checkout->sessions->retrieve($session_id);
            // проверка наличия номера счета в истории операций
            $transaction = Transaction::where('id', $inv_id)->first();
            if ($session && $transaction) {
                $transaction->success = true;
                $transaction->save();
            } else {
                return redirect(config('app.urlfront').'/fail');
            }
        } catch (\Exception $e) {
            Log::error('Error on stripe success (Exception): ' . $e->getMessage());

            return redirect(config('app.urlfront').'/fail');
        }

        $transactions = Transaction::where('user_id', $transaction->user_id)->get();
        $cases = [];

        foreach ($transactions as $caseTransaction) {
            $cases[] = "Кейс №" . $caseTransaction->case->site_number;
        }

        $caseString = implode(", ", $cases);

        try {
            $this->getresponseService->updateContactCampaignByEmail(
                $transaction->user->email,
                $this->getresponseService::CASE_1_SOLD_CAMPAIGN_ID,
                $caseString
            );
        } catch (ApiException $apiException) {
            Log::error('Error on getresponse create contact (Api exception): ' . $apiException->getMessage());
        } catch (GuzzleException $guzzleException) {
            Log::error('Error on getresponse create contact (Guzzle exception): ' . $guzzleException->getMessage());
        } catch (\Exception $e) {
            Log::error('Error on getresponse update contact (Exception): ' . $e->getMessage());
        }

        return redirect(config('app.urlfront').'/success');
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

        $transactions = Transaction::where('user_id', $transaction->user_id)->get();
        $cases = [];

        foreach ($transactions as $caseTransaction) {
            $cases[] = "Кейс №" . $caseTransaction->case->site_number;
        }

        $caseString = implode(", ", $cases);

        try {
            $this->getresponseService->updateContactCampaignByEmail(
                $transaction->user->email,
                $this->getresponseService::CASE_1_SOLD_CAMPAIGN_ID,
                $caseString
            );
        } catch (ApiException $apiException) {
            Log::error('Error on getresponse update contact (Api exception): ' . $apiException->getMessage());
        } catch (GuzzleException $guzzleException) {
            Log::error('Error on getresponse update contact (Guzzle exception): ' . $guzzleException->getMessage());
        } catch (\Exception $e) {
            Log::error('Error on getresponse update contact (Exception): ' . $e->getMessage());
        }
    }
}
