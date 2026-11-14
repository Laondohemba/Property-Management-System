import { Routes } from '@angular/router';

import { authGuard, guestGuard, twoFactorEnrollmentGuard } from './core/auth/guards';

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
