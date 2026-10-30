<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

/**
 * The rules of one legacy model's validation_rules(): `rules()` has its keys (the only POST
 * keys MY_Model::db_array() persists) and `attributes()` the labels CI form_validation puts in
 * its messages.
 *
 * Legacy validates inside the controller, after the cancel button and the duplicate check, and
 * answers a failure by rendering the form again, so nothing validates when this is resolved:
 * the controller asks for `legacyValidator()` itself.
 */
abstract class LegacyFormRequest extends FormRequest
{
    /** @return array<string, string> */
    abstract public function rules(): array;

    public function validateResolved(): void {}

    public function authorize(): bool
    {
        return true;
    }

    public function legacyValidator(): ValidatorContract
    {
        // An array where a field is expected counts as an empty field in CI.
        $data = array_map(fn (mixed $value) => is_array($value) ? null : $value, $this->post());

        return Validator::make($data, $this->rules(), ['required' => 'The :attribute field is required.'], $this->attributes());
    }
}
