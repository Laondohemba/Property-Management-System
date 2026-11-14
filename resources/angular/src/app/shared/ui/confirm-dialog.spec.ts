import { TestBed } from '@angular/core/testing';

import { ConfirmService } from './confirm-dialog';

describe('ConfirmService', () => {
  let service: ConfirmService;

  beforeEach(() => {
    service = TestBed.inject(ConfirmService);
  });

  afterEach(() => document.querySelectorAll('.cdk-overlay-container').forEach((element) => (element.innerHTML = '')));

  function dialog(): HTMLElement {
    const element = document.querySelector<HTMLElement>('pms-confirm-dialog');
    expect(element).withContext('dialog should be open').not.toBeNull();
    return element!;
  }

  it('shows the title and message in an accessible modal', async () => {
    const answer = service.ask({ title: 'Remove amenity', message: '"Balcony" will be removed.', confirmLabel: 'Remove', tone: 'danger' });
    TestBed.tick();

    const element = dialog();
    expect(element.getAttribute('role')).toBe('alertdialog');
    expect(element.textContent).toContain('Remove amenity');
    expect(element.textContent).toContain('"Balcony" will be removed.');
    expect(element.querySelector('.pms-btn-danger')?.textContent?.trim()).toBe('Remove');

    element.querySelector<HTMLButtonElement>('.pms-btn-danger')!.click();
    expect(await answer).toBeTrue();
  });

  it('resolves false when cancelled', async () => {
    const answer = service.ask({ title: 'Delete role', message: 'Sure?' });
    TestBed.tick();

    dialog().querySelector<HTMLButtonElement>('.pms-btn-secondary')!.click();

    expect(await answer).toBeFalse();
  });

  it('resolves false when dismissed with Escape', async () => {
    const answer = service.ask({ title: 'Deactivate account', message: 'Sure?' });
    TestBed.tick();

    dialog().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, bubbles: true }));

    expect(await answer).toBeFalse();
  });
});
