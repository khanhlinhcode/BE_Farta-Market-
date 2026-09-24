<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatKnowledgeDocument extends Model
{
    protected $fillable = [
        'source_id', 'title', 'locale', 'topic', 'version', 'status', 'owner',
        'checksum', 'source_updated_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'source_updated_at' => 'date',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(ChatKnowledgeChunk::class, 'document_id')->orderBy('position');
    }
}
