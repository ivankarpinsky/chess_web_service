<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GameRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
//            'token' => 'required|string|unique:games,title',
//            'description' => '',
//            'complexity' => 'required|min:1|max:10',
//            'minPlayers' => 'required|min:1|max:10',
//            'maxPlayers' => 'required|min:1|max:10',
//            'isActive' => 'required|boolean'
        ];
    }

    public function messages()
    {
        return [
//            'date.required' => 'A date is required',
//            'date.date_format'  => 'A date must be in format: Y-m-d',
//            'date.unique'  => 'This date is already taken',
//            'date.after_or_equal'  => 'A date must be after or equal today',
//            'date.exists'  => 'This date doesn\'t exists',
        ];
    }
}
