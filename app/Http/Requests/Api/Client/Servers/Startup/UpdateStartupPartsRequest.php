<?php

namespace App\Http\Requests\Api\Client\Servers\Startup;

use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\Permission;

class UpdateStartupPartsRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_STARTUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            'parts' => 'required|array|max:50',
            'parts.*.part_id' => 'required|integer',
            'parts.*.enabled' => 'required|boolean',
        ];
    }
}
