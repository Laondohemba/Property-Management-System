import { HttpErrorResponse } from '@angular/common/http';
import { FormControl, FormGroup } from '@angular/forms';

import { Problem, applyProblemToForm, toProblem } from './problem';

describe('toProblem', () => {
  it('passes a problem+json body through unchanged', () => {
    const problem: Problem = { type: 'about:blank', title: 'Validation failed', status: 422, code: 'validation_failed' };

    expect(toProblem(new HttpErrorResponse({ status: 422, error: problem }))).toEqual(problem);
  });

  it('describes network failures', () => {
    const problem = toProblem(new HttpErrorResponse({ status: 0 }));

    expect(problem.code).toBe('network_error');
    expect(problem.detail).toContain('could not be reached');
  });

  it('keeps the message of non-problem error bodies', () => {
    const problem = toProblem(new HttpErrorResponse({ status: 423, error: { message: 'Password confirmation required.' } }));

    expect(problem.status).toBe(423);
    expect(problem.detail).toBe('Password confirmation required.');
  });
});

describe('applyProblemToForm', () => {
  const problem: Problem = {
    type: 'about:blank',
    title: 'Validation failed',
    status: 422,
    code: 'validation_failed',
    errors: [
      { field: 'email', message: 'These credentials do not match our records.' },
      { field: 'unknown', message: 'Something else went wrong.' },
    ],
  };

  it('attaches field errors to matching controls and returns the rest', () => {
    const form = new FormGroup({ email: new FormControl('') });

    const remaining = applyProblemToForm(form, problem);

    expect(form.controls.email.errors).toEqual({ server: 'These credentials do not match our records.' });
    expect(form.controls.email.touched).toBeTrue();
    expect(remaining).toBe('Something else went wrong.');
  });

  it('returns the detail when there are no field errors', () => {
    const form = new FormGroup({});

    expect(applyProblemToForm(form, { ...problem, errors: [], detail: 'Too many attempts.' })).toBe('Too many attempts.');
  });
});
