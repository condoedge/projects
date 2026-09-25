<?php

namespace Condoedge\Projects\Models;

use Condoedge\Utils\Models\Model;

class GithubSyncLog extends Model
{
    protected $table = 'pm_github_sync_logs';
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];

    /** Record a sync step (audit + idempotency source). */
    public static function record(string $direction, $entity, ?int $issueNumber, ?string $action, array $payload = []): self
    {
        return static::create([
            'direction' => $direction,
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->id,
            'issue_number' => $issueNumber,
            'action' => $action,
            'payload' => $payload,
        ]);
    }
}
