import { HttpErrorResponse } from '@angular/common/http';
import { FormGroup } from '@angular/forms';

import type { FieldErrorData, ProblemData } from './generated/bff';

/** RFC 9457 problem+json document as produced by the BFF (spec §8.4); generated from ProblemData. */
export type Problem = ProblemData;
export type FieldError = FieldErrorData;

function isProblem(value: unknown): value is Problem {
  return typeof value === 'object' && value !== null && 'status' in value && 'code' in value;
}

/** Normalise any HTTP failure into a Problem so screens handle one shape. */
export function toProblem(error: unknown): Problem {
  if (error instanceof HttpErrorResponse) {
    if (isProblem(error.error)) {
      return error.error;
    }

    const body: unknown = error.error;
    const message =
      typeof body === 'object' && body !== null && 'message' in body && typeof body.message === 'string'
        ? body.message
        : undefined;

    return {
      type: 'about:blank',
      title: error.status === 0 ? 'Network error' : error.statusText || 'Request failed',
      status: error.status,
      code: error.status === 0 ? 'network_error' : 'http_error',
      ...(error.status === 0
        ? { detail: 'The server could not be reached. Check your connection and try again.' }
        : message !== undefined
          ? { detail: message }
          : {}),
    };
  }

  return { type: 'about:blank', title: 'Unexpected error', status: 0, code: 'client_error' };
}

/**
 * Map server field errors onto matching form controls (as a `server` error) and
 * return any message that could not be attached to a control.
 */
export function applyProblemToForm(form: FormGroup, problem: Problem): string | null {
  const errors = problem.errors ?? [];

  if (errors.length === 0) {
    return problem.detail ?? problem.title;
  }

  const unmatched: string[] = [];

  for (const { field, message } of errors) {
    const control = form.get(field);

    if (control) {
      control.setErrors({ ...(control.errors ?? {}), server: message });
      control.markAsTouched();
    } else {
      unmatched.push(message);
    }
  }

  return unmatched.length > 0 ? unmatched.join(' ') : null;
}
