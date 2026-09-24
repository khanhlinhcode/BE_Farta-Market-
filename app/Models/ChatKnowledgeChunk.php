<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatKnowledgeChunk extends Model
{
    protected $fillable = [
        'document_id', 'section', 'content', 'normalized_content', 'position', 'checksum',
    ];

    protected $casts = ['position' => 'integer'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(ChatKnowledgeDocument::class, 'document_id');
    }
}
