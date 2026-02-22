<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreeDModel extends Model
{
    use HasFactory;

    protected $table = 'three_d_models';

    protected $fillable = [
        'name',
        'description',
        'file_path',
        'file_format',
        'file_size',
        'thumbnail_path',
        'maddraxikon_url',
        'required_baxx',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'required_baxx' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path
            ? asset('storage/'.$this->thumbnail_path)
            : null;
    }
}
