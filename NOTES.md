# Notes

Buyers can save the search they're looking at, see and delete their saved searches, and get an email when a new listing that matches goes live. Emails go through Laravel's mail system to the log driver (storage/logs/laravel.log), as the brief allows, so switching to a real provider is a config change. The "Try it" section in the README walks through it in a couple of minutes.

## The main decision

The brief wants an alert every time a matching listing goes live, and Support wants us not to spam people. Those pull against each other, so I split them into two steps.

An alert is created the moment a listing goes live - that happens every time. How it reaches the buyer is their choice per saved search: instant (emailed straight away) or a daily digest, which is the default. The digest is one email per buyer covering all their searches, so a busy day with 50 matches is 50 alerts but still one email.

## Other decisions

- What counts as a match. The listing is live and meets every criterion the buyer set; a blank criterion means "any". These are the listings page filter's own rules, so a saved search means "tell me when something new shows up in these results". The rules exist twice in the code - in ListingController's filter and in SavedSearch::scopeMatching() - so a test checks the two always give the same answer.
- A price range rather than just a max. Someone looking for a £400k family home doesn't want an email about every studio flat, and a minimum price is the simplest way to cut that noise. I added it to the listings filter too, so the page and the saved search stay identical.
- Going live. There was no way to put a listing live before, so I added Listing::publish(). It's the only thing that raises alerts. Listings written as live directly (the seeder, an import, a raw update) raise nothing, and publishing an already-live listing does nothing. I didn't use a model observer: it hides the trigger, and Eloquent doesn't fire model events for bulk updates like Listing::where(...)->update(), so it would miss those anyway.
- One alert per buyer per listing. If a listing matches two of someone's searches they still get one alert - a unique key on (user, listing) in the alerts table guarantees it. The alert belongs to their instant search if they have one, so it's emailed straight away, otherwise to their oldest matching search.
- New listings only. A property that comes back on the market goes live again, but nobody already told about it is told twice - the brief says "a new listing", so I stuck to that.
- No backfill. Saving a search doesn't create alerts for listings already live - the buyer has just been looking at them. Each saved search links back to its current results instead.
- Deleting a search is how a buyer stops its emails, and it removes the alerts it raised with it, including ones already emailed - deleting means "I'm done with this search". For a buyer who only wants a break, pausing (in next steps) is the right tool, rather than keeping alerts with no search attached.
- No queue. The README's two-terminal setup runs no queue worker, so a queued job would never run for anyone trying it that way. So alerts are created, and instant emails sent, during the publish call itself, and the digest sends when its command runs.
- Delivery lives in one action class, SendAlerts: it sends a buyer one email for their alerts and marks them sent. Instant alerts and the digest both go through it, so the two paths can't drift apart.

## Things in the existing code I'd raise

- The auth stub. auth()->user() returned null even though the README and the brief both say to use it - the middleware only set the user on the request, not on Laravel's auth guard. It now sets both, in its own commit with a test.
- The listings page had an N+1: every card loaded its branch with a separate query. Fixed with an eager load, plus a test that the number of queries stays the same as the page grows.
- Location belongs to the branch, not the property. The code assumes a branch only sells in its own area, and the listings filter depends on that. If a Manchester branch ever lists a house in Leeds, nobody searching Leeds will see it, on the listings page or in alerts. The property's own postcode would be the right thing to match on (and would allow radius search), but the seeded postcodes are random, so I followed the listings page.
- The listings filter lives in ListingController as five if blocks that turn query-string values into where clauses. That's query logic in the HTTP layer - it belongs in a scope on Listing, next to the existing live() scope, so the controller only reads the request and renders. I kept this change scoped to the feature; moving it is a safe follow-up, because the existing listing page tests would prove the move changed nothing.

## Left out, and what I'd do next, in order

1. Sending real email through a mail provider, a queue worker, and the cron entry for php artisan schedule:run so the daily digest runs itself. Plus an unsubscribe link - real marketing email legally needs one.
2. "Back on the market" and price-drop alerts. Both need the alert to record why it was raised (new, back on the market, price drop) and the one-alert rule widened to one per listing per reason. Price drops also need an event when a price changes, with a threshold so small cuts don't spam.
3. Searching more than one type or area at once (flat or terrace, Leeds or Sheffield). For now that's two saved searches, and since a buyer only ever gets one alert per listing, it costs them nothing.
4. Editing a search, pausing a search's alerts without deleting it, a weekly digest, read and unread.

## Where it won't hold up yet, and what fixes it

- Everything runs in one process. Publishing does all the matching before it returns, and the digest emails buyers one after another - hours with millions of buyers. In production both would go on a queue: the matching handed to a background worker, and the digest split into one small job per buyer that many workers send in parallel.
- Every new listing is compared with every saved search. Because a blank criterion means "any", each check is "blank or matches", which the database can't answer from an index, so it reads every saved search - fine with thousands, slow with millions. Next would be storing searches so the ones for a listing's area or type can be looked up by index first, and at real scale a search engine that matches stored searches against new documents, like Elasticsearch's percolator.
- Alerts are never deleted, so the table grows forever. It needs a retention rule, for example archiving alerts older than a year.
- A listing that goes live without publish() - a direct database update, say - never alerts. A scheduled job that finds newly live listings and runs the same matching would catch those, and it's safe to run repeatedly because the unique key stops duplicate alerts.
