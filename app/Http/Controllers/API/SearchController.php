<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $user = Auth::user();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'success', 'data' => ['groups' => []]], 200);
        }

        $query = '%' . $request->q . '%';
        $groups = [];

        $canSeePeople = in_array($user->role, ['manager', 'director', 'admin', 'super_admin', 'recruiter'], true);

        if ($canSeePeople) {
            $users = User::where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('first_name', 'like', $query)
                      ->orWhere('last_name', 'like', $query)
                      ->orWhere('email', 'like', $query)
                      ->orWhere('employee_number', 'like', $query);
                })
                ->limit(6)
                ->get(['id', 'first_name', 'last_name', 'email', 'employee_number', 'position']);

            if ($users->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Employees',
                    'items' => $users->map(fn ($u) => [
                        'label' => $u->full_name,
                        'subtitle' => $u->position ?? $u->email,
                        'url' => route('portal.employee.profile', ['id' => $u->id]),
                    ])->values(),
                ];
            }
        }

        if (in_array($user->role, ['admin', 'super_admin', 'manager', 'director'], true)) {
            $clients = Client::where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('company_name', 'like', $query)
                      ->orWhere('registration_number', 'like', $query);
                })
                ->limit(6)
                ->get(['id', 'company_name', 'registration_number']);

            if ($clients->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Clients',
                    'items' => $clients->map(fn ($c) => [
                        'label' => $c->company_name,
                        'subtitle' => $c->registration_number,
                        'url' => route('portal.admin.clients'),
                    ])->values(),
                ];
            }

            $projects = Project::where(function ($q) use ($query) {
                $q->where('name', 'like', $query)
                  ->orWhere('project_code', 'like', $query);
            })
                ->limit(6)
                ->get(['id', 'name', 'project_code']);

            if ($projects->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Projects',
                    'items' => $projects->map(fn ($p) => [
                        'label' => $p->name,
                        'subtitle' => $p->project_code,
                        'url' => route('portal.employee.projects'),
                    ])->values(),
                ];
            }
        }

        $documents = Document::where(function ($q) use ($query, $user) {
                $q->where('is_shared', true)->orWhere('user_id', $user->id);
            })
            ->where('title', 'like', $query)
            ->limit(5)
            ->get(['id', 'title', 'category']);

        if ($documents->isNotEmpty()) {
            $groups[] = [
                'label' => 'Documents',
                'items' => $documents->map(fn ($d) => [
                    'label' => $d->title,
                    'subtitle' => $d->category,
                    'url' => route('portal.employee.documents'),
                ])->values(),
            ];
        }

        return response()->json(['status' => 'success', 'data' => ['groups' => $groups]], 200);
    }
}