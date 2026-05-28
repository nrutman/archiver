import { commonWords } from '@/data/commonWords';

export type RandomInt = (exclusiveMax: number) => number;

/**
 * Generates the user-facing archive password.
 */
export function generatePassword(randomInt: RandomInt = secureRandomInt): string {
    const tokens = [
        randomWord(randomInt),
        randomWord(randomInt),
        randomWord(randomInt),
        digitGroup(randomInt),
        digitGroup(randomInt),
    ];

    return shuffle(tokens, randomInt).join('-');
}

function randomWord(randomInt: RandomInt): string {
    return commonWords[randomInt(commonWords.length)];
}

function digitGroup(randomInt: RandomInt): string {
    return String(randomInt(1000)).padStart(3, '0');
}

function shuffle(tokens: string[], randomInt: RandomInt): string[] {
    const shuffled = [...tokens];

    for (let index = shuffled.length - 1; index > 0; index -= 1) {
        const swapIndex = randomInt(index + 1);
        [shuffled[index], shuffled[swapIndex]] = [shuffled[swapIndex], shuffled[index]];
    }

    return shuffled;
}

function secureRandomInt(exclusiveMax: number): number {
    if (!Number.isInteger(exclusiveMax) || exclusiveMax <= 0) {
        throw new Error('exclusiveMax must be a positive integer.');
    }

    const randomValues = new Uint32Array(1);
    const limit = Math.floor(0x1_0000_0000 / exclusiveMax) * exclusiveMax;

    do {
        globalThis.crypto.getRandomValues(randomValues);
    } while (randomValues[0] >= limit);

    return randomValues[0] % exclusiveMax;
}
