<?php

namespace Condoedge\Projects\Http\Controllers;

use Condoedge\Projects\Jobs\ApplyGithubIssueEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GithubWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $event = $request->header('X-GitHub-Event');        // 'issues' | 'issue_comment' | 'ping' ...
        $payload = $request->all();

        if ($event === 'ping') {
            return response()->json(['ok' => true]);
        }

        $issueNumber = $payload['issue']['number'] ?? null;
        if (!$issueNumber) {
            return response()->json(['ignored' => true]);
        }

        // 'issues' carries an action (opened/closed/reopened/edited/labeled); comments are 'commented'.
        $action = $event === 'issue_comment' ? 'commented' : ($payload['action'] ?? 'unknown');

        // The number alone is ambiguous across repositories; the node id and the repository name
        // are what tie the event to one record.
        ApplyGithubIssueEvent::dispatch((int) $issueNumber, $action, [
            'event' => $event,
            'title' => $payload['issue']['title'] ?? null,
            'state' => $payload['issue']['state'] ?? null,
        ], $payload['issue']['node_id'] ?? null, $payload['repository']['full_name'] ?? null);

        return response()->json(['ok' => true]);
    }
}
