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
        $author = $revision->relationLoaded('author') ? $revision->author : null;

        return [
            'id' => $revision->id,
            'hash' => $revision->hash,
            'message' => $revision->message,
            'author' => $author ? [
                'uuid' => $author->uuid,
                'username' => $author->username,
            ] : [
                'uuid' => null,
                'username' => 'Deleted User',
            ],
            'file_count' => $revision->file_count,
            'is_preset' => $revision->is_preset,
            'preset_name' => $revision->preset_name,
            'files' => $revision->relationLoaded('files')
                ? $revision->files->pluck('file_path')->toArray()
                : [],
            'created_at' => $revision->created_at?->toAtomString(),
        ];
    }
}
