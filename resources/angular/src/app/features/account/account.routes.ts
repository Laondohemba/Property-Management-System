import { Routes } from '@angular/router';

export const ACCOUNT_ROUTES: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'profile' },
  { path: 'profile', title: 'Profile', loadComponent: () => import('./profile.page').then((m) => m.ProfilePage) },
];
