export type InputValue = number | string;

/**
 * Apply a set of scalar input values at once: the next inputs, built in one pass, and whether the
 * set touched an input that steers a ref (so the caller re-resolves once, with the complete set).
 * Ids the calculator does not have are ignored. Setting a ref input reprices even when its value
 * is unchanged, as a single setInput always has.
 */
export function applyInputs(
    current: Record<string, InputValue>,
    patch: Record<string, InputValue>,
    refInputs: Set<string>,
): { next: Record<string, InputValue>; reprices: boolean } {
    const next = { ...current };
    let reprices = false;

    for (const [id, value] of Object.entries(patch)) {
        if (!(id in current)) {
            continue;
        }

        next[id] = value;
        reprices ||= refInputs.has(id);
    }

    return { next, reprices };
}
