/**
 * Core utility functions and domain contracts for healthcare SaaS
 */

export interface TimeSlot {
    startUtc: string;
    endUtc: string;
}

/**
 * Checks if two time intervals overlap.
 * Condition: (startA < endB) && (endA > startB)
 */
export function hasTimeOverlap(slotA: TimeSlot, slotB: TimeSlot): boolean {
    const startA = new Date(slotA.startUtc).getTime();
    const endA = new Date(slotA.endUtc).getTime();
    const startB = new Date(slotB.startUtc).getTime();
    const endB = new Date(slotB.endUtc).getTime();

    return startA < endB && endA > startB;
}

/**
 * Ensures an ISO timestamp string is strictly UTC formatted.
 */
export function toUtcIsoString(date: Date | string | number): string {
    const parsed = new Date(date);
    return parsed.toISOString();
}
