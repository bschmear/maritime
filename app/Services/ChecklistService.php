<?php

namespace App\Domain\Checklist\Services;

use App\Domain\Checklist\Models\Checklist;
use App\Domain\Checklist\Models\ChecklistTemplate;

class ChecklistService
{
    /**
     * Create a checklist from a template and attach to a model
     */
    public function createFromTemplate(
        ChecklistTemplate $template,
        $model,
        ?string $nameOverride = null
    ): Checklist {
        $checklist = Checklist::create([
            'checklist_template_id' => $template->id,
            'name' => $nameOverride ?? $template->name,
            'checklistable_id' => $model->id,
            'checklistable_type' => get_class($model),
        ]);

        foreach ($template->items as $item) {
            $checklist->items()->create([
                'label' => $item->label,
                'required' => $item->required,
                'position' => $item->position,
            ]);
        }

        return $checklist;
    }

    /**
     * Create checklist(s) based on context (auto-pick template)
     */
    public function createForContext(string $context, $model): ?Checklist
    {
        $template = ChecklistTemplate::where('context', $context)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (!$template) {
            return null;
        }

        return $this->createFromTemplate($template, $model);
    }
}