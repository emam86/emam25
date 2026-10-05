import { test } from 'node:test';
import assert from 'node:assert/strict';
import { money, durationLabel } from '../../src/lib/format.mjs';

test('money formats whole USD amounts and keeps missing prices missing', () => {
  assert.equal(money(2160), '$2,160');
  assert.equal(money(null), null);
});

test('durationLabel handles days with and without nights', () => {
  assert.equal(durationLabel({ days: 4, nights: 3 }), '4 days / 3 nights');
  assert.equal(durationLabel({ days: 1, nights: null }), '1 day');
  assert.equal(durationLabel({ days: null }), null);
});
