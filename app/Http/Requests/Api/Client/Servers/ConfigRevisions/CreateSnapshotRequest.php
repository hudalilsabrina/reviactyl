<?php

namespace App\Http\Requests\Api\Client\Servers\ConfigRevisions;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\Permission;

class CreateSnapshotRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_CONFIG_REVISION_CREATE;
    }

    public function rules(): array
    {
        return [
            'message' => 'nullable|string|max:500',
            'files' => 'nullable|array',
            'files.*' => 'string',
        ];
    }
}
