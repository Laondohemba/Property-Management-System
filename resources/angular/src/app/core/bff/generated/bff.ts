export type ActorRefData = {
id: string,
name: string,
};
export type AmenityData = {
id: string,
name: string,
room_types_count?: number,
};
export type AssignableRoleData = {
name: string,
label: string,
is_system: boolean,
};
export type AuditActorData = {
id: string,
name: string,
email: string,
};
export type AuditEntryData = {
id: string,
log: AuditLog,
event: string | null,
description: string,
causer: AuditActorData | null,
subject: AuditSubjectData | null,
property: PropertyRefData | null,
old: Record<string, any> | null,
new: Record<string, any> | null,
details: Record<string, any>,
ip_address: string | null,
user_agent: string | null,
created_at: string,
};
export type AuditLog = 'data' | 'security';
export type AuditSubjectData = {
type: string,
id: string | null,
label: string | null,
};
export type BalanceDue = 'at_check_in' | 'at_check_out';
export type BedData = {
type: BedType,
label: string,
count: number,
};
export type BedType = 'single' | 'twin' | 'double' | 'queen' | 'king' | 'sofa_bed' | 'bunk';
export type BedTypeOptionData = {
value: BedType,
label: string,
};
export type BlockType = 'out_of_order' | 'out_of_service' | 'owner' | 'hold';
export type BlockTypeOptionData = {
value: BlockType,
label: string,
};
export type CancellationPolicyData = {
id: string,
name: string,
free_cancellation_hours: number | null,
penalty_type: PenaltyType,
penalty_nights: number | null,
penalty_percent: string | null,
penalty_amount_minor: number | null,
no_show_type: PenaltyType,
no_show_nights: number | null,
no_show_percent: string | null,
no_show_amount_minor: number | null,
guest_text: string | null,
summary: string,
is_active: boolean,
rate_plans_count: number,
stamps: RecordStampsData,
};
export type CursorPaginatedDataCollection<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type DashboardData = {
property: DashboardPropertyData,
tiles: DashboardTileData[],
alerts: string[],
};
export type DashboardPropertyData = {
id: string,
name: string,
code: string,
timezone: string,
currency: string,
business_date: string,
check_in_time: string,
check_out_time: string,
local_time: string,
};
export type DashboardTileData = {
key: string,
label: string,
value: number,
};
export type DepositDue = 'at_booking' | 'before_arrival';
export type DepositPolicyData = {
id: string,
name: string,
deposit_type: DepositType,
deposit_percent: string | null,
deposit_nights: number | null,
deposit_amount_minor: number | null,
deposit_due: DepositDue,
due_days_before_arrival: number | null,
requires_card_guarantee: boolean,
balance_due: BalanceDue,
guest_text: string | null,
summary: string,
is_active: boolean,
rate_plans_count: number,
stamps: RecordStampsData,
};
export type DepositType = 'none' | 'percent' | 'nights' | 'fixed';
export type FieldErrorData = {
field: string,
message: string,
};
export type HousekeepingStatus = 'dirty' | 'cleaning' | 'clean' | 'inspected' | 'out_of_order' | 'out_of_service';
export type HousekeepingStatusData = {
value: HousekeepingStatus,
label: string,
};
export type InventoryBlockData = {
id: string,
room_type: RoomTypeRefData,
type: BlockType,
type_label: string,
quantity: number,
from_date: string,
to_date: string,
reason: string,
expires_at: string | null,
released_at: string | null,
released_by: ActorRefData | null,
status: string,
stamps: RecordStampsData,
};
export type InventoryDayData = {
date: string,
total: number,
blocked: number,
sold: number,
overbook_allowance: number,
available: number,
sellable: number,
};
export type InventoryGridData = {
from: string,
to: string,
first_date: string,
last_date: string,
dates: string[],
rows: InventoryRowData[],
totals: InventoryDayData[],
};
export type InventoryMetaData = {
block_types: BlockTypeOptionData[],
};
export type InventoryRowData = {
room_type: RoomTypeRefData,
is_active: boolean,
days: InventoryDayData[],
};
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type MealPlan = 'room_only' | 'breakfast' | 'half_board' | 'full_board' | 'all_inclusive';
export type OccupancyStatus = 'vacant' | 'occupied';
export type OccupancyStatusData = {
value: OccupancyStatus,
label: string,
};
export type OptionData = {
value: string,
label: string,
};
export type OrganizationRefData = {
id: string,
name: string,
};
export type OverbookingResultData = {
nights_changed: number,
};
export type PageMetaData = {
page: number,
per_page: number,
total: number,
last_page: number,
};
export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type PenaltyType = 'none' | 'nights' | 'percent' | 'fixed';
export type Permission = 'dashboard.view' | 'reservations.view' | 'reservations.create' | 'reservations.update' | 'reservations.assign' | 'reservations.cancel' | 'frontdesk.checkin' | 'frontdesk.checkout' | 'guests.view' | 'guests.create' | 'guests.update' | 'guests.merge' | 'rates.manage' | 'inventory.manage' | 'channels.manage' | 'housekeeping.view' | 'housekeeping.update' | 'maintenance.view' | 'maintenance.update' | 'billing.view' | 'billing.post' | 'billing.adjust' | 'payments.take' | 'payments.refund' | 'audit.run' | 'reports.view' | 'audit.view' | 'settings.property' | 'settings.rooms' | 'settings.taxes' | 'settings.policies' | 'settings.templates' | 'users.manage' | 'integrations.manage';
export type PermissionGroupData = {
group: string,
label: string,
permissions: PermissionOptionData[],
};
export type PermissionOptionData = {
name: Permission,
label: string,
};
export type PoliciesData = {
cancellation: CancellationPolicyData[],
deposit: DepositPolicyData[],
};
export type PoliciesMetaData = {
currency: string,
penalty_types: OptionData[],
deposit_types: OptionData[],
deposit_dues: OptionData[],
balance_dues: OptionData[],
};
export type PolicyRefData = {
id: string,
name: string,
summary: string,
};
export type PricePreviewData = {
room_type: RoomTypeRefData,
adults: number,
children: number,
nights: number,
base_minor: number,
extra_adults: number,
extra_adult_minor: number,
extra_children: number,
extra_child_minor: number,
nightly_minor: number,
clamped: boolean,
room_charge_minor: number,
taxes: TaxBreakdownData,
cancellation_summary: string | null,
deposit_summary: string | null,
currency: string,
};
export type ProblemData = {
type: string,
title: string,
status: number,
code: string,
detail?: string,
errors?: FieldErrorData[],
};
export type PropertyData = {
id: string,
name: string,
code: string,
timezone: string,
currency: string,
business_date: string,
check_in_time: string,
check_out_time: string,
email: string | null,
phone: string | null,
address_line1: string | null,
address_line2: string | null,
city: string | null,
state: string | null,
postal_code: string | null,
country: string | null,
is_active: boolean,
updated_at: string | null,
stamps: RecordStampsData,
};
export type PropertyRefData = {
id: string,
name: string,
code: string,
};
export type RatePlanData = {
id: string,
name: string,
code: string,
description: string | null,
meal_plan: MealPlan,
meal_plan_label: string,
cancellation_policy: PolicyRefData | null,
deposit_policy: PolicyRefData | null,
min_price_minor: number | null,
max_price_minor: number | null,
sort_order: number,
is_active: boolean,
currency: string,
prices: RatePlanPriceData[],
taxes: TaxRefData[],
stamps: RecordStampsData,
};
export type RatePlanOptionsData = {
currency: string,
meal_plans: OptionData[],
room_types: RoomTypeOptionData[],
taxes: TaxData[],
cancellation_policies: PolicyRefData[],
deposit_policies: PolicyRefData[],
};
export type RatePlanPriceData = {
room_type: RoomTypeOptionData,
base_price_minor: number,
extra_adult_minor: number,
extra_child_minor: number,
};
export type RecordStampsData = {
created_at: string | null,
created_by: ActorRefData | null,
updated_at: string | null,
updated_by: ActorRefData | null,
};
export type Role = 'super-admin' | 'property-manager' | 'front-desk-agent' | 'revenue-manager' | 'housekeeping-supervisor' | 'housekeeping-attendant' | 'maintenance-staff' | 'accountant' | 'auditor';
export type RoleData = {
name: string,
label: string,
description: string | null,
is_system: boolean,
is_super_admin: boolean,
requires_two_factor: boolean,
permissions: Permission[],
users_count: number,
};
export type RoleMetaData = {
permission_groups: PermissionGroupData[],
};
export type RoleRefData = {
name: string,
label: string,
};
export type RoomData = {
id: string,
number: string,
floor: string | null,
notes: string | null,
room_type: RoomTypeRefData,
housekeeping_status: HousekeepingStatusData,
occupancy_status: OccupancyStatusData,
sort_order: number,
is_active: boolean,
stamps: RecordStampsData,
};
export type RoomListMetaData = {
floors: string[],
page: number,
per_page: number,
total: number,
last_page: number,
};
export type RoomRangeMetaData = {
created: number,
};
export type RoomTypeData = {
id: string,
name: string,
code: string,
description: string | null,
base_occupancy: number,
max_adults: number,
max_children: number,
max_occupancy: number,
bed_configuration: BedData[],
amenities: AmenityData[],
images: RoomTypeImageData[],
rooms_count?: number,
sort_order: number,
is_active: boolean,
stamps: RecordStampsData,
};
export type RoomTypeImageData = {
id: string,
file_name: string,
alt: string | null,
size: number,
position: number,
thumb_url: string,
large_url: string | null,
};
export type RoomTypeListMetaData = {
bed_types: BedTypeOptionData[],
page: number,
per_page: number,
total: number,
last_page: number,
};
export type RoomTypeOptionData = {
id: string,
name: string,
code: string,
base_occupancy: number,
max_adults: number,
max_children: number,
max_occupancy: number,
is_active: boolean,
};
export type RoomTypeRefData = {
id: string,
name: string,
code: string,
};
export type SessionData = {
user: SessionUserData,
organization: OrganizationRefData | null,
is_super_admin: boolean,
roles: RoleRefData[],
permissions: Permission[],
properties: SessionPropertyData[],
active_property: SessionPropertyData | null,
};
export type SessionMetaData = {
app_version: string,
};
export type SessionPropertyData = {
id: string,
name: string,
code: string,
timezone: string,
currency: string,
business_date: string,
role: RoleRefData | null,
};
export type SessionUserData = {
id: string,
name: string,
email: string,
two_factor_enabled: boolean,
two_factor_required: boolean,
last_login_at: string | null,
};
export type StaffAssignmentData = {
property: PropertyRefData,
role: RoleRefData | null,
};
export type StaffListMetaData = {
properties: PropertyRefData[],
roles: AssignableRoleData[],
can_manage_accounts: boolean,
page: number,
per_page: number,
total: number,
last_page: number,
};
export type StaffOptionsData = {
properties: PropertyRefData[],
roles: AssignableRoleData[],
can_manage_accounts: boolean,
};
export type StaffResponseMetaData = {
invited?: boolean,
properties: PropertyRefData[],
roles: AssignableRoleData[],
can_manage_accounts: boolean,
};
export type StaffStatus = 'active' | 'invited' | 'inactive';
export type StaffUserData = {
id: string,
name: string,
email: string,
status: StaffStatus,
is_active: boolean,
is_super_admin: boolean,
is_self: boolean,
two_factor_enabled: boolean,
last_login_at: string | null,
invited_at: string | null,
assignments: StaffAssignmentData[],
};
export type TaxBreakdownData = {
currency: string,
charge_minor: number,
net_minor: number,
inclusive_minor: number,
exclusive_minor: number,
total_minor: number,
lines: TaxLineData[],
};
export type TaxCalculation = 'percentage' | 'per_night' | 'per_stay' | 'per_person_night';
export type TaxData = {
id: string,
name: string,
code: string,
calculation: TaxCalculation,
calculation_label: string,
rate: string | null,
amount_minor: number | null,
is_inclusive: boolean,
sort_order: number,
is_active: boolean,
stamps: RecordStampsData,
};
export type TaxLineData = {
tax_id: string,
name: string,
code: string,
basis: string,
amount_minor: number,
is_inclusive: boolean,
};
export type TaxListMetaData = {
currency: string,
calculations: OptionData[],
};
export type TaxRefData = {
id: string,
name: string,
code: string,
};
