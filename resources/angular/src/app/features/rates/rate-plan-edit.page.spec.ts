import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { PricePreview, RatePlan, RatePlanOptions } from '../../core/bff/types';
import { RatePlanEditPage } from './rate-plan-edit.page';

const stamps = { created_at: null, updated_at: null, created_by: null, updated_by: null };
const std = { id: 'std', name: 'Standard', code: 'STD', base_occupancy: 2, max_adults: 2, max_children: 1, max_occupancy: 3, is_active: true };
const dlx = { id: 'dlx', name: 'Deluxe', code: 'DLX', base_occupancy: 2, max_adults: 3, max_children: 2, max_occupancy: 4, is_active: true };

const options: RatePlanOptions = {
  currency: 'NGN',
  meal_plans: [{ value: 'room_only', label: 'Room only' }, { value: 'breakfast', label: 'Bed and breakfast' }],
  room_types: [std, dlx],
  taxes: [{ id: 'vat', name: 'VAT', code: 'VAT', calculation: 'percentage', calculation_label: 'x', rate: '7.5', amount_minor: null, is_inclusive: true, sort_order: 1, is_active: true, stamps }],
  cancellation_policies: [{ id: 'flex', name: 'Flexible', summary: 'Free cancellation until 2 days before arrival.' }],
  deposit_policies: [],
};

const plan: RatePlan = {
  id: 'bar', name: 'Best Available Rate', code: 'BAR', description: null, meal_plan: 'room_only', meal_plan_label: 'Room only',
  cancellation_policy: { id: 'flex', name: 'Flexible', summary: 'Free cancellation until 2 days before arrival.' }, deposit_policy: null,
  min_price_minor: null, max_price_minor: null, sort_order: 0, is_active: true, currency: 'NGN',
  prices: [{ room_type: std, base_price_minor: 4500000, extra_adult_minor: 1000000, extra_child_minor: 500000 }],
  taxes: [{ id: 'vat', name: 'VAT', code: 'VAT' }],
  stamps,
};

const preview: PricePreview = {
  room_type: { id: 'std', name: 'Standard', code: 'STD' }, adults: 2, children: 0, nights: 1, base_minor: 4500000,
  extra_adults: 0, extra_adult_minor: 0, extra_children: 0, extra_child_minor: 0, nightly_minor: 4500000, clamped: false,
  room_charge_minor: 4500000,
  taxes: { currency: 'NGN', charge_minor: 4500000, net_minor: 4186047, inclusive_minor: 313953, exclusive_minor: 0, total_minor: 4500000,
    lines: [{ tax_id: 'vat', name: 'VAT', code: 'VAT', basis: '7.5% included', amount_minor: 313953, is_inclusive: true }] },
  cancellation_summary: 'Free cancellation until 2 days before arrival.', deposit_summary: null, currency: 'NGN',
};

describe('RatePlanEditPage', () => {
  let fixture: ComponentFixture<RatePlanEditPage>;
  let http: HttpTestingController;

  function create(id?: string): void {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])] });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(RatePlanEditPage);
    if (id) {
      fixture.componentRef.setInput('id', id);
    }
    fixture.detectChanges();
  }

  afterEach(() => http.verify());

  const page = () => fixture.componentInstance as unknown as { form: RatePlanEditPage['form']; submit(): void };
  const text = () => (fixture.nativeElement as HTMLElement).textContent ?? '';

  it('starts a new plan with every active room type and tax selected', () => {
    create();
    http.expectOne('/bff/rate-plans/options').flush({ data: options });
    fixture.detectChanges();

    page().form.patchValue({ name: 'Bed and Breakfast', code: 'bb', meal_plan: 'breakfast' });
    page().form.controls.prices.at(0).patchValue({ base: 52000, extra_adult: 10000, extra_child: 5000 });
    page().form.controls.prices.at(1).patchValue({ sold: false });
    // The base price is required only while "Sell" is ticked; let the template update first.
    fixture.detectChanges();
    page().submit();

    const request = http.expectOne('/bff/rate-plans');
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual(jasmine.objectContaining({
      code: 'BB',
      tax_ids: ['vat'],
      prices: [{ room_type_id: 'std', base_price_minor: 5200000, extra_adult_minor: 1000000, extra_child_minor: 500000 }],
      change_reason: null,
    }));
  });

  it('loads an existing plan, previews it, and saves price changes with a reason', () => {
    create('bar');
    http.expectOne('/bff/rate-plans/options').flush({ data: options });
    http.expectOne('/bff/rate-plans/bar').flush({ data: plan });
    http.expectOne((req) => req.url === '/bff/rate-plans/bar/preview' && req.params.get('room_type_id') === 'std').flush({ data: preview });
    fixture.detectChanges();

    expect(page().form.controls.prices.at(0).controls.base.value).toBe(45000);
    expect(page().form.controls.prices.at(1).controls.sold.value).toBeFalse();
    expect(text()).toContain('Guest pays');
    expect(text()).toContain('7.5% included');
    expect(text()).toContain('Free cancellation until 2 days before arrival.');

    page().form.controls.prices.at(0).patchValue({ base: 48000 });
    page().form.patchValue({ change_reason: 'Festive season' });
    page().submit();

    const request = http.expectOne('/bff/rate-plans/bar');
    expect(request.request.method).toBe('PATCH');
    expect(request.request.body.prices[0].base_price_minor).toBe(4800000);
    expect(request.request.body.change_reason).toBe('Festive season');
    request.flush({ data: { ...plan, prices: [{ ...plan.prices[0], base_price_minor: 4800000 }] } });
    http.expectOne((req) => req.url === '/bff/rate-plans/bar/preview').flush({ data: preview });
    fixture.detectChanges();

    expect(text()).toContain('Rate plan saved.');
  });
});
