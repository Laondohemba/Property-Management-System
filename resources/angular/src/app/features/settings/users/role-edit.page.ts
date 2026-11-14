import { ChangeDetectionStrategy, Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable, map } from 'rxjs';

import { applyProblemToForm, toProblem } from '../../../core/bff/problem';
import { PermissionGroup, RoleDetail } from '../../../core/bff/types';
import { FieldError } from '../../../shared/ui/field-error';
import { PageTitle } from '../../../shared/ui/page-title';
import { StaffApi } from './staff.api';

/**
 * View a built-in role, or create/edit a custom role and its permission matrix (SYS-02).
 * /settings/roles/new?from=<role> duplicates an existing role.
 */
@Component({
  selector: 'pms-role-edit-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, PageTitle, FieldError],
  templateUrl: './role-edit.page.html',
})
export class RoleEditPage implements OnInit {
  /** Route parameter (absent when creating) and ?from= for duplication. */
  readonly name = input<string>();
  readonly from = input<string>();

  private readonly api = inject(StaffApi);
  private readonly router = inject(Router);

  protected readonly role = signal<RoleDetail | null>(null);
  protected readonly groups = signal<PermissionGroup[]>([]);
  protected readonly selected = signal<Set<string>>(new Set());
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly saved = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly isNew = computed(() => !this.name());
  protected readonly readOnly = computed(() => this.role()?.is_system ?? false);

  protected readonly form = inject(NonNullableFormBuilder).group({
    label: ['', [Validators.required, Validators.maxLength(100)]],
    description: [''],
    requires_two_factor: [false],
  });

  ngOnInit(): void {
    const source = this.name() ?? this.from();

    // Creating from scratch only needs the permission catalogue.
    const load$: Observable<{ role: RoleDetail | null; groups: PermissionGroup[] }> = source
      ? this.api.role(source)
      : this.api.roles().pipe(map(({ groups }) => ({ role: null, groups })));

    load$.subscribe({
      next: ({ role, groups }) => {
        this.groups.set(groups);

        if (role && this.name()) {
          this.role.set(role);
          this.form.patchValue({ label: role.label, description: role.description ?? '', requires_two_factor: role.requires_two_factor });
          this.selected.set(new Set(role.is_super_admin ? groups.flatMap((g) => g.permissions.map((p) => p.name)) : role.permissions));
        } else if (role) {
          // Duplicating: start from the source role's permissions.
          this.form.patchValue({ label: `${role.label} (copy)`, requires_two_factor: role.requires_two_factor });
          this.selected.set(new Set(role.permissions));
        }

        this.loading.set(false);
      },
      error: (error: unknown) => {
        const problem = toProblem(error);
        this.error.set(problem.detail ?? problem.title);
        this.loading.set(false);
      },
    });
  }

  protected isChecked(permission: string): boolean {
    return this.selected().has(permission);
  }

  protected toggle(permission: string, checked: boolean): void {
    this.selected.update((current) => {
      const next = new Set(current);
      checked ? next.add(permission) : next.delete(permission);
      return next;
    });
  }

  protected toggleGroup(group: PermissionGroup, checked: boolean): void {
    this.selected.update((current) => {
      const next = new Set(current);
      group.permissions.forEach((p) => (checked ? next.add(p.name) : next.delete(p.name)));
      return next;
    });
  }

  protected groupState(group: PermissionGroup): 'all' | 'some' | 'none' {
    const count = group.permissions.filter((p) => this.selected().has(p.name)).length;
    return count === 0 ? 'none' : count === group.permissions.length ? 'all' : 'some';
  }

  protected submit(): void {
    if (!this.readOnly() && this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.saved.set(false);
    this.error.set(null);

    const value = this.form.getRawValue();
    const name = this.name();
    const request = this.readOnly()
      ? this.api.updateRole(name!, { requires_two_factor: value.requires_two_factor })
      : name
        ? this.api.updateRole(name, { ...value, description: value.description || null, permissions: [...this.selected()] })
        : this.api.createRole({ ...value, description: value.description || null, permissions: [...this.selected()] });

    request.subscribe({
      next: (role) => {
        this.saving.set(false);
        if (name) {
          this.role.set(role);
          this.saved.set(true);
        } else {
          void this.router.navigate(['/settings/roles', role.name], { replaceUrl: true });
        }
      },
      error: (error: unknown) => {
        this.saving.set(false);
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
      },
    });
  }

  protected remove(): void {
    const role = this.role();
    if (!role || !confirm(`Delete the role "${role.label}"?`)) {
      return;
    }

    this.api.deleteRole(role.name).subscribe({
      next: () => void this.router.navigate(['/settings/roles']),
      error: (error: unknown) => {
        const problem = toProblem(error);
        this.error.set(problem.detail ?? problem.title);
      },
    });
  }

}
