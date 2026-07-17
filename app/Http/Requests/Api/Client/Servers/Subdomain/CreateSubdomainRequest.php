<?php

namespace App\Http\Requests\Api\Client\Servers\Subdomain;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\Permission;

class CreateSubdomainRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_SETTINGS_SUBDOMAIN;
    }

    public function rules(): array
    {
        return [
            'subdomain' => 'required|string|max:63|regex:/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/',
            'domain' => 'nullable|string|max:255',
        ];
    }
}
