<?php

namespace App\Models;

use App\Services\BaxxMilestoneActivityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

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

    protected static function booted(): void
    {
        static::created(function (UserPoint $userPoint): void {
            $userPointId = $userPoint->id;

            DB::afterCommit(function () use ($userPointId): void {
                app(BaxxMilestoneActivityService::class)
                    ->recordForUserPoint($userPointId);
            });
        });
    }

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

    public function maddraxikonRewardEvent(): HasOne
    {
        return $this->hasOne(MaddraxikonRewardEvent::class, 'user_point_id');
    }

    public function maddraxikonReversalRewardEvent(): HasOne
    {
        return $this->hasOne(MaddraxikonRewardEvent::class, 'reversal_user_point_id');
    }
}
