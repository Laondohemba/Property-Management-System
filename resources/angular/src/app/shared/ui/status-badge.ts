import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/** Colours follow the NiceAdmin badge palette; unknown values fall back to grey. */
const TONES: Record<string, string> = {
  clean: 'bg-success-soft text-success-fg',
  inspected: 'bg-info-soft text-info-fg',
  cleaning: 'bg-warning-soft text-warning-fg',
  dirty: 'bg-danger-soft text-danger-fg',
  out_of_order: 'bg-strong text-strong-fg',
  out_of_service: 'bg-neutral-soft text-neutral-fg',
  occupied: 'bg-info-soft text-info-fg',
  vacant: 'bg-neutral-soft text-neutral-fg',
  active: 'bg-success-soft text-success-fg',
  inactive: 'bg-neutral-soft text-neutral-fg',
};

@Component({
  selector: 'pms-status-badge',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `<span [class]="'inline-block whitespace-nowrap rounded px-2 py-0.5 text-xs font-semibold ' + tone()">{{ label() }}</span>`,
})
export class StatusBadge {
  readonly value = input.required<string>();
  readonly label = input.required<string>();

  protected readonly tone = computed(() => TONES[this.value()] ?? TONES['inactive']);
}
