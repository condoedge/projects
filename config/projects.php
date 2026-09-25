<?php

return [
    /*
     * GitHub bidirectional sync. Per-project owner/repo overrides live on pm_projects;
     * these are the defaults + the credentials.
     */
    'github' => [
        'token'          => env('GITHUB_TOKEN'),
        'api_base_url'   => env('GITHUB_API_BASE_URL', 'https://api.github.com'),
        'owner'          => env('GITHUB_REPO_OWNER'),
        'repo'           => env('GITHUB_REPO_NAME'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],

    // Disk used to store screenshots/attachments (respects the app default, s3 in prod).
    'attachments_disk' => env('PROJECTS_ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'public')),
];
