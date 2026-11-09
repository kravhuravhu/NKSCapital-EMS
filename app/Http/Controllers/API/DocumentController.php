<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Document;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    public function list(Request $request)
    {
        $user = Auth::user();

        // shared documents
        $shared = Document::shared()
            ->orderBy('title')
            ->get()
            ->map(fn ($d) => $this->format($d));

        // personal documents (from documents table)
        $mine = Document::ownedBy($user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($d) => $this->format($d));

        // merge contracts into "mine"
        $contracts = Contract::where('user_id', $user->id)
            ->orderByDesc('version')
            ->get()
            ->map(fn ($c) => [
                'id'           => 'contract-' . $c->id,
                'title'        => 'Employment Contract v' . $c->version,
                'category'     => 'HR',
                'description'  => null,
                'size'         => null,
                'size_human'   => 'PDF',
                'updated_at'   => $c->updated_at?->toIso8601String(),
                'download_url' => route('portal.documents.stream', ['type' => 'contract', 'id' => $c->id]),
                'source'       => 'contract',
            ]);

        $mine = $mine->concat($contracts)->values();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'shared'  => $shared,
                'mine'    => $mine,
                'summary' => [
                    'shared_count' => $shared->count(),
                    'mine_count'   => $mine->count(),
                    'total_count'  => $shared->count() + $mine->count(),
                ],
            ],
        ], 200);
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'      => 'required|string|max:255',
            'category'   => 'required|in:HR,Compliance,Policy,Finance,Personal',
            'file'       => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'is_shared'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $file = $request->file('file');

        $filename = 'doc-' . time() . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path     = $file->storeAs('documents', $filename, 'public');

        $document = Document::create([
            'user_id'     => $request->boolean('is_shared') ? null : $user->id,
            'title'       => $request->title,
            'category'    => $request->category,
            'file_path'   => $path,
            'file_size'   => $file->getSize(),
            'mime_type'   => $file->getMimeType(),
            'is_shared'   => $request->boolean('is_shared'),
            'uploaded_by' => $user->id,
        ]);

        AuditService::log(
            action: 'DOCUMENT_UPLOADED',
            tableName: 'documents',
            recordId: $document->id,
            newValues: [
                'title'    => $document->title,
                'category' => $document->category,
                'shared'   => $document->is_shared,
            ],
            logType: 'success'
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Document uploaded',
            'data'    => $this->format($document),
        ], 201);
    }

    public function stream(string $type, int $id)
    {
        $user = Auth::user();

        if ($type === 'contract') {
            $contract = Contract::where('user_id', $user->id)->findOrFail($id);
            $path     = $contract->file_path;

            if (!$path || !Storage::disk('public')->exists($path)) {
                abort(404, 'Contract file not uploaded yet.');
            }

            return Storage::disk('public')->download($path, basename($path));
        }

        $document = Document::findOrFail($id);

        if (!$document->is_shared && $document->user_id !== $user->id && !in_array($user->role, ['admin', 'director', 'super_admin'])) {
            abort(403);
        }

        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'This document has not been uploaded yet.');
        }

        return Storage::disk('public')->download($document->file_path, basename($document->file_path));
    }

    protected function format(Document $d): array
    {
        return [
            'id'           => $d->id,
            'title'        => $d->title,
            'category'     => $d->category,
            'description'  => $d->description,
            'size_human'   => $d->humanSize(),
            'updated_at'   => $d->updated_at?->toIso8601String(),
            'download_url' => route('portal.documents.stream', ['type' => 'document', 'id' => $d->id]),
            'source'       => 'document',
        ];
    }

    public function summarize(Request $request, GeminiService $gemini)
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'nullable|exists:documents,id',
            'file' => 'nullable|file|mimes:pdf,txt|max:10240',
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $text = '';
        $title = $request->title ?? 'Untitled';
        $category = $request->category ?? 'General';

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());

            if ($ext === 'txt') {
                $text = (string) @file_get_contents($file->getRealPath());
            } elseif ($ext === 'pdf' && class_exists(\Smalot\PdfParser\Parser::class)) {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($file->getRealPath());
                    $text = (string) $pdf->getText();
                } catch (\Throwable $e) {
                    $text = '';
                }
            }
        } elseif ($request->filled('document_id')) {
            $doc = Document::findOrFail($request->document_id);

            if (! $doc->file_path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($doc->file_path)) {
                return response()->json(['status' => 'error', 'message' => 'File not found'], 404);
            }

            $title = $doc->title;
            $category = $doc->category;

            $path = \Illuminate\Support\Facades\Storage::disk('public')->path($doc->file_path);

            if (str_ends_with($doc->file_path, '.pdf') && class_exists(\Smalot\PdfParser\Parser::class)) {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($path);
                    $text = (string) $pdf->getText();
                } catch (\Throwable $e) {
                    $text = '';
                }
            } elseif (str_ends_with($doc->file_path, '.txt')) {
                $text = (string) @file_get_contents($path);
            }
        }

        if (strlen(trim($text)) < 100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not enough text to summarize. Upload a text based PDF or a .txt file.',
            ], 422);
        }

        $summary = $gemini->summarizeDocument($title, $category, $text);

        if (! $summary) {
            return response()->json(['status' => 'error', 'message' => 'AI is unavailable.'], 503);
        }

        return response()->json(['status' => 'success', 'data' => ['summary' => $summary]], 200);
    }
}