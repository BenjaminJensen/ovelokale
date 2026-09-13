/**
 * Vitest setup — a `window.matchMedia` stub.
 *
 * jsdom implements no `matchMedia` at all (not a no-op returning `false`: the
 * property is simply absent), so anything calling it throws. `useIsPhone` does,
 * which means every test mounting the calendar needs this. The stub understands
 * one query shape — `(max-width: <n>px)`, which is all `PHONE_QUERY` uses — and
 * `setViewportWidth` re-evaluates the live queries and notifies their
 * listeners, so a test can prove the app reacts to a resize rather than only to
 * its starting width.
 */
import { beforeEach } from 'vitest'

/** The Playwright desktop project's width, so unit tests default to the desktop layout. */
const DEFAULT_WIDTH = 1440

const MAX_WIDTH_QUERY = /^\(max-width:\s*(\d+)px\)$/

type Listener = (event: MediaQueryListEvent) => void

interface Query {
  media: string
  matches: boolean
  listeners: Set<Listener>
}

let currentWidth = DEFAULT_WIDTH

const queries = new Set<Query>()

function evaluate(media: string): boolean {
  const match = MAX_WIDTH_QUERY.exec(media)
  if (!match) {
    throw new Error(`The matchMedia stub only understands "(max-width: <n>px)", got "${media}"`)
  }

  return currentWidth <= Number(match[1])
}

function createList(media: string): MediaQueryList {
  const query: Query = { media, matches: evaluate(media), listeners: new Set() }
  queries.add(query)

  return {
    media,
    // A getter, because `MediaQueryList.matches` is read-only in lib.dom —
    // `setViewportWidth` updates the backing record instead.
    get matches() {
      return query.matches
    },
    onchange: null,
    addEventListener: (_type: string, listener: Listener) => void query.listeners.add(listener),
    removeEventListener: (_type: string, listener: Listener) =>
      void query.listeners.delete(listener),
    addListener: (listener: Listener) => void query.listeners.add(listener),
    removeListener: (listener: Listener) => void query.listeners.delete(listener),
    dispatchEvent: () => true,
  } as unknown as MediaQueryList
}

/** Resize the stubbed viewport, re-evaluating every live query and notifying its listeners. */
export function setViewportWidth(width: number): void {
  currentWidth = width

  for (const query of queries) {
    const matches = evaluate(query.media)
    if (matches === query.matches) continue

    query.matches = matches
    for (const listener of query.listeners) {
      listener({ matches, media: query.media } as MediaQueryListEvent)
    }
  }
}

window.matchMedia = (media: string): MediaQueryList => createList(media)

beforeEach(() => {
  // Queries from a previous test belong to unmounted components; dropping them
  // keeps a phone width from leaking into the next file.
  queries.clear()
  currentWidth = DEFAULT_WIDTH
})
