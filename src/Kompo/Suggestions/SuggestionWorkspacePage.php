<?php

namespace Condoedge\Projects\Kompo\Suggestions;

use Condoedge\Projects\Kompo\Concerns\ChangesPmStatus;
use Condoedge\Projects\Kompo\Concerns\PmElements;
use Condoedge\Projects\Kompo\Concerns\ReadOnlyWorkspace;
use Condoedge\Projects\Kompo\Concerns\SearchesUsers;
use Condoedge\Projects\Models\ListValue;
use Condoedge\Projects\Models\Suggestion;
use Condoedge\Utils\Kompo\Common\Form;

/**
 * One page per suggestion: read-only by default, editable behind the Modifier button.
 * Same shape as the task and feature pages — the layout lives in ReadOnlyWorkspace.
 */
class SuggestionWorkspacePage extends Form
{
    use SearchesUsers;
    use ChangesPmStatus;
    use PmElements;
    use ReadOnlyWorkspace;

    public const ID = 'pm-suggestion-workspace';
    public $id = self::ID;
    public $containerClass = 'fullContainer';
    public $model = Suggestion::class;

    public function authorize()
    {
        return isSuperAdmin();
    }

    public function created()
    {
        // super_admin works across teams → load bypassing the team scope.
        if ($this->modelKey()) {
            $this->model(Suggestion::asSystemOperation()->with(['project', 'addedBy'])->findOrFail($this->modelKey()));
        }

        $this->readEditMode();
    }

    public function render()
    {
        $suggestion = $this->model;

        return _Rows(
            $this->workspaceHeader(
                'pm.suggestion',
                $suggestion->id,
                'pm.project-board',
                ['project_id' => $suggestion->project_id],
            ),

            !$suggestion->reference ? null :
                _Html($suggestion->reference)->class('text-xs text-gray-400 mt-1'),

            // The status band the feature page carries, minus the stepper — a suggestion has no
            // pipeline, only a state.
            _CardWhite(
                $this->pmStatusPill(
                    $suggestion->status,
                    $suggestion->id,
                    ListValue::SUGGESTION_STATUS,
                    $suggestion->team_id,
                    self::ID
                ),
            )->p4()->class('mt-4'),

            $this->workspaceColumns(
                [$this->bodyBlock($suggestion), $this->notesBlock($suggestion)],
                [$this->detailsBlock($suggestion), $this->requesterBlock($suggestion), $this->promotionBlock($suggestion),
                    // The same context the drawer shows, so opening the full page adds to it
                    // rather than losing it.
                    _CardWhite($this->pmContextSections($suggestion))->p4(),
                ],
            ),
        );
    }

    protected function pmStatusModel($id)
    {
        return Suggestion::asSystemOperation()->findOrFail($id);
    }

    // ── BLOCKS ──

    protected function bodyBlock(Suggestion $suggestion)
    {
        return $this->infoBlock(
            'projects.description',
            $this->editing
                ? $this->auto(_Textarea()->name('body')->rows(6))
                : $this->readText($suggestion->body),
        );
    }

    protected function notesBlock(Suggestion $suggestion)
    {
        return $this->infoBlock(
            'projects.internal-notes',
            $this->editing
                ? _Rows(
                    $this->auto(_Textarea()->name('internal_notes')->rows(4)),
                    _MultiFile('projects.attachments')->name('files'),
                )
                : $this->readText($suggestion->internal_notes),
        );
    }

    protected function detailsBlock(Suggestion $suggestion)
    {
        return $this->infoBlock(
            'projects.details',
            // Identical in both modes, on purpose. Status is set from the band above and priority
            // from the stars in the list, so neither belongs to this form — and votes are counted,
            // not typed.
            _Rows(
                $this->detailRow('projects.status', $suggestion->status
                    ? _Pill($suggestion->status->label())
                        ->class($suggestion->status->displayColor() . ' text-white')
                    : null),
                $this->detailRow('projects.priority', $this->priorityStars($suggestion)),
                $this->detailRow('projects.votes', $suggestion->votes),
            ),
        );
    }

    protected function requesterBlock(Suggestion $suggestion)
    {
        return $this->infoBlock(
            'projects.requester-name',
            $this->editing
                ? _Rows(
                    $this->auto(_Input('projects.requester-name')->name('requester_name')),
                    // Searched rather than listed: the old picker ordered the whole 152k-row
                    // users table to keep an arbitrary 200, which cost over a second per open.
                    $this->autoSel(_Select('projects.requested-by-user')->name('requested_by_user_id')
                        ->searchOptions(2, 'searchUsers', 'retrieveUser')
                        ->comment('projects.requested-by-user-hint')),
                )
                : _Rows(
                    $this->detailRow('projects.requester-name', $suggestion->requester_name),
                    $this->detailRow('projects.requested-by-user', $suggestion->requestedByUser?->name),
                ),
        );
    }

    /** The same 1-3 stars the table draws, read-only here — they are set from the table. */
    protected function priorityStars(Suggestion $suggestion)
    {
        $current = (int) $suggestion->priority;

        return _Flex(
            collect(range(1, 3))->map(fn ($level) => _Html()
                ->icon('star')
                ->class($level <= $current ? 'text-warning' : 'text-gray-300'))->all()
        )->class('gap-1');
    }

    /** Where this suggestion went, once it became a feature request. */
    protected function promotionBlock(Suggestion $suggestion)
    {
        if (!$suggestion->promoted_to_feature_request_id) {
            return null;
        }

        return $this->infoBlock(
            'projects.promoted-to',
            _Link($suggestion->featureRequest?->title ?: '#' . $suggestion->promoted_to_feature_request_id)
                ->icon('arrow-right')
                ->href('pm.feature', ['id' => $suggestion->promoted_to_feature_request_id])
                ->class('underline'),
        );
    }
}
