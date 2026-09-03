<?php

namespace App\Modules\Financial\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\User\Models\User;

/**
 * Auditoria centralizada das operações financeiras.
 *
 * Reutiliza o sistema de auditoria existente (audit_logs). Operações
 * originadas de jobs/console (sem usuário autenticado) são ignoradas.
 */
class FinancialAuditService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array<string, mixed>|null  $details
     */
    public function record(
        ?User $actor,
        AuditAction $action,
        string $entityType,
        string|int $entityId,
        ?array $details = null,
    ): void {
        if ($actor === null) {
            return;
        }

        $this->audit->recordEntity($actor, $action, $entityType, $entityId, $details);
    }
}
