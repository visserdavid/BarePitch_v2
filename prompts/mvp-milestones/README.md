# BarePitch MVP Milestone Prompts

This folder contains executable AI prompts for the BarePitch MVP build path.

Use the milestone bundles in order:

1. `v0.1.0-first-working-vertical-slice/`
2. `v0.2.0-authentication-and-team-context/`
3. `v0.3.0-player-and-match-creation-flow/`
4. `v0.4.0-match-preparation/`
5. `v0.5.0-live-match-core/`
6. `v0.6.0-substitutions-and-cards/`
7. `v0.7.0-extra-time-penalties-and-shootout/`
8. `v0.8.0-livestream-corrections-and-audit/`
9. `v0.9.0-hardening-and-mvp-candidate/`
10. `v1.0.0-mvp-release/`

Each bundle contains smaller sequential prompts. Start with `01-shared-context.md`, then execute the numbered prompts in order.

The top-level milestone `.md` files are the original large prompts. Keep them as source references, but prefer the decomposed bundles for implementation runs.

The decomposed bundles are organized as vertical slices wherever practical. A vertical-slice implementation prompt should complete one user-visible or domain-visible action end to end: route, controller, request validation, policy, service transaction, repository persistence, view/UI state, errors, and focused tests. Shared setup prompts and final audit/testing prompts are allowed to be cross-cutting when that keeps the implementation order coherent.

Each decomposed file is written as a standalone prompt for an implementation AI. The prompts assume the AI has access to the repository and must read the relevant `/docs` files before editing code.

The milestone mapping follows `docs/BarePitch-v2-14-project-versioning-and-milestones-v1.0.md`.

Do not treat these prompts as a replacement for the source documentation. If a prompt conflicts with the docs, the docs win in the precedence order defined by `docs/BarePitch-v2-00-documentation-map.md` and `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md`.
