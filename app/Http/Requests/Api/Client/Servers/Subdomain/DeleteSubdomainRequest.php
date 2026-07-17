<?php

namespace App\Http\Requests\Api\Client\Servers\Subdomain;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\Permission;

class DeleteSubdomainRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_SETTINGS_SUBDOMAIN;
    }
}
