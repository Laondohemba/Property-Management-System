/**
 * Money in the SPA: the BFF sends and receives integer minor units (kobo, cents) with an
 * ISO-4217 currency (spec §2.8). Forms show major units and convert on the way in and out.
 */

const digitsCache = new Map<string, number>();

/** Decimal places of a currency (NGN 2, JPY 0, KWD 3). */
export function fractionDigits(currency: string): number {
  if (!digitsCache.has(currency)) {
    const digits = new Intl.NumberFormat('en', { style: 'currency', currency }).resolvedOptions().maximumFractionDigits ?? 2;
    digitsCache.set(currency, digits);
  }
  return digitsCache.get(currency)!;
}

/** "45000.5" or 45000.5 → 4500050 for NGN; null for empty input. */
export function toMinor(major: string | number | null | undefined, currency: string): number | null {
  if (major === null || major === undefined || `${major}`.trim() === '') {
    return null;
  }
  const value = Number(`${major}`.replace(/,/g, ''));
  if (!Number.isFinite(value)) {
    return null;
  }
  return Math.round(value * 10 ** fractionDigits(currency));
}

/** 4500050 → 45000.5 for NGN, for form inputs. */
export function fromMinor(minor: number | null | undefined, currency: string): number | null {
  return minor === null || minor === undefined ? null : minor / 10 ** fractionDigits(currency);
}

/** 4500050 → "NGN 45,000.50" (code rather than symbol, which reads unambiguously in admin screens). */
export function formatMoney(minor: number | null | undefined, currency: string, locale?: string): string {
  if (minor === null || minor === undefined) {
    return '—';
  }
  return new Intl.NumberFormat(locale, { style: 'currency', currency, currencyDisplay: 'code' }).format(minor / 10 ** fractionDigits(currency));
}

/** Input step for a currency, e.g. 0.01 for NGN. */
export function moneyStep(currency: string): string {
  const digits = fractionDigits(currency);
  return digits === 0 ? '1' : `0.${'0'.repeat(digits - 1)}1`;
}
