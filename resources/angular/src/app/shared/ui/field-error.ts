import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { AbstractControl } from '@angular/forms';

/** Shows the first validation message for a control once the user has interacted with it. */
@Component({
  selector: 'pms-field-error',
  changeDetection: ChangeDetectionStrategy.Default,
  template: `
    @if (message(); as text) {
      <p class="pms-field-error" role="alert">{{ text }}</p>
    }
  `,
})
export class FieldError {
  readonly control = input.required<AbstractControl>();

  protected message(): string | null {
    const control = this.control();

    if (!control.errors || !(control.touched || control.dirty)) {
      return null;
    }

    const errors = control.errors;

    if (typeof errors['server'] === 'string') return errors['server'];
    if (errors['required']) return 'This field is required.';
    if (errors['email']) return 'Enter a valid email address.';
    if (errors['pattern']) return 'The format is not valid.';
    if (errors['minlength']) return `Use at least ${errors['minlength'].requiredLength} characters.`;

    return 'This value is not valid.';
  }
}
