<?php

namespace App\Models;

use App\Support\Euro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuktionGebot extends Model
{
    use HasFactory;

    protected $table = 'auktion_gebote';

    protected $fillable = [
        'auktion_id',
        'user_id',
        'bieter_name',
        'betrag_cent',
    ];

    protected $casts = [
        'betrag_cent' => 'integer',
    ];

    public function auktion(): BelongsTo
    {
        return $this->belongsTo(Auktion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFormatierterBetragAttribute(): string
    {
        return Euro::format($this->betrag_cent);
    }
}
