<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    public function generate(
        string $prompt,
        ?string $system = null,
        int $maxTokens = 400
    ): ?string {

        $apiKey = config('services.gemini.key');

        $models = array_unique(array_filter([
            config('services.gemini.model', 'gemini-3.5-flash-lite'),
            config('services.gemini.fallback_model', 'gemini-3.8-flash'),
        ]));

        if (empty($apiKey)) {
            Log::error('Gemini API key is missing.');
            return null;
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        if ($system) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $system]],
            ];
        }

        foreach ($models as $model) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $response = Http::connectTimeout(15)
                        ->timeout(35)
                        ->withHeaders([
                            'Content-Type' => 'application/json',
                            'x-goog-api-key' => $apiKey,
                        ])
                        ->post(
                            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                            $payload
                        );

                    if ($response->successful()) {
                        $parts = $response->json(
                            'candidates.0.content.parts',
                            []
                        );

                        $text = collect($parts)
                            ->pluck('text')
                            ->filter(fn ($value) =>
                                is_string($value) && trim($value) !== ''
                            )
                            ->implode("\n");

                        if (trim($text) !== '') {
                            Log::info('Gemini response generated', [
                                'model' => $model,
                            ]);

                            return trim($text);
                        }

                        Log::warning('Gemini returned empty output', [
                            'model' => $model,
                            'finish_reason' => $response->json(
                                'candidates.0.finishReason'
                            ),
                        ]);

                        break;
                    }

                    $status = $response->status();

                    Log::warning('Gemini request failed', [
                        'model' => $model,
                        'attempt' => $attempt,
                        'status' => $status,
                        'body' => substr($response->body(), 0, 500),
                    ]);

                    if (in_array($status, [429, 500, 502, 503, 504])) {
                        if ($attempt < 2) {
                            sleep(1);
                            continue;
                        }

                        break;
                    }

                    return null;

                } catch (\Throwable $e) {
                    Log::error('Gemini connection error', [
                        'model' => $model,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);

                    if ($attempt < 2) {
                        sleep(1);
                        continue;
                    }

                    break;
                }
            }

            Log::warning('Moving to next Gemini model', [
                'failed_model' => $model,
            ]);
        }

        Log::error('All Gemini models failed.');

        return null;
    }

    public function refineLeaveReason(
        string $roughReason,
        string $leaveType
    ): ?string {

        $system = 'You rewrite short leave reasons into clear, professional sentences of one or two sentences. '
            . 'Keep the meaning exact. Do not add new facts. Do not use emojis or em dashes.';

        $prompt = "Leave type: {$leaveType}\n"
            . "Rough reason: {$roughReason}\n"
            . 'Return only the rewritten reason, nothing else.';

        return $this->generate($prompt, $system, 120);
    }

    public function suggestTimesheetEntry(
        string $projectName,
        string $date,
        ?string $hint = null
    ): ?string {

        $system = 'You write concise, professional task descriptions for timesheets. '
            . 'One or two sentences describing typical work on the given project. '
            . 'Do not use emojis or em dashes.';

        $prompt = "Project: {$projectName}\n"
            . "Date: {$date}\n"
            . ($hint ? "Hint: {$hint}\n" : '')
            . 'Return only the task description.';

        return $this->generate($prompt, $system, 120);
    }

    public function autofillTimesheet(
        string $projectName,
        string $date,
        ?string $hint = null
    ): ?string {

        $system = 'You are an assistant that generates professional timesheet entries. '
            . 'Generate a realistic, concise description of work performed on the specified project. '
            . 'Use one or two professional sentences. '
            . 'Do not invent specific meetings, deliverables, or completed tasks. '
            . 'Do not use emojis or em dashes.';

        $prompt = "Project: {$projectName}\n"
            . "Date: {$date}\n"
            . ($hint ? "Additional information: {$hint}\n" : '')
            . 'Generate a professional timesheet description. '
            . 'Return only the description.';

        return $this->generate($prompt, $system, 150);
    }
}
