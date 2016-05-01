# FRED
## Framework for Rapid and Easy Development

FRED is a light-weight, component-based / dependecy injected, lazy loading
framework. Designed for flexibility and security, and optimized for speed.

TODO: It's an MVC framework with a front-controller / router. It has auto view
detection based on request extension (e.g. .json for async calls; defaults to
HTML).

All configuration is done through PHP files / arrays. No exotic file formats
that have to be parsed, cached, and cleared upon changes.

### Security

There are several security checks in place. All forms and async calls are
automaticly protected from CSRF. All output is automaticly escaped to prevent
XSS (unless explicitly excluded). All security related actions are protected
agains brute-force attacks. Users that break to many security checks within a
certain amount of time are automaticly banned.

### Widgets

TODO: Form field widgets including client-side javascript support (to off load
as much as possible to the client).

### Multi factor authentication

TODO: Necessary authentications can be set per contorller. Multiple
combinations of authenticators can be set (e.g. subnet + password, or password +
mail token for users outside the company subnet).

Access rights can be set on multiple levels (r/w/x - and every other letter),
for every controller, action, and even widget.