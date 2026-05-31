<?php

namespace App\Models\Concerns;

use App\Domain\Document\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasDocuments
{
    public function documents(): MorphToMany
    {
        return $this->morphToMany(
            Document::class,
            'documentable'
        )->withPivot(['sort_order', 'role', 'visible_to_customer', 'visible_to_vendor'])
            ->withTimestamps();
    }

    /**
     * @param  array<string, mixed>  $pivot
     */
    public function attachDocument(Document $document, array $pivot = []): void
    {
        $this->documents()->attach($document->id, array_merge([
            'visible_to_customer' => false,
            'visible_to_vendor' => false,
        ], $pivot));
    }

    /**
     * @param  array<string, mixed>  $pivot
     */
    public function updateDocumentPivot(Document $document, array $pivot): void
    {
        $this->documents()->updateExistingPivot($document->id, $pivot);
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
