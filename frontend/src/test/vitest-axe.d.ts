// jest-axe ships matcher types for Jest's `expect`, not Vitest's. The a11y
// suite calls `expect.extend(toHaveNoViolations)` at runtime, so the matcher
// exists — it just isn't declared for Vitest's Assertion interface, which
// made `tsc` fail on every `toHaveNoViolations()` call.
import 'vitest';

interface AxeMatchers<R = unknown> {
    toHaveNoViolations(): R;
}

declare module 'vitest' {
    interface Assertion<T = any> extends AxeMatchers<T> {}
    interface AsymmetricMatchersContaining extends AxeMatchers {}
}
