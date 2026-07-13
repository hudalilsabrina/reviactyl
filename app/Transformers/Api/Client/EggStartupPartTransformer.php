<?php

namespace App\Transformers\Api\Client;

use App\Models\EggStartupPart;

class EggStartupPartTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return EggStartupPart::RESOURCE_NAME;
    }

    public function transform(EggStartupPart $part): array
    {
        return [
            'id' => $part->id,
            'name' => $part->name,
            'value' => $part->value,
            'description' => $part->description,
            'default_enabled' => $part->default_enabled,
            'required' => $part->required,
            'sort_order' => $part->sort_order,
            'group_name' => $part->group_name,
        ];
    }
}
