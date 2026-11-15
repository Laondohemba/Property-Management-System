import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { InventoryBlock, InventoryDay, InventoryGrid } from '../../core/bff/types';
import { IDEMPOTENT } from '../../core/http/context';
import { ConfirmService } from '../../shared/ui/confirm-dialog';
import { InventoryPage, addDays, unavailableNights } from './inventory.page';

function day(date: string, total: number, blocked = 0, sold = 0, allowance = 0): InventoryDay {
  const available = total - blocked - sold;
  return { date, total, blocked, sold, overbook_allowance: allowance, available, sellable: available + allowance };
}

function grid(from: string): InventoryGrid {
  const dates = Array.from({ length: 14 }, (_, i) => addDays(from, i));
  return {
    from,
    to: dates[13],
    first_date: '2026-12-01',
    last_date: '2028-11-30',
    dates,
    rows: [
      { room_type: { id: 'std', name: 'Standard', code: 'STD' }, is_active: true, days: dates.map((d, i) => day(d, 4, i === 2 ? 4 : 0)) },
      { room_type: { id: 'dlx', name: 'Deluxe', code: 'DLX' }, is_active: true, days: dates.map((d) => day(d, 5, 0, 0, 1)) },
    ],
    totals: dates.map((d, i) => day(d, 9, i === 2 ? 4 : 0)),
  };
}

const meta = { block_types: [{ value: 'out_of_order' as const, label: 'Out of order' }, { value: 'hold' as const, label: 'Hold' }] };

const block: InventoryBlock = {
  id: 'b1', room_type: { id: 'std', name: 'Standard', code: 'STD' }, type: 'out_of_order', type_label: 'Out of order', quantity: 2,
  from_date: '2026-12-03', to_date: '2026-12-04', reason: 'Refit', expires_at: null, released_at: null, released_by: null, status: 'active',
  stamps: { created_at: null, updated_at: null, created_by: null, updated_by: null },
};

describe('addDays', () => {
  it('moves across month and year ends', () => {
    expect(addDays('2026-12-31', 1)).toBe('2027-01-01');
    expect(addDays('2027-03-01', -1)).toBe('2027-02-28');
  });
});

describe('unavailableNights', () => {
  it('reads the nights from an inventory_unavailable problem', () => {
    const problem = { type: 'about:blank', title: 'x', status: 409, code: 'inventory_unavailable', nights: [{ date: '2026-12-10', available: 0 }] };
    expect(unavailableNights(problem)).toEqual([{ date: '2026-12-10', available: 0 }]);
    expect(unavailableNights({ type: 'about:blank', title: 'x', status: 500, code: 'server_error' })).toEqual([]);
  });
});

describe('InventoryPage', () => {
  let fixture: ComponentFixture<InventoryPage>;
  let http: HttpTestingController;
  let confirmAnswer: boolean;

  beforeEach(() => {
    confirmAnswer = true;
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: ConfirmService, useValue: { ask: () => Promise.resolve(confirmAnswer) } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(InventoryPage);
    fixture.detectChanges();

    // First request uses the browser's date; the page then moves to the business date.
    http.expectOne((req) => req.url === '/bff/inventory').flush({ data: grid('2026-11-20'), meta });
    http.expectOne((req) => req.url === '/bff/inventory' && req.params.get('from') === '2026-12-01').flush({ data: grid('2026-12-01'), meta });
    http.expectOne((req) => req.url === '/bff/inventory/blocks').flush({ data: [block], meta: { page: 1, per_page: 50, total: 1, last_page: 1 } });
    fixture.detectChanges();
  });

  afterEach(() => http.verify());

  const text = () => (fixture.nativeElement as HTMLElement).textContent ?? '';

  it('shows room types by night with a totals row and colours full nights', () => {
    const element = fixture.nativeElement as HTMLElement;
    const rows = element.querySelector('table')!.querySelectorAll('tbody tr');

    expect(rows.length).toBe(3);
    expect(rows[0].textContent).toContain('Standard');
    expect(rows[2].textContent).toContain('All room types');
    expect(rows[0].querySelectorAll('td')[2].className).toContain('bg-danger-soft');
    expect(rows[1].querySelector('td')?.getAttribute('aria-label')).toContain('overbooking allowance 1');
  });

  it('moves the window by the number of nights shown', () => {
    (fixture.nativeElement as HTMLElement).querySelector<HTMLButtonElement>('[aria-label="Later nights"]')!.click();

    const request = http.expectOne((req) => req.url === '/bff/inventory');
    expect(request.request.params.get('from')).toBe('2026-12-15');
    expect(request.request.params.get('to')).toBe('2026-12-28');
    request.flush({ data: grid('2026-12-15'), meta });
  });

  it('creates a block with an idempotency key and shows refused nights', () => {
    const component = fixture.componentInstance as unknown as { blockForm: InventoryPage['blockForm']; submitBlock(): void };
    component.blockForm.patchValue({ reason: 'Owner stay', quantity: 3, from_date: '2026-12-09', to_date: '2026-12-11' });
    component.submitBlock();

    const request = http.expectOne('/bff/inventory/blocks');
    expect(request.request.context.get(IDEMPOTENT)).toBeTrue();
    expect(request.request.body).toEqual(jasmine.objectContaining({ room_type_id: 'std', quantity: 3, reason: 'Owner stay', expires_at: null }));

    request.flush(
      { type: 'about:blank', title: 'Not enough inventory', status: 409, code: 'inventory_unavailable', detail: '3 units are not free on every night.', nights: [{ date: '2026-12-10', available: 1 }] },
      { status: 409, statusText: 'Conflict' },
    );
    fixture.detectChanges();

    expect(text()).toContain('3 units are not free on every night.');
    expect(text()).toContain('1 free');
  });

  it('clears the form without showing errors after a block is created', () => {
    const component = fixture.componentInstance as unknown as { blockForm: InventoryPage['blockForm']; submitBlock(): void };
    component.blockForm.patchValue({ reason: 'Owner stay' });
    component.submitBlock();

    http.expectOne('/bff/inventory/blocks').flush({ data: { ...block, reason: 'Owner stay' } });
    http.expectOne((req) => req.url === '/bff/inventory').flush({ data: grid('2026-12-01'), meta });
    http.expectOne((req) => req.url === '/bff/inventory/blocks').flush({ data: [block], meta: { page: 1, per_page: 50, total: 1, last_page: 1 } });
    fixture.detectChanges();

    expect(component.blockForm.controls.reason.value).toBe('');
    expect(component.blockForm.controls.reason.pristine).toBeTrue();
    expect(text()).not.toContain('This field is required');
  });

  it('releases a block only after confirmation', async () => {
    const release = () => Array.from((fixture.nativeElement as HTMLElement).querySelectorAll('button')).find((b) => b.textContent?.trim() === 'Release')!;

    confirmAnswer = false;
    release().click();
    await fixture.whenStable();
    http.expectNone('/bff/inventory/blocks/b1/release');

    confirmAnswer = true;
    release().click();
    await fixture.whenStable();
    http.expectOne('/bff/inventory/blocks/b1/release').flush({ data: { ...block, status: 'released' } });
    http.expectOne((req) => req.url === '/bff/inventory').flush({ data: grid('2026-12-01'), meta });
    http.expectOne((req) => req.url === '/bff/inventory/blocks').flush({ data: [], meta: { page: 1, per_page: 50, total: 0, last_page: 1 } });
    fixture.detectChanges();

    expect(text()).toContain('back on sale');
  });

  it('sends chosen weekdays for overbooking, or none when every day is chosen', () => {
    const component = fixture.componentInstance as unknown as {
      overbookingForm: InventoryPage['overbookingForm'];
      submitOverbooking(): void;
      toggleDay(day: number, checked: boolean): void;
    };
    component.overbookingForm.patchValue({ allowance: 2 });
    component.submitOverbooking();
    expect(http.expectOne('/bff/inventory/overbooking').request.body.weekdays).toBeNull();

    [1, 2, 3, 4, 7].forEach((d) => component.toggleDay(d, false));
    component.submitOverbooking();
    const request = http.expectOne('/bff/inventory/overbooking');
    expect(request.request.body.weekdays).toEqual([5, 6]);
    request.flush({ data: { nights_changed: 2 } });
    http.expectOne((req) => req.url === '/bff/inventory').flush({ data: grid('2026-12-01'), meta });
    fixture.detectChanges();

    expect(text()).toContain('Overbooking updated on 2 nights.');
  });
});
