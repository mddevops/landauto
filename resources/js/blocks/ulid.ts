const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

/** Generates a ULID used as the stable identity of a Repeater item. */
export function ulid(now: number = Date.now()): string {
    let time = '';
    let remaining = now;

    for (let index = 0; index < 10; index++) {
        time = ALPHABET[remaining % 32] + time;
        remaining = Math.floor(remaining / 32);
    }

    const random = crypto.getRandomValues(new Uint8Array(16));
    let randomPart = '';

    for (const byte of random) {
        randomPart += ALPHABET[byte % 32];
    }

    return time + randomPart;
}
