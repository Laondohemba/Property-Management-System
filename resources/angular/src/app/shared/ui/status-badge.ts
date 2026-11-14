import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/** Colours follow the NiceAdmin badge palette; unknown values fall back to grey. */
const TONES: Record<string, string> = {
  clean: 'bg-[#d1e7dd] text-[#0f5132]',
  inspected: 'bg-[#cfe2ff] text-[#084298]',
  cleaning: 'bg-[#fff3cd] text-[#664d03]',
  dirty: 'bg-[#f8d7da] text-[#842029]',
  out_of_order: 'bg-[#212529] text-white',
  out_of_service: 'bg-[#e2e3e5] text-[#41464b]',
  occupied: 'bg-[#cfe2ff] text-[#084298]',
  vacant: 'bg-[#e2e3e5] text-[#41464b]',
  active: 'bg-[#d1e7dd] text-[#0f5132]',
  inactive: 'bg-[#e2e3e5] text-[#41464b]',
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
