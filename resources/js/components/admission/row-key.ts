let counter = 0;

/**
 * A key for a row that was added in the browser, so removing a row above it does not make React reuse the
 * wrong input.
 */
export function newRowKey(): string {
    counter += 1;

    return `new-${counter}`;
}
