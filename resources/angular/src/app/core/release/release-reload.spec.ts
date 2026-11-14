import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { NavigationError, Router, provideRouter } from '@angular/router';

import { AppVersionService } from '../http/app-version.service';
import { HARD_NAVIGATE, ReleaseReloadService, isChunkLoadError } from './release-reload';

@Component({ template: '' })
class BlankPage {}

describe('ReleaseReloadService', () => {
  let navigations: string[];

  beforeEach(() => {
    navigations = [];
    TestBed.configureTestingModule({
      providers: [
        provideRouter([
          { path: 'one', component: BlankPage },
          { path: 'two', component: BlankPage },
        ]),
        { provide: HARD_NAVIGATE, useValue: (url: string) => navigations.push(url) },
      ],
    });
  });

  it('recognises chunk-load failures from the major browsers', () => {
    expect(isChunkLoadError(new TypeError('Failed to fetch dynamically imported module: https://pms.test/chunk-AB12CD34.js'))).toBeTrue();
    expect(isChunkLoadError(new TypeError('error loading dynamically imported module'))).toBeTrue();
    expect(isChunkLoadError(new TypeError('Importing a module script failed.'))).toBeTrue();
    expect(isChunkLoadError(new Error('Room type not found'))).toBeFalse();
    expect(isChunkLoadError(undefined)).toBeFalse();
  });

  it('reloads the target URL when a lazy chunk is missing', () => {
    const service = TestBed.inject(ReleaseReloadService);

    service.handleNavigationError(new NavigationError(1, '/settings/rooms', new TypeError('Failed to fetch dynamically imported module')));
    service.handleNavigationError(new NavigationError(2, '/settings/users', new Error('Forbidden')));

    expect(navigations).toEqual(['/settings/rooms']);
  });

  it('navigates normally while the release is current', async () => {
    TestBed.inject(ReleaseReloadService);
    const version = TestBed.inject(AppVersionService);
    version.observe('1.0.0');

    await TestBed.inject(Router).navigateByUrl('/one');

    expect(navigations).toEqual([]);
  });

  it('turns the next navigation into a full page load once a newer release is live', async () => {
    TestBed.inject(ReleaseReloadService);
    const version = TestBed.inject(AppVersionService);
    version.observe('1.0.0');
    version.observe('1.1.0');

    await TestBed.inject(Router).navigateByUrl('/two');

    expect(navigations).toEqual(['/two']);
  });
});
