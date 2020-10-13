# 5.5 to 5.6

## Response codes
When returning a newly created Eloquent model directly from a route, the
response status will now automatically be set to 201 instead of 200.
If any of your application's tests were explicitly expecting a 200 response,
those tests should be updated to expect 201.

## php unit 
You should update the phpunit/phpunit dependency of your application to ^7.0.
