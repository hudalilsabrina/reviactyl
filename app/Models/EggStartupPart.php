<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $egg_id
 * @property string $name
 * @property string $value
 * @property string|null $description
 * @property bool $default_enabled
 * @property bool $required
 * @property int $sort_order
 * @property string|null $group_name
 */
class EggStartupPart extends Model
{
    public const RESOURCE_NAME = 'egg_startup_part';

    protected $table = 'egg_startup_parts';

    protected $fillable = [
        'name',
        'value',
        'description',
        'default_enabled',
        'required',
        'sort_order',
        'group_name',
    ];

    protected $casts = [
        'egg_id' => 'integer',
        'default_enabled' => 'boolean',
        'required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static array $validationRules = [
        'egg_id' => 'required|integer|exists:eggs,id',
        'name' => 'required|string|max:191',
        'value' => 'required|string|max:191',
        'description' => 'nullable|string|max:500',
        'default_enabled' => 'sometimes|boolean',
        'required' => 'sometimes|boolean',
        'sort_order' => 'sometimes|integer|min:0',
        'group_name' => 'nullable|string|max:191',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * @return BelongsTo<Egg, $this>
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }
}
