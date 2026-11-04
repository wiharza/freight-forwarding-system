<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    public const ADMIN = 'admin';

    public const CS = 'cs';

    public const CUSTOMS_SPECIALIST = 'customs_specialist';

    public const OPS = 'ops';

    public const FIELD = 'field';

    public const FINANCE = 'finance';

    protected $fillable = [
        'slug',
        'name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
