/**
 * BFF response contracts.
 *
 * Hand-written for now; ARC-11 requires these to be generated from the Laravel
 * Resources/DTOs (spatie/laravel-data + typescript-transformer) and checked in CI.
 */

export interface BffEnvelope<TData, TMeta = Record<string, unknown>> {
  data: TData;
  meta?: TMeta;
}

export interface PageMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface SessionUser {
  id: string;
  name: string;
  email: string;
  two_factor_enabled: boolean;
  last_login_at: string | null;
}

export interface SessionOrganization {
  id: string;
  name: string;
}

export interface SessionRole {
  name: string;
  label: string;
}

export interface SessionProperty {
  id: string;
  name: string;
  code: string;
  timezone: string;
  currency: string;
  business_date: string;
  role: SessionRole | null;
}

export interface SessionData {
  user: SessionUser;
  organization: SessionOrganization | null;
  is_super_admin: boolean;
  roles: SessionRole[];
  /** Permissions held at the active property. */
  permissions: string[];
  properties: SessionProperty[];
  active_property: SessionProperty | null;
}

export interface SessionMeta {
  app_version: string;
}

export interface Property {
  id: string;
  name: string;
  code: string;
  timezone: string;
  currency: string;
  business_date: string;
  check_in_time: string;
  check_out_time: string;
  email: string | null;
  phone: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string | null;
  is_active: boolean;
  updated_at: string | null;
}

export type PropertyInput = Omit<Property, 'id' | 'business_date' | 'updated_at'>;

export interface Option {
  value: string;
  label: string;
}

export interface Amenity {
  id: string;
  name: string;
  room_types_count?: number;
}

export interface Bed {
  type: string;
  label?: string;
  count: number;
}

export interface RoomType {
  id: string;
  name: string;
  code: string;
  description: string | null;
  base_occupancy: number;
  max_adults: number;
  max_children: number;
  max_occupancy: number;
  bed_configuration: Bed[];
  amenities: Amenity[];
  rooms_count?: number;
  sort_order: number;
  is_active: boolean;
}

export interface RoomTypeInput {
  name: string;
  code: string;
  description: string | null;
  base_occupancy: number;
  max_adults: number;
  max_children: number;
  max_occupancy: number;
  bed_configuration: { type: string; count: number }[];
  amenity_ids: string[];
  sort_order: number;
  is_active: boolean;
}

export interface Room {
  id: string;
  number: string;
  floor: string | null;
  notes: string | null;
  room_type: { id: string; name: string; code: string };
  housekeeping_status: Option;
  occupancy_status: Option;
  sort_order: number;
  is_active: boolean;
}

export interface RoomInput {
  room_type_id: string;
  number: string;
  floor: string | null;
  notes: string | null;
  sort_order: number;
  is_active: boolean;
}

export interface RoomRangeInput {
  room_type_id: string;
  floor: string | null;
  prefix: string | null;
  from: number;
  to: number;
}

export interface DashboardTile {
  key: string;
  label: string;
  value: number;
}

export interface DashboardData {
  property: {
    id: string;
    name: string;
    code: string;
    timezone: string;
    currency: string;
    business_date: string;
    check_in_time: string;
    check_out_time: string;
    local_time: string;
  };
  tiles: DashboardTile[];
  alerts: unknown[];
}
