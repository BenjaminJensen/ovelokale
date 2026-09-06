# Booking conflicts are always a hard block, never a soft warning

A conflict is any two occurrences overlapping in time on the same date — including two bookings from the *same* band. The booking dialog treats every conflict as a hard block: "Book" stays disabled while one exists, enforced identically by a client-side live check and a server-side check before insert (both calling the same underlying conflict-detection logic, so they can't drift apart).

We considered the more common pattern of warning but still allowing submission (as an earlier mockup did, showing the warning next to an enabled "Book" button), leaving the server as a soft backstop. We rejected that: the room is a single physical space that can only host one rehearsal at a time, so there's no legitimate scenario where a known conflict should be submittable — same-band overlaps included, since two sub-groups of one band still can't occupy the room simultaneously.
