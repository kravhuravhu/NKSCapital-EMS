<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\EmployeeCsvImportService;
use App\Services\AuditService;
use App\Support\RoleCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class EmployeeImportController extends Controller
{
    public function __construct(protected EmployeeCsvImportService $import) {}

    public function template()
    {
        $this->requireAdmin();

        return response(EmployeeCsvImportService::templateCsv())
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="employee-import-template.csv"');
    }

    public function preview(Request $request)
    {
        $this->requireAdmin();

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);

        $path = $request->file('file')->store('imports', 'local');

        try {
            $result = $this->import->preview(Storage::disk('local')->path($path));
        } finally {
            Storage::disk('local')->delete($path);
        }

        return response()->json(['status' => 'success', 'data' => $result], 200);
    }

    public function import(Request $request)
    {
        $user = $this->requireAdmin();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Password is incorrect'], 422);
        }

        $path = $request->file('file')->store('imports', 'local');

        try {
            $result = $this->import->import(Storage::disk('local')->path($path), $user);
        } finally {
            Storage::disk('local')->delete($path);
        }

        AuditService::log(
            action: 'EMPLOYEE_CSV_BATCH_IMPORTED',
            tableName: 'users',
            recordId: 0,
            newValues: [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'actor_id' => $user->id,
            ],
            logType: 'success'
        );

        return response()->json(['status' => 'success', 'data' => $result], 200);
    }

    protected function requireAdmin()
    {
        $user = Auth::user();

        if (! RoleCheck::has($user, ['admin', 'super_admin'])) {
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }

        return $user;
    }
}