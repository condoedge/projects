<?php

namespace Condoedge\Projects\Kompo\FeatureRequests;

use Condoedge\Projects\Models\FeatureRequest;
use Condoedge\Utils\Kompo\Common\Modal;

/**
 * Dumps the full feature-request documentation as markdown so it can be pasted into
 * Claude Code for effort/time estimation (the documented, no-API estimation method).
 */
class EstimationExportModal extends Modal
{
    public $_Title = 'projects.export-for-estimation';
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
        return _Rows(
            _Html('Copy this into Claude Code, then paste the returned days / complexity / confidence back into the feature request.')
                ->class('text-sm text-gray-500 mb-2'),
            _Textarea()->value($this->fr->toMarkdown())->rows(18)->class('font-mono text-xs'),
        );
    }
}
