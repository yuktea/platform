# 5.5 to 5.6

## Response codes
When returning a newly created Eloquent model directly from a route, the
response status will now automatically be set to 201 instead of 200.
If any of your application's tests were explicitly expecting a 200 response,
those tests should be updated to expect 201.

## php unit 
You should update the phpunit/phpunit dependency of your application to ^7.0.

# 5.6 a 5.7
New storage/framework/cache/data directory must exist.

# To check 

# 5.7 to 5.8

- Check arr_ and str_ methods https://laravel.com/docs/5.8/upgrade#string-and-array-helpers
- ClearDB for heroku won't work unless we stop needing putenv(). Affected code: 
        ```
        // Parse ClearDB URLs
        if (getenv("CLEARDB_DATABASE_URL")) {
            $url = parse_url(getenv("CLEARDB_DATABASE_URL"));
            // Push url parts into env
            putenv("DB_HOST=" . $url["host"]);
            putenv("DB_USERNAME=" . $url["user"]);
            putenv("DB_PASSWORD=" . $url["pass"]);
            putenv("DB_DATABASE=" . substr($url["path"], 1));
        } 
        ```
        https://laravel.com/docs/5.8/upgrade#collections

-         
