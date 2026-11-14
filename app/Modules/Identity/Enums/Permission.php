<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * Property-scoped permissions, named after the BFF endpoint catalogue (spec §8.2).
 * A user's permissions come from the role they hold at the active property.
 */
enum Permission: string
{
    case DashboardView = 'dashboard.view';

    case ReservationsView = 'reservations.view';
    case ReservationsCreate = 'reservations.create';
    case ReservationsUpdate = 'reservations.update';
    case ReservationsAssign = 'reservations.assign';
    case ReservationsCancel = 'reservations.cancel';
    case FrontdeskCheckin = 'frontdesk.checkin';
    case FrontdeskCheckout = 'frontdesk.checkout';

    case GuestsView = 'guests.view';
    case GuestsCreate = 'guests.create';
    case GuestsUpdate = 'guests.update';
    case GuestsMerge = 'guests.merge';

    case RatesManage = 'rates.manage';
    case InventoryManage = 'inventory.manage';
    case ChannelsManage = 'channels.manage';

    case HousekeepingView = 'housekeeping.view';
    case HousekeepingUpdate = 'housekeeping.update';
    case MaintenanceView = 'maintenance.view';
    case MaintenanceUpdate = 'maintenance.update';

    case BillingView = 'billing.view';
    case BillingPost = 'billing.post';
    case BillingAdjust = 'billing.adjust';
    case PaymentsTake = 'payments.take';
    case PaymentsRefund = 'payments.refund';
    case NightAuditRun = 'audit.run';

    case ReportsView = 'reports.view';
    case AuditView = 'audit.view';

    case SettingsProperty = 'settings.property';
    case SettingsRooms = 'settings.rooms';
    case SettingsTaxes = 'settings.taxes';
    case SettingsPolicies = 'settings.policies';
    case SettingsTemplates = 'settings.templates';

    case UsersManage = 'users.manage';
    case IntegrationsManage = 'integrations.manage';

    /**
     * Grouping used by the permission matrix screen (SYS-02).
     */
    public function group(): string
    {
        return explode('.', $this->value)[0];
    }

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'View dashboard',
            self::ReservationsView => 'View reservations',
            self::ReservationsCreate => 'Create reservations',
            self::ReservationsUpdate => 'Modify reservations',
            self::ReservationsAssign => 'Assign and move rooms',
            self::ReservationsCancel => 'Cancel and no-show',
            self::FrontdeskCheckin => 'Check in',
            self::FrontdeskCheckout => 'Check out',
            self::GuestsView => 'View guests',
            self::GuestsCreate => 'Create guests',
            self::GuestsUpdate => 'Edit guests',
            self::GuestsMerge => 'Merge guests',
            self::RatesManage => 'Manage rates and restrictions',
            self::InventoryManage => 'Manage allotments and blocks',
            self::ChannelsManage => 'Manage channels',
            self::HousekeepingView => 'View housekeeping board',
            self::HousekeepingUpdate => 'Update room status',
            self::MaintenanceView => 'View work orders',
            self::MaintenanceUpdate => 'Manage work orders',
            self::BillingView => 'View folios and invoices',
            self::BillingPost => 'Post charges',
            self::BillingAdjust => 'Reverse and transfer charges',
            self::PaymentsTake => 'Take payments',
            self::PaymentsRefund => 'Refund payments',
            self::NightAuditRun => 'Run night audit',
            self::ReportsView => 'View reports',
            self::AuditView => 'View audit log',
            self::SettingsProperty => 'Edit property details',
            self::SettingsRooms => 'Manage rooms and room types',
            self::SettingsTaxes => 'Manage taxes and charge codes',
            self::SettingsPolicies => 'Manage policies',
            self::SettingsTemplates => 'Manage document templates',
            self::UsersManage => 'Manage staff access',
            self::IntegrationsManage => 'Manage integrations',
        };
    }

    public static function groupLabel(string $group): string
    {
        return match ($group) {
            'frontdesk' => 'Front desk',
            'audit' => 'Audit and night audit',
            default => ucfirst($group),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
