/**
 * BFF contract for the SPA.
 *
 * Response types are GENERATED from the Laravel DTOs into ./generated/bff.ts (spec ARC-11):
 * run `composer types` after changing a DTO or enum; a backend test fails if the committed
 * file is out of date. This file only gives them app-friendly names and adds the
 * hand-written request payloads and the generic envelope.
 */
import type {
  AmenityData,
  BedData,
  BedType,
  BlockType,
  BlockTypeOptionData,
  InventoryBlockData,
  InventoryDayData,
  InventoryGridData,
  InventoryMetaData,
  InventoryRowData,
  OverbookingResultData,
  DashboardTileData,
  OrganizationRefData,
  PageMetaData,
  Permission,
  PermissionGroupData,
  PropertyData,
  RecordStampsData,
  RoleData,
  RoleRefData,
  RoomData,
  RoomTypeData,
  RoomTypeImageData,
  SessionMetaData,
  SessionPropertyData,
  SessionUserData,
  StaffAssignmentData,
  StaffOptionsData,
  StaffUserData,
} from './generated/bff';

export type {
  AuditEntryData as AuditEntry,
  AuditLog,
  BedType,
  BedTypeOptionData,
  BlockType,
  DashboardData,
  HousekeepingStatus,
  OccupancyStatus,
  Permission,
  RoomListMetaData,
  RoomRangeMetaData,
  RoomTypeListMetaData,
  SessionData,
  StaffListMetaData,
  StaffResponseMetaData,
  StaffStatus,
} from './generated/bff';

/** The { data, meta } envelope every BFF response uses (spec §8.1). */
export interface BffEnvelope<TData, TMeta = Record<string, unknown>> {
  data: TData;
  meta?: TMeta;
}

// Generated response types under the names used across the app -------------

export type PageMeta = PageMetaData;
export type SessionUser = SessionUserData;
export type SessionOrganization = OrganizationRefData;
export type SessionRole = RoleRefData;
export type SessionProperty = SessionPropertyData;
export type SessionMeta = SessionMetaData;
export type Property = PropertyData;
export type DashboardTile = DashboardTileData;
export type Amenity = AmenityData;
export type Bed = BedData;
export type RoomType = RoomTypeData;
export type RoomTypeImage = RoomTypeImageData;
export type InventoryGrid = InventoryGridData;
export type InventoryRow = InventoryRowData;
export type InventoryDay = InventoryDayData;
export type InventoryMeta = InventoryMetaData;
export type InventoryBlock = InventoryBlockData;
export type BlockTypeOption = BlockTypeOptionData;
export type OverbookingResult = OverbookingResultData;
export type Room = RoomData;
export type StaffAssignment = StaffAssignmentData;
export type StaffUser = StaffUserData;
export type StaffOptions = StaffOptionsData;
export type PermissionGroup = PermissionGroupData;
export type RoleDetail = RoleData;
export type RecordStamps = RecordStampsData;

// Request payloads (validated by Laravel Form Requests) -----------------------

export type PropertyInput = Omit<Property, 'id' | 'business_date' | 'updated_at' | 'stamps'>;

export interface RoomTypeInput {
  name: string;
  code: string;
  description: string | null;
  base_occupancy: number;
  max_adults: number;
  max_children: number;
  max_occupancy: number;
  bed_configuration: { type: BedType | string; count: number }[];
  amenity_ids: string[];
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

export interface RoleInput {
  label: string;
  description: string | null;
  permissions: Permission[];
  requires_two_factor: boolean;
}

export interface InventoryBlockInput {
  room_type_id: string;
  type: BlockType;
  quantity: number;
  /** First night, YYYY-MM-DD. */
  from_date: string;
  /** Last night (inclusive), YYYY-MM-DD. */
  to_date: string;
  reason: string;
  /** ISO-8601; the block is released automatically at this time. */
  expires_at: string | null;
}

export interface OverbookingInput {
  room_type_id: string;
  from: string;
  to: string;
  allowance: number;
  /** ISO weekdays (1 = Monday … 7 = Sunday); null for every night. */
  weekdays: number[] | null;
}
