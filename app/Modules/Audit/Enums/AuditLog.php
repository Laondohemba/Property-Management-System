<?php

declare(strict_types=1);

namespace App\Modules\Audit\Enums;

enum AuditLog: string
{
    /** Create / update / delete of business records (AUD-01). */
    case Data = 'data';
    /** Sign-ins, 2FA, passwords, invitations and permission changes (AUD-04). */
    case Security = 'security';
}
