# CHANGELOG

1.2.0
----
* The login link only works on POST requests. This fixes that link checkers and bots invoke the link and thereby invalidate the login token. When the login link gets invoked with a GET request, a `<form method="post">` will be rendered and submitted to gather a POST request. Fixes #3
* Added notification token `##login_form_html##`. This token can be added to html emails and renders a login `<form method="post">` to immediately login the member. 
* Fixed authentication checks: Do not log in disabled users
