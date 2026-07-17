<?php

namespace App\Transformers\Api\Client;

use App\Models\ServerConfigRevision;

class ConfigRevisionTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return ServerConfigRevision::RESOURCE_NAME;
    }

    public function transform(ServerConfigRevision $revision): array
    {
        return [
            'id' => $revision->id,
            'hash' => $revision->hash,
            'message' => $revision->message,
            'author' => [
                'uuid' => $revision->author->uuid,
                'username' => $revision->author->username,
            ],
            'file_count' => $revision->file_count,
            'is_preset' => $revision->is_preset,
            'preset_name' => $revision->preset_name,
            'files' => $revision->files->pluck('file_path')->toArray(),
            'created_at' => $revision->created_at->toAtomString(),
        ];
    }
}
