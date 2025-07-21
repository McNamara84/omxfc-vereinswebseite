<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Team;
use App\Models\User;
use App\Models\Todo;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property int|null $todo_id
 * @property int $points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Team $team
 * @property-read Todo|null $todo
 */
class UserPoint extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'team_id',
        'todo_id',
        'points',
    ];

    /**
     * Get the user that owns the points.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team that the points belong to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the todo that the points are for.
     */
    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }
}
