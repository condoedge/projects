<?php

namespace Condoedge\Projects\Services\Github;

use Condoedge\Utils\Services\AbstractApiClientService;

/**
 * Thin GitHub Issues REST client. Mirrors app/Services/Coolecto/CoolectoApiClient:
 * extend the shared AbstractApiClientService, which sends JSON with a Bearer token.
 */
class GithubApiClient extends AbstractApiClientService
{
    protected $name = 'GitHub API';

    public function __construct($token = null)
    {
        parent::__construct($token ?: config('projects.github.token'));
    }

    protected function getBaseUrl()
    {
        return config('projects.github.api_base_url', 'https://api.github.com');
    }

    public function createIssue(string $owner, string $repo, string $title, string $body, array $labels = []): array
    {
        return $this->request('POST', "repos/{$owner}/{$repo}/issues", array_filter([
            'title' => $title,
            'body' => $body,
            'labels' => $labels ?: null,
        ]));
    }

    public function updateIssue(string $owner, string $repo, int $number, array $fields): array
    {
        return $this->request('PATCH', "repos/{$owner}/{$repo}/issues/{$number}", $fields);
    }

    public function setState(string $owner, string $repo, int $number, string $state): array
    {
        return $this->updateIssue($owner, $repo, $number, ['state' => $state]); // 'open' | 'closed'
    }

    public function addComment(string $owner, string $repo, int $number, string $body): array
    {
        return $this->request('POST', "repos/{$owner}/{$repo}/issues/{$number}/comments", ['body' => $body]);
    }

    public function getIssue(string $owner, string $repo, int $number): array
    {
        return $this->request('GET', "repos/{$owner}/{$repo}/issues/{$number}");
    }
}
