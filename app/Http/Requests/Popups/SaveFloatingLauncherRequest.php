<?php

namespace App\Http\Requests\Popups;

use App\Models\Site;
use App\Popups\FloatingLauncher;
use App\Support\DesignerScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class SaveFloatingLauncherRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(DesignerScope::class)->site($this->site());

        return Gate::allows('editPopups', $this->site());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'popup' => ['nullable', 'string', 'size:26'],
            'desktop' => ['nullable', 'array'],
            'mobile' => ['nullable', 'array'],
            'dialog' => ['nullable', 'array'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            FloatingLauncher::validate($validator, $this->all(), $this->site());
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function launcher(): array
    {
        $normalized = FloatingLauncher::tryNormalize($this->all());

        return $normalized ?? FloatingLauncher::defaults();
    }

    public function site(): Site
    {
        $site = $this->route('site');
        abort_unless($site instanceof Site, 404);

        return $site;
    }
}
