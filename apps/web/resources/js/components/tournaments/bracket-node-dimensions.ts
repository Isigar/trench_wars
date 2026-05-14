// Source: 06-12-PLAN.md Task 2 + 06-RESEARCH.md Pattern 8.
//
// Extracted from BracketNode.vue (07-10 plan task 1 — Rule 3 blocking fix).
// Vue 3 `<script setup>` cannot host `export const` — Vue's compiler-sfc rejects
// module-level exports inside <script setup> blocks (the script-setup RFC enforces
// component-scope only). The dimension constants are imported by BracketCanvas.vue
// to compute viewBox sizing + edge anchor points, so a sibling `.ts` constants
// module is the canonical place for them.

export const NODE_WIDTH = 200;
export const NODE_HEIGHT = 60;
