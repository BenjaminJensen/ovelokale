# A hardcoded current user stands in until login exists

There is no login system yet, but the booking dialog still needs to attribute ad-hoc bookings to *someone* and needs to know which bands to show in bold as "the current user's own". Rather than leaving that undefined per call site, both sides fix it at one place each: `App\User\CurrentUser::ID` in the backend, `CURRENT_USER_ID` in `frontend/src/currentUser.ts`. Both are hardcoded to the same user id (`1`) and are the only two places that value should be written.

The backend never trusts a client-supplied user id for `booked_by_user_id` — `POST /api/bookings` always uses `CurrentUser::ID` server-side, so the frontend constant only ever affects which bands are bolded and which bands are fetched as "own bands," never who a booking is attributed to.

We considered passing a `user_id` end-to-end (e.g. as a request parameter) to make the stub more visible and swappable. We rejected that: it would suggest the client is a trusted source of identity, which is exactly what a real login system must not do. Keeping the id as a fixed constant on each side, rather than threaded through requests, makes it obvious this is temporary scaffolding to delete once real authentication lands, and keeps the eventual migration to a two-line change (replace each constant with the authenticated user's id).
