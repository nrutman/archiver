# Contributing

## Development workflow

1. Identify a clear unit of work.
2. Create a branch for that unit of work. Do not commit directly to `main`.
3. Implement the smallest useful change for the unit.
4. Run the relevant local checks.
5. Run 2-3 `review-pr-with-sub-agents` cycles before asking for human review. Keep review follow-up scoped to the current unit of work.
6. Make sure CI passes.
7. Ask a human for review and offer to squash-merge when they are ready.

All PRs should be squash-merged into `main`.

Because production may not have the frontend toolchain, `public/build` is committed. CI verifies that those built assets match the frontend source after rebuilding them. If a frontend change updates the build output, either rebuild assets locally and commit `public/build`, or configure the `BUILD_ASSETS_COMMIT_TOKEN` repository secret so CI can push generated asset updates to same-repository PR branches. Use a fine-grained token scoped to this repository's contents, and do not allow it to bypass protected `main`.

## Setup

```bash
make setup
```

## Development servers

Run these in separate terminals:

```bash
make dev-backend
make dev-frontend
```

## Checks

```bash
make check
```

CI runs the full frontend lint/type/test/build suite. On a development machine with the frontend toolchain installed, run those checks locally with:

```bash
make frontend-check
```

Or run targeted checks:

```bash
composer check
make check-built-assets
```

## Testing expectations

For each unit of work, review the test suite and ask:

1. Are there any high-value test cases I should write?
2. Are there ways the tests can be consolidated or simplified?
3. Are there any low-value tests I can remove?

Prefer tests that cover user-visible behavior, security boundaries, validation, temporary-file cleanup guarantees, and archive compatibility. Avoid brittle tests that only duplicate implementation details.

## Documentation expectations

Keep docs close to the code they explain. Add or update README files for directories with architectural responsibilities, entry points, deployment assumptions, or operational expectations that would help a new human or agent understand the codebase.
