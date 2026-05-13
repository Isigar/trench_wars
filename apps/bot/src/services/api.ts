// Trenchwars bot — Web API client.
//
// Source: .planning/phases/05-discord-bot-v1/05-08-PLAN.md task 2 (Wave 6),
// RESEARCH §Example 1 verbatim. Sanctum bearer-token client implemented over
// undici's `fetch` (Node 22, native ESM).
//
// CRITICAL — Pitfall 3 / T-05-08-01: Web API responses (especially 4xx/5xx
// bodies) may echo headers or unredacted token fragments. Every non-OK
// response runs `text.replaceAll(env.WEB_API_TOKEN, '[REDACTED]')` BEFORE
// throwing — the propagated Error message MUST NEVER contain the plaintext
// token because Railway log aggregators capture stderr verbatim. Scrubbing in
// the catch site (e.g., the slash command handler) is too late.
//
// `actsAsDiscordId` is forwarded as `X-Bot-Acts-As-User` (see plan 05-04
// ResolveBotActsAsUserMiddleware). Set it for every per-user action (slash
// command, button click); omit it for service-level polls (outbound queue).

import { fetch, Headers } from 'undici';

import { env } from '../env.js';

export interface CallOptions {
    actsAsDiscordId?: string;
    body?: unknown;
    method?: 'GET' | 'POST' | 'DELETE';
}

export const api = {
    async request<T>(path: string, opts: CallOptions = {}): Promise<T> {
        const headers = new Headers({
            Accept: 'application/json',
            Authorization: `Bearer ${env.WEB_API_TOKEN}`,
        });
        if (opts.body !== undefined) {
            headers.set('Content-Type', 'application/json');
        }
        if (opts.actsAsDiscordId !== undefined) {
            headers.set('X-Bot-Acts-As-User', opts.actsAsDiscordId);
        }

        const method = opts.method ?? (opts.body !== undefined ? 'POST' : 'GET');

        const res = await fetch(`${env.WEB_API_URL}/api/bot${path}`, {
            method,
            headers,
            body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
        });

        if (!res.ok) {
            const text = await res.text().catch(() => '<unreadable>');
            const scrubbed = text.replaceAll(env.WEB_API_TOKEN, '[REDACTED]');
            throw new Error(
                `Bot API ${method} ${path} -> ${res.status}: ${scrubbed.slice(0, 500)}`,
            );
        }

        return (await res.json()) as T;
    },

    get<T>(path: string, opts: Omit<CallOptions, 'method' | 'body'> = {}): Promise<T> {
        return this.request<T>(path, { ...opts, method: 'GET' });
    },

    post<T>(path: string, body: unknown, opts: Omit<CallOptions, 'method' | 'body'> = {}): Promise<T> {
        return this.request<T>(path, { ...opts, method: 'POST', body });
    },

    delete<T>(path: string, opts: Omit<CallOptions, 'method' | 'body'> = {}): Promise<T> {
        return this.request<T>(path, { ...opts, method: 'DELETE' });
    },
};
