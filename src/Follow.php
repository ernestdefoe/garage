<?php

namespace ErnestDefoe\Garage;

use Flarum\Database\AbstractModel;

/**
 * A member following a build (told about its new log updates): one row per member and build (the pair is the key).
 *
 * @property int $build_id
 * @property int $user_id
 * @property \Carbon\Carbon $created_at
 */
class Follow extends AbstractModel
{
    protected $table = 'garage_follows';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'build_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
