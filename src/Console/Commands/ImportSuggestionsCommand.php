<?php

namespace Condoedge\Projects\Console\Commands;

use Condoedge\Projects\Models\Enums\FeatureRequestStatusEnum;
use Condoedge\Projects\Models\Enums\SuggestionStatusEnum;
use Condoedge\Projects\Models\Project;
use Condoedge\Projects\Models\Suggestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Imports the support-team suggestion backlog (a markdown registry) into pm_suggestions.
 * Idempotent by `reference` (S###). The markdown lives outside the repo (agent data); pass its path.
 */
class ImportSuggestionsCommand extends Command
{
    protected $signature = 'projects:import-suggestions
        {--file= : Path to suggestions.md (default: .tmp/support_triage/suggestions.md)}
        {--team= : Team id to own the backlog project (default: first MAIN-level team)}
        {--project= : Project id to attach to (default: find/create "SISC v2 — Backlog support")}
        {--promote= : Comma list of refs (e.g. S002,S169) to promote into feature requests}';

    protected $description = 'Import the support suggestion backlog into the projects module';

    public function handle(): int
    {
        $file = $this->option('file') ?: base_path('.tmp/support_triage/suggestions.md');
        if (!File::exists($file)) {
            $this->error("File not found: $file");

            return self::FAILURE;
        }

        $teamId = $this->resolveTeamId();
        if (!$teamId) {
            $this->error('No team found to own the backlog project.');

            return self::FAILURE;
        }

        $project = $this->resolveProject($teamId);
        $content = File::get($file);

        $details = $this->indexDetailBlocks($content);
        $rows = $this->parseRanking($content);

        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            $body = $this->buildBody($row, $details);

            $existing = Suggestion::withoutGlobalScopes()->where('reference', $row['ref'])->first();
            $s = $existing ?: new Suggestion();
            if (!$existing) {
                $s->setTeamId($teamId);
                $s->status = SuggestionStatusEnum::NEW;
            }
            $s->project_id = $project->id;
            $s->title = mb_substr($this->stripMd($row['subject']), 0, 180);
            $s->body = $body;
            $s->requester_name = $row['requester'] ?: null;
            $s->reference = $row['ref'];
            $s->save();

            $existing ? $updated++ : $created++;
        }

        $this->info("Backlog imported into project #{$project->id} ({$project->name}) on team #{$teamId}.");
        $this->info("Created: $created · Updated: $updated · Total rows: ".count($rows));

        $this->promoteRefs($project);

        return self::SUCCESS;
    }

    protected function resolveTeamId(): ?int
    {
        if ($this->option('team')) {
            return (int) $this->option('team');
        }

        $teamClass = config('kompo-auth.team-model-namespace');

        // MAIN level = 1 in TeamLevelEnum.
        return $teamClass::query()->where('team_level', 1)->orderBy('id')->value('id')
            ?? $teamClass::query()->orderBy('id')->value('id');
    }

    protected function resolveProject(int $teamId): Project
    {
        if ($this->option('project')) {
            return Project::findOrFail($this->option('project'));
        }

        $name = 'SISC v2 — Backlog support';
        $project = Project::withoutGlobalScopes()->where('name', $name)->first();
        if (!$project) {
            $project = new Project();
            $project->setTeamId($teamId);
            $project->name = $name;
            $project->description = __('projects.imported-backlog');
            $project->save();
        }

        return $project;
    }

    /** Index the "# Suggestions — détail" section: map Odoo #NNNN → raw block text. */
    protected function indexDetailBlocks(string $content): array
    {
        $pos = mb_strpos($content, '# Suggestions');
        if ($pos === false) {
            return [];
        }
        $section = mb_substr($content, $pos);

        $map = [];
        $current = null;
        $buffer = [];
        foreach (preg_split('/\r\n|\r|\n/', $section) as $line) {
            if (preg_match('/^##\s+#(\d+)/', $line, $m)) {
                if ($current) {
                    $map[$current] = trim(implode("\n", $buffer));
                }
                $current = $m[1];
                $buffer = [];
            } elseif ($current && !preg_match('/^#\s/', $line)) {
                $buffer[] = $line;
            }
        }
        if ($current) {
            $map[$current] = trim(implode("\n", $buffer));
        }

        return $map;
    }

    /** Parse the ranking tables (before "# Suggestions — détail"). */
    protected function parseRanking(string $content): array
    {
        $end = mb_strpos($content, '# Suggestions');
        $ranking = $end !== false ? mb_substr($content, 0, $end) : $content;

        $rows = [];
        $module = null;
        foreach (preg_split('/\r\n|\r|\n/', $ranking) as $line) {
            if (preg_match('/^###\s+(.+)$/', $line, $m)) {
                $module = trim($m[1]);
                continue;
            }
            // Ranking rows: | S### | Sujet | Demandeur | Type | Effort | Info |
            if (!preg_match('/^\|\s*(S\d{3})\s*\|/', $line)) {
                continue;
            }
            $cells = array_map('trim', explode('|', trim($line, "| \t")));
            // cells: [ref, subject, requester, type, effort, info]
            $rows[] = [
                'ref' => $cells[0] ?? '',
                'subject' => $cells[1] ?? '',
                'requester' => $cells[2] ?? '',
                'type' => $cells[3] ?? '',
                'effort' => $cells[4] ?? '',
                'info' => $cells[5] ?? '',
                'module' => $module,
            ];
        }

        return $rows;
    }

    protected function buildBody(array $row, array $details): string
    {
        $parts = [$row['subject']];

        // Enrich with the Odoo detail block when the subject references a #NNNN we indexed.
        if (preg_match('/#(\d+)/', $row['subject'], $m) && !empty($details[$m[1]])) {
            $parts[] = "\n---\n".$details[$m[1]];
        }

        $meta = array_filter([
            $row['module'] ? 'Module: '.$row['module'] : null,
            $row['type'] ? 'Type: '.$row['type'] : null,
            $row['effort'] ? 'Effort: '.$row['effort'] : null,
            $row['info'] ? 'Info: '.$row['info'] : null,
            'Réf: '.$row['ref'],
        ]);
        $parts[] = "\n---\n".implode(' · ', $meta);

        return implode("\n", $parts);
    }

    protected function stripMd(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['**', '`', '🔴'], '', $s)));
    }

    /** Promote the requested refs into feature requests (materialises "promoted → to do"). */
    protected function promoteRefs(Project $project): void
    {
        $refs = array_filter(array_map('trim', explode(',', (string) $this->option('promote'))));
        foreach ($refs as $ref) {
            $s = Suggestion::withoutGlobalScopes()->where('reference', $ref)->first();
            if (!$s) {
                $this->warn("Promote: ref $ref not found.");
                continue;
            }
            if ($s->status?->isKey("PROMOTED")) {
                continue;
            }
            $fr = $s->promote();
            $fr->status = FeatureRequestStatusEnum::IN_ANALYSIS;
            $fr->proposed_solution = $fr->proposed_solution ?: 'À cadrer — promue depuis la suggestion '.$ref.'.';
            $fr->saveQuietly();
            $this->info("Promoted $ref → feature request #{$fr->id}.");
        }
    }
}
