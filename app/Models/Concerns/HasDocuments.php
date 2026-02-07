<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use App\Domain\Document\Models\Document;

trait HasDocuments
{
    public function documents(): MorphToMany
    {
        return $this->morphToMany(
            Document::class,
            'documentable'
        )->withTimestamps();
    }

    /**
     * Attach a document to this model
     */
    public function attachDocument(Document $document): void
    {
        $this->documents()->attach($document);
    }

    /**
     * Detach a document from this model
     */
    public function detachDocument(Document $document): void
    {
        $this->documents()->detach($document);
    }

    /**
     * Check if a document is attached to this model
     */
    public function hasDocument(Document $document): bool
    {
        return $this->documents()->where('documents.id', $document->id)->exists();
    }
}
