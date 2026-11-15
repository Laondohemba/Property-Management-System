import { formatMoney, fractionDigits, fromMinor, moneyStep, toMinor } from './money';

describe('money', () => {
  it('knows how many decimals a currency has', () => {
    expect(fractionDigits('NGN')).toBe(2);
    expect(fractionDigits('JPY')).toBe(0);
    expect(fractionDigits('KWD')).toBe(3);
  });

  it('converts major units to minor units without float drift', () => {
    expect(toMinor('45000.5', 'NGN')).toBe(4500050);
    expect(toMinor(0.1 + 0.2, 'NGN')).toBe(30);
    expect(toMinor('1,250.75', 'NGN')).toBe(125075);
    expect(toMinor(500, 'JPY')).toBe(500);
    expect(toMinor('', 'NGN')).toBeNull();
    expect(toMinor(null, 'NGN')).toBeNull();
    expect(toMinor('abc', 'NGN')).toBeNull();
  });

  it('converts back for form inputs', () => {
    expect(fromMinor(4500050, 'NGN')).toBe(45000.5);
    expect(fromMinor(1234, 'KWD')).toBe(1.234);
    expect(fromMinor(null, 'NGN')).toBeNull();
  });

  it('formats with the currency code and gives input steps', () => {
    expect(formatMoney(4500000, 'NGN', 'en')).toContain('45,000.00');
    expect(formatMoney(4500000, 'NGN', 'en')).toContain('NGN');
    expect(formatMoney(null, 'NGN')).toBe('—');
    expect(moneyStep('NGN')).toBe('0.01');
    expect(moneyStep('JPY')).toBe('1');
  });
});
