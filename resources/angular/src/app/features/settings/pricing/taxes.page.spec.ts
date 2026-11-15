import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { Tax } from '../../../core/bff/types';
import { TaxesPage } from './taxes.page';

const stamps = { created_at: null, updated_at: null, created_by: null, updated_by: null };
const vat: Tax = {
  id: 't1', name: 'VAT', code: 'VAT', calculation: 'percentage', calculation_label: 'Percentage of the room charge',
  rate: '7.5', amount_minor: null, is_inclusive: true, sort_order: 1, is_active: true, stamps,
};
const levy: Tax = {
  id: 't2', name: 'City levy', code: 'LEVY', calculation: 'per_night', calculation_label: 'Fixed amount per room night',
  rate: null, amount_minor: 50000, is_inclusive: false, sort_order: 2, is_active: true, stamps,
};
const meta = {
  currency: 'NGN',
  calculations: [
    { value: 'percentage', label: 'Percentage of the room charge' },
    { value: 'per_night', label: 'Fixed amount per room night' },
  ],
};

describe('TaxesPage', () => {
  let fixture: ComponentFixture<TaxesPage>;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])] });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(TaxesPage);
    fixture.detectChanges();
    http.expectOne('/bff/settings/taxes').flush({ data: [vat, levy], meta });
    fixture.detectChanges();
  });

  afterEach(() => http.verify());

  const element = () => fixture.nativeElement as HTMLElement;
  const page = () => fixture.componentInstance as unknown as { form: TaxesPage['form']; submit(): void; edit(tax: Tax): void };

  it('lists percentages and fixed fees in the property currency', () => {
    const text = element().textContent ?? '';
    expect(text).toContain('7.5%');
    expect(text).toContain('included in prices');
    expect(text).toMatch(/NGN\s?500\.00/);
    expect(text).toContain('added on top');
  });

  it('sends a fixed fee in minor units and never as inclusive', () => {
    page().form.patchValue({ name: 'Tourism levy', code: 'tl', calculation: 'per_stay', amount: 1250.5, is_inclusive: true });
    fixture.detectChanges();
    page().submit();

    const request = http.expectOne('/bff/settings/taxes');
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual(jasmine.objectContaining({ code: 'TL', calculation: 'per_stay', rate: null, amount_minor: 125050, is_inclusive: false }));
  });

  it('edits an existing tax with PATCH and shows the amount in major units', () => {
    page().edit(levy);
    fixture.detectChanges();
    expect(page().form.controls.amount.value).toBe(500);

    page().submit();
    const request = http.expectOne('/bff/settings/taxes/t2');
    expect(request.request.method).toBe('PATCH');
    request.flush({ data: levy });
    http.expectOne('/bff/settings/taxes').flush({ data: [vat, levy], meta });
  });

  it('shows the server reason on the matching field', () => {
    page().form.patchValue({ name: 'VAT 2', code: 'VAT', rate: '5' });
    page().submit();
    http.expectOne('/bff/settings/taxes').flush(
      { type: 'about:blank', title: 'Validation failed', status: 422, code: 'validation_failed', errors: [{ field: 'code', message: 'The code has already been taken.' }] },
      { status: 422, statusText: 'Unprocessable Content' },
    );
    fixture.detectChanges();

    expect(page().form.controls.code.errors?.['server']).toBe('The code has already been taken.');
  });
});
