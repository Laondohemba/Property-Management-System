import { ChangeDetectionStrategy, Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { SessionStore } from '../../../core/auth/session.store';
import { applyProblemToForm, toProblem } from '../../../core/bff/problem';
import { Property, PropertyInput } from '../../../core/bff/types';
import { FieldError } from '../../../shared/ui/field-error';
import { PageTitle } from '../../../shared/ui/page-title';
import { PropertiesApi } from './properties.api';

function supported(key: 'timeZone' | 'currency', fallback: string[]): string[] {
  try {
    return Intl.supportedValuesOf(key);
  } catch {
    return fallback;
  }
}

/** Create (no id) or edit a property (SYS-03). */
@Component({
  selector: 'pms-property-edit-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, PageTitle, FieldError],
  templateUrl: './property-edit.page.html',
})
export class PropertyEditPage implements OnInit {
  /** Route parameter; undefined on /settings/properties/new. */
  readonly id = input<string>();

  private readonly fb = inject(NonNullableFormBuilder);
  private readonly api = inject(PropertiesApi);
  private readonly session = inject(SessionStore);
  private readonly router = inject(Router);

  protected readonly timezones = supported('timeZone', ['Africa/Lagos', 'UTC']);
  protected readonly currencies = supported('currency', ['NGN', 'USD', 'EUR', 'GBP']);

  protected readonly isNew = computed(() => !this.id());
  protected readonly property = signal<Property | null>(null);
  protected readonly loading = signal(false);
  protected readonly saving = signal(false);
  protected readonly saved = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    code: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(/^[A-Za-z0-9][A-Za-z0-9_-]*$/)]],
    timezone: [Intl.DateTimeFormat().resolvedOptions().timeZone || 'Africa/Lagos', [Validators.required]],
    currency: ['NGN', [Validators.required]],
    check_in_time: ['14:00', [Validators.required]],
    check_out_time: ['12:00', [Validators.required]],
    email: ['', [Validators.email]],
    phone: [''],
    address_line1: [''],
    address_line2: [''],
    city: [''],
    state: [''],
    postal_code: [''],
    country: ['', [Validators.pattern(/^[A-Za-z]{2}$/)]],
    is_active: [true],
  });

  ngOnInit(): void {
    const id = this.id();

    if (!id) {
      return;
    }

    this.loading.set(true);
    this.api.get(id).subscribe({
      next: (property) => {
        this.property.set(property);
        this.form.patchValue({
          ...property,
          email: property.email ?? '',
          phone: property.phone ?? '',
          address_line1: property.address_line1 ?? '',
          address_line2: property.address_line2 ?? '',
          city: property.city ?? '',
          state: property.state ?? '',
          postal_code: property.postal_code ?? '',
          country: property.country ?? '',
        });
        this.loading.set(false);
      },
      error: (error: unknown) => {
        const problem = toProblem(error);
        this.error.set(problem.detail ?? problem.title);
        this.loading.set(false);
      },
    });
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.saved.set(false);
    this.error.set(null);

    const value = this.form.getRawValue();
    const input: PropertyInput = {
      ...value,
      email: value.email || null,
      phone: value.phone || null,
      address_line1: value.address_line1 || null,
      address_line2: value.address_line2 || null,
      city: value.city || null,
      state: value.state || null,
      postal_code: value.postal_code || null,
      country: value.country || null,
    };

    const id = this.id();
    const request = id ? this.api.update(id, input) : this.api.create(input);

    request.subscribe({
      next: async (property) => {
        this.saving.set(false);
        // Property names, codes and availability appear in the shell, so refresh the session.
        await this.session.load();

        if (id) {
          this.property.set(property);
          this.saved.set(true);
        } else {
          await this.router.navigate(['/settings/properties', property.id], { replaceUrl: true });
        }
      },
      error: (error: unknown) => {
        this.saving.set(false);
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
      },
    });
  }
}
