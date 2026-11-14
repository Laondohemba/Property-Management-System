import { Routes } from '@angular/router';

/** Guest-only screens. Paths avoid the Fortify POST endpoints (/login, /reset-password, ...). */
export const AUTH_ROUTES: Routes = [
  { path: 'sign-in', title: 'Sign in', loadComponent: () => import('./sign-in.page').then((m) => m.SignInPage) },
  {
    path: 'sign-in/two-factor',
    title: 'Two-factor authentication',
    loadComponent: () => import('./two-factor-challenge.page').then((m) => m.TwoFactorChallengePage),
  },
  {
    path: 'password/forgot',
    title: 'Reset password',
    loadComponent: () => import('./forgot-password.page').then((m) => m.ForgotPasswordPage),
  },
  {
    path: 'password/reset/:token',
    title: 'Choose a new password',
    loadComponent: () => import('./reset-password.page').then((m) => m.ResetPasswordPage),
  },
];
