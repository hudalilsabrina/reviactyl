<?php

namespace App\Http\Requests\Api\Client\Servers\ConfigRevisions;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\Permission;

class RevertRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_CONFIG_REVISION_REVERT;
    }

    public function rules(): array
    {
        return [
            'files' => 'nullable|array',
            'files.*' => 'string',
            'message' => 'nullable|string|max:500',
        ];
    }
}
