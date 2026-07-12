<?php

namespace App\Transformers\Api\Application;

use App\Exceptions\Transformer\InvalidTransformerLevelException;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Contracts\Encryption\Encrypter;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\NullResource;

class ServerDatabaseTransformer extends BaseTransformer
{
    protected array $availableIncludes = ['password', 'host'];

    private Encrypter $encrypter;

    /**
     * Perform dependency injection.
     */
    public function handle(Encrypter $encrypter)
    {
        $this->encrypter = $encrypter;
    }

    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return Database::RESOURCE_NAME;
    }

    /**
     * Transform a database model in a representation for the application API.
     */
    public function transform(Database $model): array
    {
        return [
            'id' => $model->id,
            'server' => $model->server_id,
            'host' => $model->database_host_id,
            'database' => $model->database,
            'username' => $model->username,
            'remote' => $model->remote,
            'max_connections' => $model->max_connections,
            'created_at' => $model->created_at->toAtomString(),
            'updated_at' => $model->updated_at->toAtomString(),
        ];
    }

    /**
     * Include the database password in the request.
     */
    public function includePassword(Database $model): Item
    {
        return $this->item($model, function (Database $model) {
            return [
                'password' => $this->encrypter->decrypt($model->password),
            ];
        }, 'database_password');
    }

    /**
     * Return the database host relationship for this server database.
     *
     * @throws InvalidTransformerLevelException
     */
    public function includeHost(Database $model): Item|NullResource
    {
        if (! $this->authorize(AdminAcl::RESOURCE_DATABASE_HOSTS)) {
            return $this->null();
        }

        $model->loadMissing('host');

        return $this->item(
            $model->getRelation('host'),
            $this->makeTransformer(DatabaseHostTransformer::class),
            DatabaseHost::RESOURCE_NAME
        );
    }
}
