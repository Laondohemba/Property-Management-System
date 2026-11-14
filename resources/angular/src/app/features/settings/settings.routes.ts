import { Routes } from '@angular/router';

import { permissionGuard } from '../../core/auth/guards';

const canManageProperties = permissionGuard((session) => session.isSuperAdmin() || session.can('settings.property'));

export const SETTINGS_ROUTES: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'properties' },
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
];
