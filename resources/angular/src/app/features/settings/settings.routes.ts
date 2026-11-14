import { Routes } from '@angular/router';

import { permissionGuard } from '../../core/auth/guards';

const canManageProperties = permissionGuard((session) => session.isSuperAdmin() || session.can('settings.property'));
const canManageRooms = permissionGuard((session) => session.can('settings.rooms'));
const canManageStaff = permissionGuard((session) => session.canManageStaff());
const isSuperAdmin = permissionGuard((session) => session.isSuperAdmin());

export const SETTINGS_ROUTES: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'properties' },

  // Property setup (SYS-03)
  {
    path: 'properties',
    title: 'Properties',
    canActivate: [canManageProperties],
    loadComponent: () => import('./properties/property-list.page').then((m) => m.PropertyListPage),
  },
  {
    path: 'properties/new',
    title: 'New property',
    canActivate: [permissionGuard((session) => session.isSuperAdmin())],
    loadComponent: () => import('./properties/property-edit.page').then((m) => m.PropertyEditPage),
  },
  {
    path: 'properties/:id',
    title: 'Edit property',
    canActivate: [canManageProperties],
    loadComponent: () => import('./properties/property-edit.page').then((m) => m.PropertyEditPage),
  },

  // Room types, rooms and amenities of the active property (SYS-04)
  {
    path: 'room-types',
    title: 'Room types',
    canActivate: [canManageRooms],
    loadComponent: () => import('./rooms/room-type-list.page').then((m) => m.RoomTypeListPage),
  },
  {
    path: 'room-types/new',
    title: 'New room type',
    canActivate: [canManageRooms],
    loadComponent: () => import('./rooms/room-type-edit.page').then((m) => m.RoomTypeEditPage),
  },
  {
    path: 'room-types/:id',
    title: 'Edit room type',
    canActivate: [canManageRooms],
    loadComponent: () => import('./rooms/room-type-edit.page').then((m) => m.RoomTypeEditPage),
  },
  {
    path: 'rooms',
    title: 'Rooms',
    canActivate: [canManageRooms],
    loadComponent: () => import('./rooms/room-list.page').then((m) => m.RoomListPage),
  },
  {
    path: 'rooms/new',
    title: 'New room',
    canActivate: [canManageRooms],
    loadComponent: () => import('./rooms/room-edit.page').then((m) => m.RoomEditPage),
  },
  {
    path: 'rooms/range',
    title: 'Add rooms in bulk',
    canActivate: [canManageRooms],
    loadComponent: () => import('./rooms/room-range.page').then((m) => m.RoomRangePage),
  },
  {
    path: 'rooms/:id',
    title: 'Edit room',
    canActivate: [canManageRooms],
    loadComponent: () => import('./rooms/room-edit.page').then((m) => m.RoomEditPage),
  },
  {
    path: 'amenities',
    title: 'Amenities',
    canActivate: [canManageRooms],
    loadComponent: () => import('./rooms/amenities.page').then((m) => m.AmenitiesPage),
  },

  // Staff and roles (SYS-01, SYS-02)
  {
    path: 'users',
    title: 'Users',
    canActivate: [canManageStaff],
    loadComponent: () => import('./users/user-list.page').then((m) => m.UserListPage),
  },
  {
    path: 'users/invite',
    title: 'Invite staff',
    canActivate: [canManageStaff],
    loadComponent: () => import('./users/user-invite.page').then((m) => m.UserInvitePage),
  },
  {
    path: 'users/:id',
    title: 'Manage user',
    canActivate: [canManageStaff],
    loadComponent: () => import('./users/user-detail.page').then((m) => m.UserDetailPage),
  },
  {
    path: 'roles',
    title: 'Roles',
    canActivate: [isSuperAdmin],
    loadComponent: () => import('./users/role-list.page').then((m) => m.RoleListPage),
  },
  {
    path: 'roles/new',
    title: 'New role',
    canActivate: [isSuperAdmin],
    loadComponent: () => import('./users/role-edit.page').then((m) => m.RoleEditPage),
  },
  {
    path: 'roles/:name',
    title: 'Role',
    canActivate: [isSuperAdmin],
    loadComponent: () => import('./users/role-edit.page').then((m) => m.RoleEditPage),
  },
];
