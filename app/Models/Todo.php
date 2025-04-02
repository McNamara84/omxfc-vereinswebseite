<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
