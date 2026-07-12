<?php

namespace App\Models;

use Database\Factories\UserSSHKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * \App\Models\UserSSHKey.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $fingerprint
 * @property string $public_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|UserSSHKey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserSSHKey newQuery()
 * @method static \Illuminate\Database\Query\Builder|UserSSHKey onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserSSHKey query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserSSHKey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserSSHKey whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserSSHKey whereFingerprint($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserSSHKey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserSSHKey whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserSSHKey wherePublicKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserSSHKey whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserSSHKey whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|UserSSHKey withTrashed()
 * @method static \Illuminate\Database\Query\Builder|UserSSHKey withoutTrashed()
 * @method static \Database\Factories\UserSSHKeyFactory factory(...$parameters)
 *
 * @mixin \Eloquent
 */
class UserSSHKey extends Model
{
    /** @use HasFactory<UserSSHKeyFactory> */
    use HasFactory;

    use SoftDeletes;

    public const RESOURCE_NAME = 'ssh_key';

    protected $table = 'user_ssh_keys';

    protected $fillable = [
        'name',
        'public_key',
        'fingerprint',
    ];

    public static array $validationRules = [
        'name' => ['required', 'string'],
        'fingerprint' => ['required', 'string'],
        'public_key' => ['required', 'string'],
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
