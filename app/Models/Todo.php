<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $created_by
 * @property int|null $assigned_to
 * @property int|null $verified_by
 * @property string $title
 * @property string|null $description
 * @property int $points
 * @property string $status
 * @property Carbon|null $completed_at
 * @property Carbon|null $verified_at
 * @property int|null $category_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $creator
 * @property-read User|null $assignee
 * @property-read User|null $verifier
 * @property-read TodoCategory|null $category
 */
class Todo extends Model
{
    use HasFactory;
    protected $fillable = [
        'team_id',
        'created_by',
        'assigned_to',
        'verified_by',
        'title',
        'description',
        'points',
        'status',
        'completed_at',
        'verified_at',
        'category_id',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the team that owns the todo.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created the todo.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who is assigned to the todo.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who verified the todo.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the category that the todo belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TodoCategory::class);
    }

    /**
     * Prüft, ob ein Benutzer dieses Todo übernehmen kann.
     */
    public function canBeAssignedTo(User $user): bool
    {
        $eligibleRoles = ['Mitglied', 'Ehrenmitglied', 'Kassenwart', 'Vorstand', 'Admin'];
        $team = $this->team;

        if (!$team) {
            return false;
        }

        $membership = $team->users()
            ->where('user_id', $user->id)
            ->whereIn('team_user.role', $eligibleRoles)
            ->first();

        return $membership !== null;
    }

    /**
     * Prüft, ob ein Benutzer dieses Todo erstellen kann.
     */
    public function canBeCreatedBy(User $user): bool
    {
        $eligibleRoles = ['Kassenwart', 'Vorstand', 'Admin'];
        $team = $this->team;

        if (!$team) {
            return false;
        }

        $membership = $team->users()
            ->where('user_id', $user->id)
            ->whereIn('team_user.role', $eligibleRoles)
            ->first();

        return $membership !== null;
    }

    /**
     * Prüft, ob ein Benutzer dieses Todo verifizieren kann.
     */
    public function canBeVerifiedBy(User $user): bool
    {
        $eligibleRoles = ['Kassenwart', 'Vorstand', 'Admin'];
        $team = $this->team;

        if (!$team) {
            return false;
        }

        $membership = $team->users()
            ->where('user_id', $user->id)
            ->whereIn('team_user.role', $eligibleRoles)
            ->first();

        return $membership !== null;
    }
}
