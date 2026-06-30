Step 1

    Project set up
                1. Start new Laravel project
                2. connect to Github repo
                                                                                                    10 mins

----------------------------------------------------------------------------------------------------------------

Step 2

    Documentation
                1. Write out the Understand.md
                2. Write out the Time Estimate.md
                3. Add the Ai Time estimate to the Estimate.md
                4. Write out the Aproach.md
                                                                                                        120 mins

----------------------------------------------------------------------------------------------------------------

Step 3

    Finish Project set up
                1. Install dependencies
                2. Install Sanctum
                3. Install Pest
                4. Confirm API/auth setup
                                                                                                    20 mins

----------------------------------------------------------------------------------------------------------------

Step 4

    Feature tests

                1. Auth tests
                                - Unauthenticated user cannot access macro endpoints
                                - Authenticated user can access their own data
                
                2. Macro log tests
                                - User can create a macro log
                                - User can update their own macro log
                                - User can delete their own macro log
                                - User cannot update another user’s macro log
                                - User cannot delete another user’s macro log
                
                3. Daily summary tests
                                - Daily summary returns correct totals
                                - Daily summary defaults to today
                                - Empty day returns zero totals
                                - Summary is scoped to authenticated user
                                - User A cannot see User B’s totals
                                - Required calorie example returns exactly:
                                                                            165g protein
                                                                            220g carbs
                                                                            70g fat
                                                                            2170 calories
                
                4. Cache tests
                                - First daily summary request runs aggregation query
                                - Second identical request uses cached result
                                - Cache key is scoped by user and date
                                - Cache invalidates after creating a log
                                - Cache invalidates after updating a log
                                - Cache invalidates after deleting a log
                                - Updating logged_at clears old date and new date cache
                                - After invalidation, next request runs fresh aggregation
                
                5. Weekly summary tests
                                - Weekly summary returns seven days
                                - Weekly summary starts from start_date
                                - Weekly summary includes zero-total days
                                - Weekly summary totals are correct for each day
                                - Weekly summary is scoped to authenticated user
                                                                                                    120 mins

----------------------------------------------------------------------------------------------------------------

Step 5

    Database and model setup

                1. Create macro_logs migration
                2. Add columns:
                                id
                                user_id
                                logged_at
                                protein_g
                                carbs_g
                                fat_g
                                description
                                timestamps
                
                3. Add foreign key to users
                4. Add useful index:
                                    user_id
                                    logged_at
                
                5. Create MacroLog model
                6. Define fillable fields
                7. Add casts:
                            logged_at as date
                            macro fields as decimal / float as needed
                8. Define relationship:
                            MacroLog belongsTo User
                            User hasMany MacroLogs
                                                                                                    30 mins

----------------------------------------------------------------------------------------------------------------

Step 6

    Authentication setup

                1. Configure Sanctum middleware
                2. Protect API routes with auth:sanctum
                3. Add test helpers for authenticated users
                4. Ensure unauthenticated requests return 401
                                                                                                    20 mins

----------------------------------------------------------------------------------------------------------------

Step 7

    Macro log endpoints

POST /api/macro-logs

                1. Create controller method
                2. Validate request:
                                    logged_at required date
                                    protein_g required numeric min 0
                                    carbs_g required numeric min 0
                                    fat_g required numeric min 0
                                    description nullable string max 150
                
                3. Save log against authenticated user
                4. Return created response

PUT /api/macro-logs/{id}

                1. Find macro log by ID
                2. Ensure it belongs to authenticated user
                3. Validate update payload
                4. Update entry
                5. Return updated response
                6. Handle date-change edge case for cache invalidation

DELETE /api/macro-logs/{id}

                1. Find macro log by ID
                2. Ensure it belongs to authenticated user
                3. Delete entry
                4. Return success / no-content response
                                                                                                    45 mins

----------------------------------------------------------------------------------------------------------------

Step 8

    Daily summary endpoint

GET /api/daily-summary

                1. Accept optional date query param
                2. Default date to today when missing
                3. Validate date format
                4. Build cache key using user ID and date
                5. Use 24-hour cache TTL
                6. Aggregate macro logs for authenticated user and date:
                                                                        sum protein
                                                                        sum carbs
                                                                        sum fat
                                                                        count entries
                7. Calculate calories:
                                    protein × 4
                                    carbs × 4
                                    fat × 9
                
                8. Return response shape from brief
                9. Ensure empty days return zero totals
                                                                                                    30 mins

----------------------------------------------------------------------------------------------------------------

Step 9

    Weekly summary endpoint

GET /api/weekly-summary

                1. Accept optional start_date
                2. Decide default if missing
                3. Validate start_date
                4. Generate seven consecutive dates
                5. Return one summary per day
                6. Include zero-total days
                7. Decide whether to reuse daily summary service/cache
                8. Return clear response shape with:
                                                    start date
                                                    end date
                                                    daily summaries
                                                                                                    45 mins

----------------------------------------------------------------------------------------------------------------

Step 10

    Cache service / helper

                1. Create a summary service or helper class
                2. Add cache key builder:
                                        daily-summary:user:{user_id}:date:{date}
                
                3. Add method to calculate daily summary
                4. Add method to cache daily summary for 24 hours
                5. Add method to forget cache for user/date
                6. Keep cache logic out of controller as much as possible
                                                                                                    35 mins

----------------------------------------------------------------------------------------------------------------

Step 11

    Cache invalidation

                1. Create MacroLogObserver
                2. Register observer in service provider
                3. On created:
                            clear cache for user/date
                4. On updated:
                            clear cache for new date
                            if logged_at changed, clear old date too
                5. On deleted:
                            clear cache for user/date
                
                6. Confirm invalidation happens automatically from model events
                7. Confirm update and delete invalidation are covered by tests
                                                                                                    35 mins

----------------------------------------------------------------------------------------------------------------

Step 12

    Routes

                1. Add routes in routes/api.php
                2. Group routes under auth:sanctum
                3. Define:
                            POST /api/macro-logs
                            PUT /api/macro-logs/{id}
                            DELETE /api/macro-logs/{id}
                            GET /api/daily-summary
                            GET /api/weekly-summary
                                                                                                    25 mins

----------------------------------------------------------------------------------------------------------------

Step 9

    Run Tests
                                                                                                    20 mins

----------------------------------------------------------------------------------------------------------------

Step 10

    Fix any failing tests
                                                                                                    25 mins

----------------------------------------------------------------------------------------------------------------

Step 11

    Manual test
                                                                                                    45 mins

----------------------------------------------------------------------------------------------------------------

Step 12 

    BEFORE-AFTER.md
                                                                                                    30 mins
----------------------------------------------------------------------------------------------------------------

                                                                                                    11 hrs

---------------------------------------------------------------------------------------------------------------- 