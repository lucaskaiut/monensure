<?php

namespace App\Modules\Audit\Enums;

enum AuditAction: string
{
    case MasterLogin = 'master_login';
    case TenantSelected = 'tenant_selected';
    case TenantSwitched = 'tenant_switched';

    case PayableCreated = 'payable.created';
    case PayableUpdated = 'payable.updated';
    case PayableDeleted = 'payable.deleted';
    case PayablePaid = 'payable.paid';
    case PayableCancelled = 'payable.cancelled';

    case SupplierCreated = 'supplier.created';
    case SupplierUpdated = 'supplier.updated';
    case SupplierDeleted = 'supplier.deleted';

    case CategoryCreated = 'category.created';
    case CategoryUpdated = 'category.updated';
    case CategoryDeleted = 'category.deleted';

    case RecurrenceCreated = 'recurrence.created';
    case RecurrenceUpdated = 'recurrence.updated';
    case RecurrenceDeleted = 'recurrence.deleted';
}
