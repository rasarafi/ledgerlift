<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('hands POSTed empty strings and untrimmed values to the controller as sent', function () {
    // CodeIgniter neither trims nor nulls input, and legacy stores '' and padded values.
    Route::middleware('web')->post('/probe/input', fn (Request $request) => response()->json([
        'empty' => $request->input('empty'),
        'padded' => $request->input('padded'),
        'nested' => $request->input('nested'),
    ]));

    $this->post('/probe/input', ['empty' => '', 'padded' => '  x  ', 'nested' => ['k' => ' ', 'e' => '']])
        ->assertExactJson(['empty' => '', 'padded' => '  x  ', 'nested' => ['k' => ' ', 'e' => '']]);
});
