Goal

The goal is to build a Laravel API that lets authenticated users log macro entries and request daily or weekly macro summaries.

The main focus of this task is the GET /api/daily-summary endpoint. This endpoint aggregates a user's macro logs for a selected date and returns total calories, protein, carbs, fat, and entry count.

Because the endpoint may be hit often, the daily summary should be cached for 24 hours. However, the cache must also be invalidated when the underlying macro logs change, otherwise users could see stale data after creating, updating, or deleting a meal entry.

Important brief decision

The brief contains a contradiction.

It says:

When a client logs a new meal, the cache for that day should still be valid — the response only needs to be recalculated once per day.

But the acceptance criteria says:

The cache is invalidated when a macro log is added, updated, or deleted for that user and date.

I will follow the acceptance criteria.

That means the daily summary will be cached, but any create, update, or delete that affects that user's summary date will clear the cache. The next request will rebuild and cache the fresh summary.

This avoids stale data.

Example stale data problem:

User views GET /api/daily-summary?date=2026-06-15.
Summary is cached.
User logs a meal for 2026-06-15.
User views the same summary again.
If the cache was not invalidated, the new meal would not appear until the cache expired.
Data model
macro_logs table
Column	Type	Notes
id	bigIncrements	Primary key
user_id	foreignId	References users.id
logged_at	date	The day the macro log belongs to
protein_g	decimal(6,2)	Protein grams
carbs_g	decimal(6,2)	Carbohydrate grams
fat_g	decimal(6,2)	Fat grams
description	string(150)	Nullable meal description
created_at	timestamp	Laravel timestamp
updated_at	timestamp	Laravel timestamp
Constraints and indexes

The user_id column will reference the users table.

I will add an index on:

user_id, logged_at

Reason:

The daily and weekly summary queries will filter by authenticated user and date. This index should help the database find the relevant rows quickly.

Model relationships

In MacroLog:

public function user()
{
    return $this->belongsTo(User::class);
}

In User:

public function macroLogs()
{
    return $this->hasMany(MacroLog::class);
}
Model casts

In MacroLog:

protected $casts = [
    'logged_at' => 'date',
    'protein_g' => 'decimal:2',
    'carbs_g' => 'decimal:2',
    'fat_g' => 'decimal:2',
];
Calorie formula

The old spreadsheet formula was:

total calories = (protein_g × 4) + (carbs_g × 4) + (fat_g × 4)

That is incorrect because fat should not be calculated at 4 kcal per gram.

I will use the standard Atwater general calorie values:

protein = 4 kcal/g
carbs = 4 kcal/g
fat = 9 kcal/g

Formula:

total_calories = (protein_g × 4) + (carbs_g × 4) + (fat_g × 9)

Example required by the brief:

165g protein × 4 = 660
220g carbs × 4 = 880
70g fat × 9 = 630

660 + 880 + 630 = 2170

So the summary for 165g protein, 220g carbs, and 70g fat must return:

{
  "total_calories": 2170
}

I will round total_calories to a whole number because the example response uses an integer.

Endpoints and routes

All routes will be protected by auth:sanctum.

Routes will be added in routes/api.php.

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/macro-logs', [MacroLogController::class, 'store']);
    Route::put('/macro-logs/{macroLog}', [MacroLogController::class, 'update']);
    Route::delete('/macro-logs/{macroLog}', [MacroLogController::class, 'destroy']);

    Route::get('/daily-summary', [MacroSummaryController::class, 'daily']);
    Route::get('/weekly-summary', [MacroSummaryController::class, 'weekly']);
});
POST /api/macro-logs

Creates a macro log for the authenticated user.

Validation
[
    'logged_at' => ['required', 'date'],
    'protein_g' => ['required', 'numeric', 'min:0'],
    'carbs_g' => ['required', 'numeric', 'min:0'],
    'fat_g' => ['required', 'numeric', 'min:0'],
    'description' => ['nullable', 'string', 'max:150'],
]
Behaviour
Use the authenticated user's ID as user_id.
Do not allow the client to submit another user's user_id.
Save the macro log.
Return the created macro log.
The MacroLogObserver will clear the daily summary cache for that user and date.
PUT /api/macro-logs/{id}

Updates a macro log owned by the authenticated user.

Behaviour
Find the macro log.
Confirm it belongs to the authenticated user.
Validate the request.
Update the macro log.
Return the updated macro log.
The observer will clear the affected daily summary cache.
Important update edge case

If logged_at changes, two cache keys must be cleared:

The old date cache.
The new date cache.

Example:

Original date: 2026-06-15
Updated date: 2026-06-16

Both of these should be cleared:

daily-summary:user:{user_id}:date:2026-06-15
daily-summary:user:{user_id}:date:2026-06-16

Reason:

The log moved out of one day and into another day, so both daily summaries are now affected.

DELETE /api/macro-logs/{id}

Deletes a macro log owned by the authenticated user.

Behaviour
Find the macro log.
Confirm it belongs to the authenticated user.
Delete it.
Return a 204 No Content response.
The observer will clear the daily summary cache for the deleted log's user and date.
GET /api/daily-summary

Returns the authenticated user's macro summary for one date.

Query params
date=2026-06-15

If date is missing, it defaults to today.

Validation
[
    'date' => ['nullable', 'date'],
]
Response shape
{
  "date": "2026-06-15",
  "total_calories": 2170,
  "total_protein_g": 165,
  "total_carbs_g": 220,
  "total_fat_g": 70,
  "entry_count": 4
}
Empty day response

If the user has no macro logs for that day, return zero totals.

Example:

{
  "date": "2026-06-15",
  "total_calories": 0,
  "total_protein_g": 0,
  "total_carbs_g": 0,
  "total_fat_g": 0,
  "entry_count": 0
}
GET /api/weekly-summary

Returns seven daily summaries for the authenticated user.

Query params
start_date=2026-06-10

The endpoint returns summaries for:

2026-06-10
2026-06-11
2026-06-12
2026-06-13
2026-06-14
2026-06-15
2026-06-16
Default decision

The brief does not clearly say what should happen if start_date is missing.

I will default start_date to today.

Reason:

The daily summary endpoint defaults to today, and this keeps the API behaviour simple and predictable.

Response shape

The brief does not give an exact weekly response shape, so I will return:

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
Weekly summary caching decision

The brief only specifically requires caching for GET /api/daily-summary.

I will not create a separate weekly cache key.

Instead, I will build the weekly response by asking the summary service for seven daily summaries. That means each day can still benefit from the daily cache.

Reason:

Keeps the cache design simple.
Avoids needing to invalidate both daily and weekly cache keys.
Avoids stale weekly summaries after a daily log changes.
Reuses the same tested daily summary logic.
Cache key design

Daily summaries will be cached per user and per date.

Cache key format:

daily-summary:user:{user_id}:date:{YYYY-MM-DD}

Exact required example:

daily-summary:user:5:date:2026-06-15

This key is scoped by user ID and date.

That prevents one user's cached summary from being returned to another user.

Cache TTL

The brief requires the result to be cached for 24 hours.

I will use:

now()->addHours(24)

or:

now()->addDay()

inside Cache::remember.

Example:

return Cache::remember(
    $this->dailySummaryKey($userId, $date),
    now()->addDay(),
    fn () => $this->calculateDailySummary($userId, $date)
);
Cache service

I will create a service class, probably:

app/Services/MacroSummaryService.php

This service will contain the summary and cache logic.

Responsibilities

The service will handle:

building cache keys
calculating daily summaries
returning cached daily summaries
forgetting daily summary cache keys
calculating weekly summaries through daily summaries
Main methods
public function dailySummary(User $user, CarbonInterface|string $date): array

Returns a cached daily summary.

public function calculateDailySummary(int $userId, string $date): array

Runs the database aggregation query.

public function weeklySummary(User $user, CarbonInterface|string $startDate): array

Returns seven daily summaries.

public function forgetDailySummary(int $userId, CarbonInterface|string $date): void

Clears the cache for one user/date.

public function dailySummaryKey(int $userId, string $date): string

Returns a key like:

daily-summary:user:5:date:2026-06-15
Daily aggregation query

The daily summary calculation will query macro_logs where:

user_id = authenticated user's ID
logged_at = requested date

It will aggregate:

SUM(protein_g)
SUM(carbs_g)
SUM(fat_g)
COUNT(*)

Then it will calculate calories using:

protein × 4 + carbs × 4 + fat × 9
Cache invalidation

I will use a model observer:

app/Observers/MacroLogObserver.php

This keeps cache invalidation close to the model events and prevents the controller from being responsible for remembering every cache-clearing case.

The observer will be registered in a service provider.

Observer events
Created

When a macro log is created, clear:

daily-summary:user:{user_id}:date:{logged_at}
Updated

When a macro log is updated, clear:

daily-summary:user:{user_id}:date:{logged_at}

If the logged_at date changed, also clear the old date.

Example:

if ($macroLog->wasChanged('logged_at')) {
    $oldDate = $macroLog->getOriginal('logged_at');
    $newDate = $macroLog->logged_at;

    $summaryService->forgetDailySummary($macroLog->user_id, $oldDate);
    $summaryService->forgetDailySummary($macroLog->user_id, $newDate);
}

Also, if the macro values changed but the date did not, clear the current date cache.

Deleted

When a macro log is deleted, clear:

daily-summary:user:{user_id}:date:{logged_at}

This ensures the deleted entry is removed from the next summary calculation.

Auth and authorization

All endpoints require auth:sanctum.

For macro log update and delete:

A user can only update their own macro logs.
A user can only delete their own macro logs.
A user cannot update or delete another user's logs.

I will enforce this in the controller by checking:

if ($macroLog->user_id !== $request->user()->id) {
    abort(404);
}

I prefer returning 404 instead of 403 here so the API does not reveal whether another user's macro log exists.

Libraries and packages
Laravel

Used as the main application framework.

Laravel Sanctum

Used for API authentication because the brief requires all endpoints to use auth:sanctum.

Laravel Cache

Used for daily summary caching.

The cache driver will be set to:

CACHE_DRIVER=file

No Redis is needed for this task.

Carbon

Used for date parsing, defaulting to today, and generating seven consecutive days for the weekly summary.

Pest

Used for feature tests, because the workflow expects tests and the project setup includes installing Pest.

Testing approach

I will write feature tests before or alongside the implementation.

The tests will cover the acceptance criteria and the main edge cases.

Feature tests
Auth tests
Unauthenticated users cannot access POST /api/macro-logs.
Unauthenticated users cannot access PUT /api/macro-logs/{id}.
Unauthenticated users cannot access DELETE /api/macro-logs/{id}.
Unauthenticated users cannot access GET /api/daily-summary.
Unauthenticated users cannot access GET /api/weekly-summary.
Macro log tests
Authenticated user can create a macro log.
Authenticated user can update their own macro log.
Authenticated user can delete their own macro log.
User cannot update another user's macro log.
User cannot delete another user's macro log.
Daily summary tests
Daily summary returns correct aggregated totals.
Daily summary defaults to today if no date is provided.
Empty day returns zero totals.
Summary is scoped to the authenticated user.
User A cannot see User B's totals.
Required calorie example returns exactly 2170 calories for:
165g protein
220g carbs
70g fat
Cache tests
First daily summary request runs the aggregation query.
Second identical daily summary request uses the cached result.
Cache key is scoped by user and date.
Cache is invalidated after creating a log.
Cache is invalidated after updating a log.
Cache is invalidated after deleting a log.
Updating logged_at clears the old date cache and the new date cache.
After invalidation, the next request runs a fresh aggregation.
Weekly summary tests
Weekly summary returns seven days.
Weekly summary starts from start_date.
Weekly summary includes days with zero totals.
Weekly summary totals are correct for each day.
Weekly summary is scoped to the authenticated user.
Query count cache test strategy

To prove that the cached response does not run the aggregation query again, I will use Laravel's query listener or query log inside the test.

The test will:

Create a user.
Create macro logs for a date.
Request GET /api/daily-summary?date=2026-06-15.
Confirm the correct summary is returned.
Clear or reset the query log.
Request the same endpoint again.
Assert that the second request does not run the macro_logs aggregation query.

I will focus the assertion on queries against the macro_logs table, because the request may still run other framework/auth queries.

Edge cases
Empty day

A date with no macro logs should return zero totals and entry count 0.

Decimal macro values

Macro values are stored as decimal(6,2), so totals may include decimal values.

I will return numeric totals. If totals are whole numbers, the JSON can show them as whole numbers.

Calories with decimal macro values

If macro values contain decimals, the calorie total may produce decimals.

The brief example expects an integer. I will round calories to the nearest whole number.

Negative macro values

Negative protein, carbs, or fat values should not be allowed.

Validation will use:

min:0
Long descriptions

Descriptions are limited to 150 characters.

Missing description

Description is nullable.

User-submitted user_id

The API should ignore any submitted user_id.

The owner should always be the authenticated user.

Updating another user's log

A user should not be able to update another user's macro log.

I will return 404.

Deleting another user's log

A user should not be able to delete another user's macro log.

I will return 404.

Updating logged_at

If a log moves from one date to another, both affected daily summary caches must be cleared.

Cache scoped by date

A cached summary for 2026-06-15 must not be reused for 2026-06-16.

Cache scoped by user

A cached summary for user 5 must not be reused for user 6.

Weekly summary zero days

If only two days have logs, the weekly response should still include all seven days.

The missing days should return zero totals.

Cache stampede

A cache stampede happens when many requests try to rebuild the same expired cache value at the same time.

It could technically happen here if many clients hit the same uncached summary at once. For this small task, I will not add cache locking unless required.

Reason:

The endpoint is scoped per user and date.
The aggregation query is small.
The brief does not require lock-based cache protection.
File cache is being used, not Redis.

If this became a production hot path, I would consider Cache::lock() or a stale-while-revalidate strategy.

Decisions made because the brief is ambiguous
Coach access

The brief says coaches and clients hit the endpoint frequently, but it does not define a coach-client relationship or a way for coaches to request another user's summary.

Decision:

For this task, summaries are scoped to the authenticated user's own macro logs only.

Weekly summary cache

The brief only requires caching GET /api/daily-summary.

Decision:

Do not create a separate weekly cache key. Build weekly summaries from seven daily summaries.

Weekly summary default date

The brief does not define the default for missing start_date.

Decision:

Default start_date to today.

Delete type

The brief does not mention soft deletes.

Decision:

Use normal hard deletes.

Update payload

I will allow normal update validation. If using PUT, I will likely require the main macro fields again to keep behaviour predictable.

If I decide to allow partial updates, I will use sometimes validation and document that in the code/tests.

Cache invalidation location

Decision:

Use a MacroLogObserver instead of putting cache invalidation directly in controllers.

Reason:

Prevents duplication across create/update/delete.
Keeps invalidation close to model changes.
Covers future code paths that modify MacroLog outside the controller.
Implementation order
Set up Laravel project.
Install Sanctum.
Configure SQLite and file cache.
Install Pest.
Write workflow documentation.
Write feature tests.
Create macro_logs migration.
Create MacroLog model and relationships.
Create routes.
Create macro log controller.
Create summary service.
Create daily summary endpoint.
Create weekly summary endpoint.
Create observer for cache invalidation.
Run tests.
Fix failing tests.
Manually test endpoints.
Paste before/after output into BEFORE-AFTER.md.
Final design summary

The API will store macro logs per user and date.

Daily summaries will be cached with this key format:

daily-summary:user:{user_id}:date:{YYYY-MM-DD}

For user 5 on 2026-06-15, the exact cache key is:

daily-summary:user:5:date:2026-06-15

Daily summaries will use a 24-hour TTL, but the cache will also be invalidated on create, update, and delete.

Calories will be calculated using:

protein × 4 + carbs × 4 + fat × 9

The required example of 165g protein, 220g carbs, and 70g fat will return:

2170 calories