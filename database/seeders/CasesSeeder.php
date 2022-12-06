<?php

namespace Database\Seeders;

use App\Models\Cases;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class CasesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $case = new Cases([
            'id' => 1,
            'name' => 'Оплата кейса №1',
            'price' => 490,
            'site_number' => 1,
            'stripe_price_id' => 'price_1M6SxIJvZKDEPMw5sLkkm79T'
        ]);

        $case->save();

        $transactions = Transaction::all();

        foreach ($transactions as $transaction) {
            $transaction->case_id = $case->id;
            $transaction->save();
        }
    }
}
