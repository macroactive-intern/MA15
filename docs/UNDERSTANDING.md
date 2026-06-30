What is the task asking me to build?

This task is asking me to build a Laravel API for logging client macro entries and returning daily / weekly macro summaries.

The main feature is the GET /api/daily-summary endpoint. It needs to aggregate a user's macro logs for a single date and return:

        - Total calories
        - Total protein
        - Total carbs
        - Total fat
        - Entry count

Because coaches and clients may hit this endpoint often, the daily summary result should be cached so the database aggregation query does not run every time.

The API also needs endpoints to create, update, and delete macro log entries. When a macro log changes, the affected daily summary cache must be cleared so the next summary request recalculates the totals from the database.

All endpoints must require Sanctum authentication.

---------------------------------------------------------------------------------------------------------------------------------------------

Inputs

POST /api/macro-logs

Creates a macro log entry.

Expected input:

{
  "logged_at": "2026-06-15",
  "protein_g": 165,
  "carbs_g": 220,
  "fat_g": 70,
  "description": "Example day"
}

The authenticated user owns the log through user_id.

-------------------------------------------------------------------------

PUT /api/macro-logs/{id}

Updates an existing macro log entry owned by the authenticated user.

Possible input:

{
  "logged_at": "2026-06-15",
  "protein_g": 170,
  "carbs_g": 210,
  "fat_g": 65,
  "description": "Updated meal"
}

-------------------------------------------------------------------------

DELETE /api/macro-logs/{id}

Deletes an existing macro log entry owned by the authenticated user.

-------------------------------------------------------------------------

GET /api/daily-summary?date=2026-06-15

Returns the authenticated user's aggregate macro totals for one date.

If date is not provided, it defaults to today.

-------------------------------------------------------------------------

GET /api/weekly-summary?start_date=2026-06-10

Returns per-day macro summaries for seven consecutive days, starting from start_date.

If start_date is not provided, I need to decide whether it should default to today or the start of the current week. The brief only explicitly says the daily summary date defaults to today.

---------------------------------------------------------------------------------------------------------------------------------------------

Outputs

Daily summary response
{
  "date": "2026-06-15",
  "total_calories": 2170,
  "total_protein_g": 165,
  "total_carbs_g": 220,
  "total_fat_g": 70,
  "entry_count": 4
}

-------------------------------------------------------------------------

Weekly summary response

The exact response shape is not fully specified, but it should return seven daily aggregate summaries.

I will likely return something like:

{
  "start_date": "2026-06-10",
  "end_date": "2026-06-16",
  "days": [
    {
      "date": "2026-06-10",
      "total_calories": 0,
      "total_protein_g": 0,
      "total_carbs_g": 0,
      "total_fat_g": 0,
      "entry_count": 0
    }
  ]
}

---------------------------------------------------------------------------------------------------------------------------------------------

Calorie calculation formula

I will use the general Atwater calorie factors:

total calories = (protein_g × 4) + (carbs_g × 4) + (fat_g × 9)

The FAO describes the Atwater general system as using 4 kcal/g for protein, 4 kcal/g for carbohydrates, and 9 kcal/g for fat. USDA ARS also describes protein and carbohydrates as about 4 calories per gram, and fat as about 9 calories per gram.

Example required by the brief:

protein: 165g × 4 = 660
carbs:   220g × 4 = 880
fat:      70g × 9 = 630

total = 660 + 880 + 630 = 2170

So a day with 165g protein, 220g carbs, and 70g fat should return:

{
  "total_calories": 2170
}

---------------------------------------------------------------------------------------------------------------------------------------------

The brief contradicts itself about cache invalidation

If the cache stayed valid after a client logged a new meal, the client could see stale daily totals for up to 24 hours. For example:

Client views summary at 7am.
The result is cached.
Client logs breakfast at 8am.
Client checks the summary again at 8:05am.
If the cache was not invalidated, the summary would not include breakfast.

That would be confusing and would make the summary look wrong.

Because the acceptance criteria and deliverables specifically require invalidation, I will follow those instead of the sentence saying the cache should remain valid after new logs.

-------------------------------------------------------------------------

Should weekly summaries be cached?

daily summaries must be cached. Weekly summaries only need to return correct per-day aggregates for seven days. I may build weekly summaries using the same daily summary service so each day can benefit from the daily cache, but I will not create a separate weekly cache key unless needed.

-------------------------------------------------------------------------

What should weekly-summary default to?

if start_date is missing, default to today as the first day of the 7-day period, unless I decide it is better to default to the start of the current week. I will document this decision in APPROACH.md.

-------------------------------------------------------------------------

Rounding / decimal output format is not fully specified

totals can be returned as numbers. If the total is a whole number, it can appear as 165; if decimal values exist, it may return something like 165.5 or 165.50.

-------------------------------------------------------------------------

Authorization rules for coaches are not fully specified

This task, each authenticated user can only access their own macro logs and summaries. I will not implement coach access to client summaries unless the brief later provides a coach-client relationship.

-------------------------------------------------------------------------

Update edge case: changing the logged_at date

If a macro log is updated and its logged_at date changes, then two cache keys may need invalidation:

The old date's daily summary cache.
The new date's daily summary cache.

Example:

Original log date: 2026-06-15
Updated log date: 2026-06-16

Both of these should be cleared, because the entry moved out of one day and into another.

-------------------------------------------------------------------------

Delete edge case

When a macro log is deleted, the cache for that log's user and date must be cleared.

Otherwise, the daily summary could still include the deleted meal until the cache expires.

-------------------------------------------------------------------------

Cache strategy

The daily summary cache should be scoped by:

endpoint / summary type
user ID
date

This prevents one user's summary from leaking into another user's response.

The cache key I plan to use is:

daily-summary:user:{user_id}:date:{YYYY-MM-DD}

For user 5 on 2026-06-15, the exact cache key will be:

daily-summary:user:5:date:2026-06-15

-------------------------------------------------------------------------

TTL decision

The brief says to cache the result for 24 hours.

I will use a 24-hour TTL, probably:

now()->addHours(24)

or Laravel's equivalent Cache::remember($key, now()->addDay(), ...).

However, I will not rely only on TTL for correctness. TTL alone would allow stale summaries after a create, update, or delete. So the cache must also be manually invalidated whenever affected macro logs change.

-------------------------------------------------------------------------

Invalidation strategy

The cache should be cleared when a macro log is:

created
updated
deleted

I will likely implement this using a model observer, for example:

MacroLogObserver

The observer will clear the relevant cache key when the model changes.

-------------------------------------------

Create

When a macro log is created:

clear daily-summary:user:{user_id}:date:{logged_at}

-------------------------------------------

Update

When a macro log is updated:

clear daily-summary:user:{user_id}:date:{new_logged_at}

If logged_at changed, also clear:

clear daily-summary:user:{user_id}:date:{old_logged_at}

-------------------------------------------

Delete

When a macro log is deleted:

clear daily-summary:user:{user_id}:date:{logged_at}