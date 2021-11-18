# CHANGELOG

1.3.0
-----

* The extension was fixed to follow-up on [contao/contao#1118][2] and [contao/contao#565][3]. Fixes [#7][4].


1.2.0
----
* The login link only works on POST requests. This fixes that link checkers and bots invoke the link and thereby invalidate the login token. When the login link gets invoked with a GET request, a `<form method="post">` will be rendered and submitted to gather a POST request. Fixes [#3][1].
* Added notification token `##login_form_html##`. This token can be added to html emails and renders a login `<form method="post">` to immediately login the member. 
* Fixed authentication checks: Do not log in disabled users.


[1]: https://github.com/richardhj/contao-email-token-login/issues/3
[2]: https://github.com/contao/contao/pull/1118 
[3]: https://github.com/contao/contao/pull/565
[4]: https://github.com/richardhj/contao-email-token-login/issues/7
