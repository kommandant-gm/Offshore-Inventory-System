<?php
namespace App\Http\Requests;

use App\Support\PaintFields;
use Illuminate\Foundation\Http\FormRequest;

class SavePaintRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->canEdit('assets') ?? false; }
    public function rules(): array { return [...PaintFields::rules(), 'company' => ['nullable', 'in:DESB,FTSB'], 'stock_token' => ['nullable', 'string', 'size:64']]; }
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $manufacture = $this->input('manufacture_date');
            $bestBefore = $this->input('best_before_date');
            if ($this->input('date_status') === 'confirmed') {
                if (! $manufacture && ! $bestBefore) $validator->errors()->add('date_status', 'Enter at least one confirmed date.');
                if ($manufacture && $bestBefore && $bestBefore < $manufacture) $validator->errors()->add('best_before_date', 'Best before cannot be earlier than manufacture.');
            } elseif ($manufacture || $bestBefore) {
                $validator->errors()->add('date_status', 'Confirm the date meaning before assigning manufacture or best-before dates.');
            }
        });
    }
}
