<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Models\Role;
use App\Modules\Property\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'property_id' => ['required', 'string', Rule::exists('properties', 'public_id')->where('organization_id', $this->user()->organization_id)->whereNull('deleted_at')],
            'role' => ['required', 'string', Rule::in($this->assignableRoleNames())],
        ];
    }

    public function property(): Property
    {
        return Property::where('public_id', $this->validated('property_id'))->firstOrFail();
    }

    public function role(): Role
    {
        return Role::query()->visibleTo((int) $this->user()->organization_id)->where('name', $this->validated('role'))->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function assignableRoleNames(): array
    {
        return Role::query()->visibleTo((int) $this->user()->organization_id)
            ->where('name', '!=', RoleEnum::SuperAdmin->value)
            ->pluck('name')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['role.in' => 'Choose one of the available roles.'];
    }
}
