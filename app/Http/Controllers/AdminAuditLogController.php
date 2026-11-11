<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminAuditLogIndexRequest;
use App\Models\AdminAuditLog;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    private const SUBJECT_TYPES = [
        'user' => User::class,
        'role' => Role::class,
        'menu' => Menu::class,
    ];

    public function __construct(private readonly AdminAuditService $auditService) {}

    public function index(AdminAuditLogIndexRequest $request): View
    {
        $filters = $request->validated();
        $subjectClass = self::SUBJECT_TYPES[$filters['subject_type'] ?? ''] ?? null;

        $audits = AdminAuditLog::query()
            ->with(['actor:id,name,email', 'subject'])
            ->when($filters['actor_id'] ?? null, fn ($query, $actorId) => $query->where('actor_id', $actorId))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($subjectClass, fn ($query, $type) => $query->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn ($query, $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.access-logs.index', [
            'audits' => $audits,
            'filters' => $filters,
            'actors' => User::query()->whereHas('adminAuditLogs')->orderBy('name')->get(['id', 'name', 'email']),
            'actions' => AdminAuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'subjectTypes' => AdminAuditLog::SUBJECT_LABELS,
        ]);
    }

    public function show(Request $request, AdminAuditLog $auditLog): View
    {
        abort_unless($request->user()->hasPermission('permissions.manage'), 403);
        $auditLog->load(['actor:id,name,email', 'subject']);

        return view('admin.access-logs.show', [
            'audit' => $auditLog,
            'beforeValues' => $this->auditService->safeForDisplay($auditLog->before_values),
            'afterValues' => $this->auditService->safeForDisplay($auditLog->after_values),
        ]);
    }
}
