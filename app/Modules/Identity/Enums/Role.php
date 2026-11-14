<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * Staff roles from spec §2.6. Machine clients (API tokens) and guests are not
 * session users, so they are intentionally not listed here.
 *
 * Super Admin is organisation-wide; every other role is assigned per property.
 */
enum Role: string
{
    case SuperAdmin = 'super-admin';
    case PropertyManager = 'property-manager';
    case FrontDeskAgent = 'front-desk-agent';
    case RevenueManager = 'revenue-manager';
    case HousekeepingSupervisor = 'housekeeping-supervisor';
    case HousekeepingAttendant = 'housekeeping-attendant';
    case MaintenanceStaff = 'maintenance-staff';
    case Accountant = 'accountant';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::PropertyManager => 'Property Manager',
            self::FrontDeskAgent => 'Front Desk Agent',
            self::RevenueManager => 'Revenue Manager',
            self::HousekeepingSupervisor => 'Housekeeping Supervisor',
            self::HousekeepingAttendant => 'Housekeeping Attendant',
            self::MaintenanceStaff => 'Maintenance Staff',
            self::Accountant => 'Accountant / Finance',
            self::Auditor => 'Auditor',
        };
    }

    /**
     * Roles that can be assigned to a user at a property.
     *
     * @return list<self>
     */
    public static function propertyRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role !== self::SuperAdmin));
    }

    /**
     * Default permission matrix, derived from the "typical access" column of spec §2.6.
     * Editable per organisation once custom roles land (SYS-02).
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin, self::PropertyManager => Permission::cases(),
            self::FrontDeskAgent => [
                Permission::DashboardView,
                Permission::ReservationsView, Permission::ReservationsCreate, Permission::ReservationsUpdate,
                Permission::ReservationsAssign, Permission::ReservationsCancel,
                Permission::FrontdeskCheckin, Permission::FrontdeskCheckout,
                Permission::GuestsView, Permission::GuestsCreate, Permission::GuestsUpdate,
                Permission::BillingView, Permission::BillingPost, Permission::PaymentsTake,
                Permission::HousekeepingView,
            ],
            self::RevenueManager => [
                Permission::DashboardView, Permission::ReservationsView,
                Permission::RatesManage, Permission::InventoryManage, Permission::ChannelsManage,
                Permission::ReportsView,
            ],
            self::HousekeepingSupervisor => [
                Permission::HousekeepingView, Permission::HousekeepingUpdate, Permission::MaintenanceView,
            ],
            self::HousekeepingAttendant => [
                Permission::HousekeepingView, Permission::HousekeepingUpdate,
            ],
            self::MaintenanceStaff => [
                Permission::MaintenanceView, Permission::MaintenanceUpdate,
            ],
            self::Accountant => [
                Permission::DashboardView,
                Permission::BillingView, Permission::BillingPost, Permission::BillingAdjust,
                Permission::PaymentsTake, Permission::PaymentsRefund, Permission::NightAuditRun,
                Permission::ReportsView,
            ],
            self::Auditor => [
                Permission::DashboardView, Permission::ReservationsView, Permission::GuestsView,
                Permission::BillingView, Permission::ReportsView, Permission::AuditView,
            ],
        };
    }
}
