<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ResumeParserService
{
    /**
     * Parse a resume file (PDF/DOCX) and return extracted fields.
     *
     * Lightweight heuristic parser — no external AI calls.
     * Falls back gracefully when text extraction fails.
     */
    public function parse(UploadedFile $file): array
    {
        $text = $this->extractText($file);

        if (empty($text)) {
            return [
                'success' => false,
                'message' => 'Could not extract text from resume.',
                'data' => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Resume parsed successfully.',
            'data' => [
                'full_name' => $this->extractName($text),
                'email' => $this->extractEmail($text),
                'phone' => $this->extractPhone($text),
                'linkedin_url' => $this->extractLinkedIn($text),
                'years_experience' => $this->extractYearsExperience($text),
                'skills' => $this->extractSkills($text),
                'current_position' => $this->extractPosition($text),
                'highest_qualification' => $this->extractQualification($text),
                'raw_text_preview' => mb_substr($text, 0, 500),
            ],
        ];
    }

    /**
     * Best-effort text extraction from PDF/DOCX/TXT.
     */
    protected function extractText(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        try {
            return match ($ext) {
                'txt' => (string) @file_get_contents($path),
                'pdf' => $this->extractPdf($path),
                'docx' => $this->extractDocx($path),
                'doc' => '', // legacy doc not supported natively
                default => '',
            };
        } catch (\Throwable $e) {
            Log::warning('ResumeParserService: extraction failed', [
                'ext' => $ext,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    protected function extractPdf(string $path): string
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            return '';
        }
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        return (string) $pdf->getText();
    }

    protected function extractDocx(string $path): string
    {
        if (!class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            return '';
        }
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }
        return $text;
    }

    // ============================================================
    // EXTRACTORS (heuristic)
    // ============================================================

    protected function extractName(string $text): ?string
    {
        // Try "Name: John Doe" pattern first
        if (preg_match('/\b(?:name|full name)\s*[:\-]\s*([A-Z][a-zA-Z\'\-]+(?:\s+[A-Z][a-zA-Z\'\-]+){1,3})/i', $text, $m)) {
            return trim($m[1]);
        }
        // Fall back to first line that looks like a name
        $lines = preg_split('/\r\n|\r|\n/', $text);
        foreach (array_slice($lines, 0, 5) as $line) {
            $line = trim($line);
            if (preg_match('/^[A-Z][a-zA-Z\'\-]+(?:\s+[A-Z][a-zA-Z\'\-]+){1,3}$/', $line)) {
                return $line;
            }
        }
        return null;
    }

    protected function extractEmail(string $text): ?string
    {
        if (preg_match('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    protected function extractPhone(string $text): ?string
    {
        if (preg_match('/(\+?\d[\d\s\-\(\)]{7,}\d)/', $text, $m)) {
            return preg_replace('/\s+/', ' ', trim($m[0]));
        }
        return null;
    }

    protected function extractLinkedIn(string $text): ?string
    {
        if (preg_match('#(https?://)?(www\.)?linkedin\.com/in/[A-Za-z0-9\-_%]+#i', $text, $m)) {
            return str_starts_with($m[0], 'http') ? $m[0] : 'https://' . ltrim($m[0], '/');
        }
        return null;
    }

    protected function extractYearsExperience(string $text): ?int
    {
        if (preg_match('/(\d{1,2})\+?\s*(?:years?|yrs?)\s*(?:of\s*)?experience/i', $text, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    protected function extractSkills(string $text): array
    {
        $knownSkills = [
            // Languages
            'PHP', 'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'C++', 'Go', 'Rust', 'Ruby',
            'Kotlin', 'Swift', 'Dart', 'SQL', 'Bash', 'Shell',
            // Frameworks
            'Laravel', 'Symfony', 'CodeIgniter', 'React', 'Vue', 'Angular', 'Svelte',
            'Node.js', 'Express', 'Next.js', 'Nuxt', 'Django', 'Flask', 'Spring', '.NET',
            'Livewire', 'Tailwind', 'Bootstrap',
            // DB / infra
            'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'Elasticsearch', 'Docker', 'Kubernetes',
            'AWS', 'Azure', 'GCP', 'Terraform', 'Ansible', 'CI/CD', 'Jenkins', 'GitHub Actions',
            // Other
            'Git', 'REST', 'GraphQL', 'Microservices', 'Agile', 'Scrum', 'Jira', 'Figma',
            'Excel', 'PowerBI', 'Tableau', 'SAP', 'Salesforce',
        ];

        $found = [];
        foreach ($knownSkills as $skill) {
            if (preg_match('/\b' . preg_quote($skill, '/') . '\b/i', $text)) {
                $found[] = $skill;
            }
        }
        return array_values(array_unique($found));
    }

    protected function extractPosition(string $text): ?string
    {
        $titles = [
            'Software Engineer', 'Software Developer', 'Senior Developer', 'Junior Developer',
            'Full Stack Developer', 'Frontend Developer', 'Backend Developer',
            'DevOps Engineer', 'QA Engineer', 'Data Analyst', 'Data Scientist',
            'Project Manager', 'Product Manager', 'Business Analyst', 'Scrum Master',
            'Accountant', 'HR Manager', 'Recruiter', 'Consultant', 'Architect',
        ];
        foreach ($titles as $title) {
            if (preg_match('/\b' . preg_quote($title, '/') . '\b/i', $text, $m)) {
                return $title;
            }
        }
        return null;
    }

    protected function extractQualification(string $text): ?string
    {
        $quals = ['PhD', 'Doctorate', 'Master', 'MSc', 'MA', 'MBA', 'Honours', 'BSc', 'BA', 'BCom', 'BTech', 'Diploma', 'Certificate', 'Matric'];
        foreach ($quals as $q) {
            if (preg_match('/\b' . preg_quote($q, '/') . '\b/i', $text)) {
                return $q;
            }
        }
        return null;
    }
}