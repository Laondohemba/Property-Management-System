import { ChangeDetectionStrategy, Component, input } from '@angular/core';

/** Placeholder brand mark. Replace with the final PMS logo. */
@Component({
  selector: 'pms-logo',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { style: 'display: contents' },
  template: `
    <svg [attr.class]="size()" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <rect width="32" height="32" rx="7" fill="#4154f1" />
      <path d="M9 24V10.5L16 7l7 3.5V24" fill="none" stroke="#fff" stroke-width="2" stroke-linejoin="round" />
      <path d="M13 13h2m2 0h2m-6 4h2m2 0h2M14 24v-3h4v3" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" />
    </svg>
  `,
})
export class Logo {
  readonly size = input('size-[26px]');
}
