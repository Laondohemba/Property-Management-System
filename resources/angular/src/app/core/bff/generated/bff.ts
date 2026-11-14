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
export type FieldErrorData = {
field: string,
message: string,
};
export type HousekeepingStatus = 'dirty' | 'cleaning' | 'clean' | 'inspected' | 'out_of_order' | 'out_of_service';
export type HousekeepingStatusData = {
value: HousekeepingStatus,
label: string,
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
export type OccupancyStatus = 'vacant' | 'occupied';
export type OccupancyStatusData = {
value: OccupancyStatus,
label: string,
};
export type OrganizationRefData = {
id: string,
name: string,
};
export type PageMetaData = {
page: number,
per_page: number,
total: number,
last_page: number,
};
export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
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
};
export type PropertyRefData = {
id: string,
name: string,
code: string,
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
rooms_count?: number,
sort_order: number,
is_active: boolean,
};
export type RoomTypeListMetaData = {
bed_types: BedTypeOptionData[],
page: number,
per_page: number,
total: number,
last_page: number,
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
