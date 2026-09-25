<?php

namespace Condoedge\Projects\Kompo\FeatureRequests;

use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Utils\Kompo\Common\Modal;

/**
 * Dumps the documented feature + a prompt so Claude Code can confront it with the REAL SISC
 * codebase and list the missing answers/gaps — the "AI review ↔ code" step before "ready".
 */
class AiReviewExportModal extends Modal
{
    public $_Title = 'projects.ai-review-export';
    public $class = 'max-w-2xl';

    protected FeatureRequest $fr;

    public function created()
    {
        $this->fr = FeatureRequest::asSystemOperation()->findOrFail($this->modelKey() ?? $this->prop('id'));
    }

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function body()
    {
        $prompt = implode("\n", [
            'You are reviewing a proposed change against the real SISC Laravel 10 + Kompo codebase.',
            'Confront the documented feature below with the actual code (models, routes, Kompo forms,',
            'permissions, DB schema). List precisely:',
            '1. Open questions / missing answers needed before it can be built.',
            '2. Existing code that already does part of this (files/classes to reuse).',
            '3. Hidden constraints or edge cases (team scoping, permissions, migrations).',
            'Then propose a decomposition into small, measurable, demonstrable deliverables.',
            '',
            '--- FEATURE ---',
            $this->fr->toMarkdown(),
        ]);

        return _Rows(
            _Html('Copie ceci dans Claude Code (dans le repo SISC). Reporte les trous/réponses dans « Notes de revue IA » de la fiche.')
                ->class('text-sm text-gray-500 mb-2'),
            _Textarea()->value($prompt)->rows(20)->class('font-mono text-xs'),
        );
    }
}
