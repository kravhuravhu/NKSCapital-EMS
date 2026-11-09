<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function generate(string $prompt, ?string $system = null, int $maxTokens = 400): ?string
    {
        $apiKey = config('services.gemini.key');

        if (! $apiKey) {
            return null;
        }

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        if ($system) {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint . '?key=' . urlencode($apiKey), $payload);

            if (! $response->successful()) {
                Log::warning('Gemini request failed', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                return null;
            }

            return trim($response->json('candidates.0.content.parts.0.text') ?? '') ?: null;
        } catch (\Throwable $e) {
            Log::warning('Gemini request error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function refineLeaveReason(string $roughReason, string $leaveType): ?string
    {
        $system = 'You rewrite short leave reasons into clear, professional sentences of one or two sentences. '
                . 'Keep the meaning exact. Do not add new facts. Do not use emojis or em dashes.';

        $prompt = "Leave type: {$leaveType}\nRough reason: {$roughReason}\n"
                . 'Return only the rewritten reason, nothing else.';

        return $this->generate($prompt, $system, 120);
    }

    public function suggestTimesheetEntry(string $projectName, string $date, ?string $hint = null): ?string
    {
        $system = 'You write concise, professional task descriptions for timesheets. '
                . 'One or two sentences describing typical work on the given project. Do not use emojis or em dashes.';

        $prompt = "Project: {$projectName}\nDate: {$date}\n"
                . ($hint ? "Hint: {$hint}\n" : '')
                . 'Return only the task description.';

        return $this->generate($prompt, $system, 120);
    }

    public function autofillTimesheet(string $description, array $workDays, array $projects): ?array
    {
        $system = 'You convert a rough description of a month of work into timesheet rows. '
                . 'Return ONLY a valid JSON array. No prose, no markdown fences. '
                . 'Each element must be an object with keys: '
                . '"work_date" (YYYY-MM-DD), "project_id" (integer, from the available list), '
                . '"task_description" (short phrase under 100 characters), "hours" (number 1 to 8). '
                . 'Never invent a project_id that is not in the available list. '
                . 'Never use a work_date that is not in the provided working days list. '
                . 'Distribute hours sensibly, most days 8 hours. Skip a day if the description does not mention work.';

        $projectSummary = collect($projects)
            ->map(fn ($p) => sprintf('  id=%s name=%s', $p['id'], $p['name']))
            ->implode("\n");

        $prompt = "Available projects:\n{$projectSummary}\n\n"
                . 'Available working days (do not deviate): ' . implode(', ', $workDays) . "\n\n"
                . "Employee description:\n{$description}\n\n"
                . 'Return the JSON array now.';

        $response = $this->generate($prompt, $system, 4000);

        if (! $response) return null;

        // Strip markdown fences and any leading prose
        $response = preg_replace('/```(?:json)?\s*|\s*```/', '', $response);

        if (preg_match('/\[[\s\S]*\]/', $response, $matches)) {
            $response = $matches[0];
        }

        $decoded = json_decode(trim($response), true);

        if (! is_array($decoded)) return null;

        return array_values($decoded);
    }

    public function summarizeDocument(string $title, string $category, string $extractedText): ?string
    {
        $system = 'You write concise summaries of uploaded HR documents. '
                . 'Two or three short sentences. Include the document type, the parties involved, '
                . 'and any dates or amounts that matter. No emojis. No em dashes. No quotes.';

        $prompt = "Title: {$title}\n"
                . "Category: {$category}\n"
                . "Content:\n" . mb_substr($extractedText, 0, 6000) . "\n\n"
                . 'Write the summary now.';

        return $this->generate($prompt, $system, 220);
    }

    public function describeAssetIssue(string $assetModel, string $roughIssue): ?string
    {
        $system = 'You rewrite short equipment issue descriptions into clear, professional sentences. '
                . 'One or two sentences. Keep the meaning exact. Mention what is broken and what the user tried. '
                . 'No emojis, no em dashes.';

        $prompt = "Asset: {$assetModel}\n"
                . "User description: {$roughIssue}\n"
                . 'Return only the rewritten description.';

        return $this->generate($prompt, $system, 150);
    }
}