<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cases;

class CasesController extends Controller
{
    public function getAll()
    {
        $dbCases = Cases::all();
        $cases = [];

        foreach ($dbCases as $dbCase) {
            $cases[] = [
                'id' => $dbCase->id,
                'number' => $dbCase->site_number,
                'price' => $dbCase->price,
            ];
        }

        return response()->json([
            "status"  => true,
            "message" => "",
            "data"    => [
                "cases" => $cases
            ],
        ]);
    }
}
