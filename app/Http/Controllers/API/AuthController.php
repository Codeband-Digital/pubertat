<?php

namespace App\Http\Controllers\API;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;

use App\Models\Cases;
use App\Models\User;
use App\Services\GetresponseService;
use App\Services\RobokassaService;
use App\Services\StripeService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use \App\Models\EmailLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Telegram\Bot\Objects\Document;
use \App\Models\Transaction;


class AuthController extends Controller
{
    private $getresponseService;
    private $stripeService;
    private $robokassaService;

    public function __construct(
        GetresponseService $getresponseService,
        StripeService      $stripeService,
        RobokassaService   $robokassaService
    ) {
        $this->getresponseService = $getresponseService;
        $this->stripeService = $stripeService;
        $this->robokassaService = $robokassaService;
    }

    public function login(Request $request)
    {
        $paid = false;

        if ($request->email == "") {
            return response()->json([
                "status"  => false,
                "message" => "Не введен email",
                "data"    => [],
            ]);
        }

        if ($request->resend == "y" && $request->email != "") {
            $issetUserEmail = EmailLogin::where('email', $request->email)->first();
            if ($issetUserEmail) {
                $url = route('email-authenticate', [
                    'token' => $issetUserEmail->token,
                ]);

                Mail::send('emails.email-login', ['url' => $url], function ($m) use ($request) {
                    $m->from(config('mail.from.address'), config('app.title'));
                    $m->to($request->input('email'))->subject("Подтвердите почту");
                });
            }

            return response()->json([
                "status"  => true,
                "message" => "Проверьте почту",
                "data"    => [],
            ]);
        }

        //проверка на существование пользователя
        $user = \App\Models\User::where("email", $request->email)->first();
        if ($user) {
            $issetUserEmail = EmailLogin::where('email', $request->email)->first();
            $url = route('email-authenticate', [
                'token' => $issetUserEmail->token,
            ]);

            Mail::send('emails.email-login', ['url' => $url], function ($m) use ($request) {
                $m->from(config('mail.from.address'), config('app.title'));
                $m->to($request->input('email'))->subject("Подтвердите почту");
            });

            return response()->json([
                "status"  => true,
                "message" => "Пользователь найден",
                "data"    => [
                    "user_id"    => $user->id,
                    "paid"       => $paid,
                    "registered" => true,
                ],
            ]);

        }

        $newUser = new \App\Models\User();
        $newUser->email = $request->email;
        $newUser->name = $request->email;
        $newUser->password = \Illuminate\Support\Facades\Hash::make(Str::random(8));
        $newUser->save();

        $emailLogin = EmailLogin::createForEmail($request->email);
        $url = route('email-authenticate', [
            'token' => $emailLogin->token,
        ]);

        if ($newUser) {
            try {
                $this->getresponseService->createContact(
                    $newUser->email,
                    $this->getresponseService::CASE_1_LEAD_CAMPAIGN_ID,
                    $url
                );
            } catch (ApiException $apiException) {
                Log::error('Error on getresponse create contact (Api exception): ' . $apiException->getMessage());
            } catch (GuzzleException $guzzleException) {
                Log::error('Error on getresponse create contact (Guzzle exception): ' . $guzzleException->getMessage());
            }

            $userId = $newUser->id;
        }


        return response()->json([
            "status"  => true,
            "message" => "Пользователь создан. Проверьте почту",
            "data"    => [
                "user_id"    => $userId,
                "registered" => false,
                "paid"       => false,
            ],
        ]);

    }

    public function getPaymentLinkAuth(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(
                ["status" => false, "message" => "Not Authed request!"],
                401
            );
        }

        $caseId = $request->get('case_id');

        if (!$caseId) {
            $caseId = 1;
        }

        $caseId = (int) $caseId;

        $paidTransaction = false;
        $auth = true;
        $user = Auth::user();
        $transaction = Transaction::where('user_id', $user->id)->where('case_id', $caseId)->first();

        if (!$transaction) {
            $transaction = new Transaction;
            $transaction->out_sum = CASE_PRICE;
            $transaction->user_id = $user->id;
            $transaction->case_id = $caseId;
            $transaction->save();
        }

        if ($transaction->success) {
            $paidTransaction = true;
        }

        $case = $transaction->case;

        $robokassaPaymentUrl = $this->robokassaService->getPaymentUrl(
            (string) $transaction->id,
            (string) $case->price,
            $case->name
        );

        $stripePaymentUrl = "";

        try {
            $stripePaymentUrl = $this->stripeService->getPaymentUrl(
                (string) $transaction->id,
                $case->stripe_price_id
            );
        } catch (\Exception $e) {
            Log::error('Error on stripe get link (Exception): ' . $e->getMessage());
        }

        return response()->json([
            "status"  => true,
            "message" => "",
            "data"    => [
                "user"         => $user,
                "auth"         => $auth,
                "paid"         => $paidTransaction,
                "price"        => $case->price,
                "payment_urls" => [
                    "robokassa" => $robokassaPaymentUrl,
                    "stripe"    => $stripePaymentUrl,
                ],
            ],
        ]);
    }

    public function getPaymentLinks(Request $request)
    {
        $caseId = $request->get('case_id');
        $email = $request->get('email');

        if (!$email) {
            return response()->json(
                ["status" => false, "message" => "Email not found in request"],
                400
            );
        }

        if (!$caseId) {
            $caseId = 1;
        }

        $caseId = (int) $caseId;

        $paidTransaction = false;
        $auth = false;
        $user = User::query()->where('email', '=', $email)->first();

        if (!$user) {
            return response()->json(
                ["status" => false, "message" => "User with $email email not found!"],
                400
            );
        }

        $transaction = Transaction::where('user_id', $user->id)->where('case_id', $caseId)->first();

        if (!$transaction) {
            $transaction = new Transaction;
            $transaction->out_sum = CASE_PRICE;
            $transaction->user_id = $user->id;
            $transaction->case_id = $caseId;
            $transaction->save();
        }

        if ($transaction->success) {
            $paidTransaction = true;
        }

        $case = $transaction->case;

        $robokassaPaymentUrl = $this->robokassaService->getPaymentUrl(
            (string) $transaction->id,
            (string) $case->price,
            $case->name
        );

        $stripePaymentUrl = "";

        try {
            $stripePaymentUrl = $this->stripeService->getPaymentUrl(
                (string) $transaction->id,
                $case->stripe_price_id
            );
        } catch (\Exception $e) {
            Log::error('Error on stripe get link (Exception): ' . $e->getMessage());
        }

        return response()->json([
            "status"  => true,
            "message" => "",
            "data"    => [
                "user"         => $user,
                "auth"         => $auth,
                "paid"         => $paidTransaction,
                "price"        => $case->price,
                "payment_urls" => [
                    "robokassa" => $robokassaPaymentUrl,
                    "stripe"    => $stripePaymentUrl,
                ],
            ],
        ]);
    }

    public function getAuth()
    {
        $price = CASE_PRICE;
        $auth = false;
        $user = null;
        $firstPaidTransaction = false;
        $cases = [];
        $user = Auth::user();
        if (Auth::check()) {
            $auth = true;
            $user = Auth::user();

            $firstTransaction = Transaction::where('user_id', $user->id)->where('case_id', 1)->first();
            if ($firstTransaction) {
                if ($firstTransaction->success) {
                    $firstPaidTransaction = true;
                }
            }

            $dbCases = Cases::all();

            foreach ($dbCases as $dbCase) {
                $case = [
                    'id' => $dbCase->id,
                    'number' => $dbCase->site_number,
                    'price' => $dbCase->price,
                    'paid' => false,
                ];
                $transaction = Transaction::where('user_id', $user->id)->where('case_id', $dbCase->id)->first();

                if ($transaction) {
                    if ($transaction->success) {
                        $case['paid'] = true;
                    }
                }

                $cases[] = $case;
            }
        }

        return response()->json([
            "status"  => true,
            "message" => "",
            "data"    => [
                "user"  => $user,
                "auth"  => $auth,
                "paid"  => $firstPaidTransaction,
                "price" => $price,
                "cases" => $cases
            ],
        ]);
    }

    public function logout()
    {
        dd("2");
        Auth::logout();
    }


}
