<?php

namespace Condoedge\Projects\Models;

use Condoedge\Projects\Models\Casts\ListValueCast;
use Condoedge\Projects\Models\Enums\FeatureRequestStatusEnum;
use Condoedge\Projects\Models\Enums\SuggestionStatusEnum;
use Condoedge\Utils\Models\Files\MorphManyFilesTrait;
use Condoedge\Utils\Models\Model;
use Kompo\Auth\Contracts\Security\ScopedToTeam;
use Kompo\Auth\Models\Concerns\Security\BelongsToOneTeam;
use Kompo\Auth\Models\Teams\BelongsToTeamTrait;

class Suggestion extends Model implements ScopedToTeam
{
    use BelongsToOneTeam, BelongsToTeamTrait, MorphManyFilesTrait;

    public const MORPH_ALIAS = 'pm_suggestion';

    protected $table = 'pm_suggestions';
    protected $guarded = [];

    protected $casts = [
        'status' => ListValueCast::class . ":" . ListValue::SUGGESTION_STATUS,
    ];

    /**
     * Still waiting on a human: promoted and declined are decisions already made.
     *
     * Four call sites spelled this whereNotIn out by hand, so "what is still open" was defined
     * four times and could quietly stop agreeing with itself.
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNotIn('status', [
            SuggestionStatusEnum::PROMOTED->value,
            SuggestionStatusEnum::DECLINED->value,
        ]);
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function featureRequest()
    {
        return $this->belongsTo(FeatureRequest::class, 'promoted_to_feature_request_id');
    }

    /** The SISC user this suggestion is attributed to (the real requester), set manually. */
    public function requestedByUser()
    {
        return $this->belongsTo(\Kompo\Auth\Facades\UserModel::getClass(), 'requested_by_user_id');
    }

    /**
     * Turn this suggestion into a feature request (light → heavy).
     * Seeds the FR title/problem from the suggestion; the guided form does the rest.
     */
    public function promote(): FeatureRequest
    {
        $fr = new FeatureRequest();
        $fr->setTeamId($this->team_id);
        $fr->project_id = $this->project_id;
        $fr->title = $this->title;
        $fr->problem = $this->body;
        $fr->status = FeatureRequestStatusEnum::DRAFT;
        $fr->promoted_from_suggestion_id = $this->id;
        $fr->save();

        $this->status = SuggestionStatusEnum::PROMOTED;
        $this->promoted_to_feature_request_id = $fr->id;
        $this->save();

        return $fr;
    }
}
