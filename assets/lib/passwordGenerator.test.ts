import { describe, expect, it } from 'vitest';

import { generatePassword, type RandomInt } from './passwordGenerator';

describe('generatePassword', () => {
    it('generates three words and two three-digit groups in randomized order', () => {
        const values = [0, 7, 20, 4, 42, 4, 3, 2, 1];
        const randomInt: RandomInt = (exclusiveMax) => {
            const value = values.shift();
            if (value === undefined) {
                throw new Error('No mocked random values remain.');
            }

            return value % exclusiveMax;
        };

        const password = generatePassword(randomInt);
        const tokens = password.split('-');

        expect(tokens).toHaveLength(5);
        expect(tokens.filter((token) => /^\d{3}$/.test(token))).toHaveLength(2);
        expect(tokens.filter((token) => /^[a-z]{5,9}$/.test(token))).toHaveLength(3);
        expect(password).toMatch(/^[a-z0-9]+(-[a-z0-9]+){4}$/);
    });
});
