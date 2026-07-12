<?php

namespace App\Transformers\Api\Client;

use App\Models\SocialLogin;

class SocialLoginTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return 'social_login';
    }

    public function transform(SocialLogin $model): array
    {
        return [
            'id' => $model->id,
            'provider' => $model->provider,
            'created_at' => $model->created_at->toAtomString(),
            'updated_at' => $model->updated_at->toAtomString(),
        ];
    }
}
