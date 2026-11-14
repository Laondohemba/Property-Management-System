import { TestBed } from '@angular/core/testing';

import { ThemeService } from './theme.service';

describe('ThemeService', () => {
  const root = document.documentElement;

  beforeEach(() => {
    localStorage.removeItem('pms.theme');
    delete root.dataset['theme'];
  });

  afterEach(() => localStorage.removeItem('pms.theme'));

  function create(): ThemeService {
    const service = TestBed.inject(ThemeService);
    TestBed.tick();
    return service;
  }

  it('follows the operating system by default', () => {
    const service = create();
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    expect(service.preference()).toBe('system');
    expect(root.dataset['theme']).toBe(systemDark ? 'dark' : 'light');
  });

  it('applies and remembers an explicit choice', () => {
    const service = create();

    service.setPreference('dark');
    TestBed.tick();

    expect(root.dataset['theme']).toBe('dark');
    expect(localStorage.getItem('pms.theme')).toBe('dark');

    service.setPreference('light');
    TestBed.tick();

    expect(root.dataset['theme']).toBe('light');
  });

  it('forgets the override when going back to system', () => {
    const service = create();
    service.setPreference('dark');
    service.setPreference('system');

    expect(localStorage.getItem('pms.theme')).toBeNull();
  });

  it('restores a saved choice on start', () => {
    localStorage.setItem('pms.theme', 'dark');

    expect(create().theme()).toBe('dark');
  });
});
