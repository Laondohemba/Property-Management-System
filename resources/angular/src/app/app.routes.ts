import { Routes } from '@angular/router';

import { authGuard, guestGuard, permissionGuard, twoFactorEnrollmentGuard } from './core/auth/guards';

/** Feature routes are lazy-loaded per module (spec §11.1). */
export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
  {
    path: '',
    canActivate: [guestGuard],
    loadComponent: () => import('./layout/auth-layout').then((m) => m.AuthLayout),
    loadChildren: () => import('./features/auth/auth.routes').then((m) => m.AUTH_ROUTES),
  },
  {
    path: '',
    canActivate: [authGuard],
    canActivateChild: [twoFactorEnrollmentGuard],
    loadComponent: () => import('./layout/shell').then((m) => m.Shell),
    children: [
      {
        path: 'dashboard',
        title: 'Dashboard',
        loadComponent: () => import('./features/dashboard/dashboard.page').then((m) => m.DashboardPage),
      },
      {
        path: 'audit',
        title: 'Audit log',
        canActivate: [permissionGuard((session) => session.isSuperAdmin() || session.can('audit.view'))],
        loadComponent: () => import('./features/audit/audit-log.page').then((m) => m.AuditLogPage),
      },
      {
        path: 'rate-plans',
        canActivate: [permissionGuard((session) => session.can('rates.manage'))],
        children: [
          { path: '', title: 'Rate plans', loadComponent: () => import('./features/rates/rate-plan-list.page').then((m) => m.RatePlanListPage) },
          { path: 'new', title: 'New rate plan', loadComponent: () => import('./features/rates/rate-plan-edit.page').then((m) => m.RatePlanEditPage) },
          { path: ':id', title: 'Edit rate plan', loadComponent: () => import('./features/rates/rate-plan-edit.page').then((m) => m.RatePlanEditPage) },
        ],
      },
      {
        path: 'inventory',
        title: 'Inventory',
        canActivate: [permissionGuard((session) => session.can('inventory.manage'))],
        loadComponent: () => import('./features/inventory/inventory.page').then((m) => m.InventoryPage),
      },
      {
        path: 'settings',
        loadChildren: () => import('./features/settings/settings.routes').then((m) => m.SETTINGS_ROUTES),
      },
      {
        path: 'account',
        loadChildren: () => import('./features/account/account.routes').then((m) => m.ACCOUNT_ROUTES),
      },
    ],
  },
  {
    path: '**',
    title: 'Page not found',
    loadComponent: () => import('./features/errors/not-found.page').then((m) => m.NotFoundPage),
  },
];
