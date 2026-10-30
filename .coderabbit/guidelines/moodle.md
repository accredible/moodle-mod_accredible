# mod_accredible review guidelines

Rules for reviewing this plugin. Flag code that breaks them; don't restate them when it doesn't.

## Sources

These guidelines cover what is specific to this plugin. For anything they don't cover, follow
the Moodle developer docs:

- Coding style: https://moodledev.io/general/development/policies/codingstyle
- Security: https://moodledev.io/general/development/policies/security
- External functions: https://moodledev.io/docs/apis/subsystems/external/security
- Database API: https://moodledev.io/docs/apis/core/dml
- Privacy API: https://moodledev.io/docs/apis/subsystems/privacy
- Activity modules: https://moodledev.io/docs/apis/plugintypes/mod
- PHPUnit: https://moodledev.io/general/development/tools/phpunit

moodledev.io describes the latest Moodle by default. When it recommends an API or syntax that
doesn't work on Moodle 3.11 or PHP 7.4, the Compatibility section below wins: don't suggest it.

## Compatibility

The plugin supports Moodle 3.11 to 5.0 on PHP 7.4 to 8.3 (see the CI matrix in
`.github/workflows/moodle-ci.yml`).

- No PHP 8.0+ syntax: `match`, the nullsafe operator `?->`, constructor property promotion,
  union types, named arguments, `enum`, `readonly`.
- No Moodle API newer than 3.11 without a version check or fallback. For example, external
  functions extend `\external_api` with `require_once($CFG->libdir . '/externallib.php')`, not
  the 4.2+ `core_external\external_api`; the 4.4+ hooks API is not available.

## Security

- **Page scripts** (`view.php`, `index.php`, ...) call `require_login()` with the course and
  module, then `require_capability()` on the module context before reading or changing anything.
  Actions that change state also call `require_sesskey()`.
- **External functions** (`classes/external/`) call `validate_parameters()`,
  `validate_context()` and then `require_capability()`. `validate_context()` is not a capability
  check, and `require_login()` does not belong in an external function. The function's entry in
  `db/services.php` lists the capabilities it checks, and its `type` is `write` if it changes
  data.
- Capabilities are `mod/accredible:*`, defined in `db/access.php`. Check them in the module
  context, not the system context. A new capability needs a lang string
  (`accredible:<name>`) and a version bump.
- Input comes through `required_param()` / `optional_param()` or the external function's
  parameters, with the narrowest `PARAM_*` type. Flag `PARAM_RAW` on input and any direct use of
  `$_GET`, `$_POST` or `$_REQUEST`.
- Database access goes through `$DB` with placeholders (`?` or `:name`) and `get_in_or_equal()`
  for lists. Flag SQL built by concatenating values.
- Output is escaped with `s()`, `format_string()` or `format_text()`. Mustache templates use
  `{{ }}`; flag `{{{ }}}` unless the value is already sanitised HTML.

## Accredible API

- Calls to the Accredible API go through `classes/client/client.php` and
  `classes/apirest/apirest.php`, never a new `curl` instance.
- The API key (`$CFG->accredible_api_key`) must never be logged, put in an exception message,
  sent to the browser or included in an event.
- API failures must not break the Moodle action that triggered them (course completion, grading,
  the activity page). Catch `mod_accredible\local\api_exception`, record it with the existing
  events (`api_request_failed`, `credential_issue_failed`), and carry on.
- Sending new personal data to Accredible, or storing new user data locally, needs a matching
  update to `classes/privacy/provider.php`.

## Database schema and versions

- Schema changes go in both `db/install.xml` and a new step in `db/upgrade.php`, ending with
  `upgrade_mod_savepoint()` for the new version.
- Every upgrade step, new capability, new external function or change to `db/` bumps
  `$plugin->version` in `version.php`.

## Strings and JavaScript

- User-facing text goes through `get_string()` with the key in `lang/en/accredible.php`. Flag
  hard-coded English in PHP, templates or JS (JS uses `core/str`).
- JavaScript lives in `amd/src`. A change there needs the rebuilt files in `amd/build` in the
  same PR; don't review `amd/build` itself.

## Code style

CI runs `phpcs` and `phpdoc` with zero warnings allowed, so:

- Every file has the GPL header with `@package mod_accredible`, `@copyright` and `@license`.
- Every class, method and property has a docblock with `@param`, `@return` and `@var` types.
- Moodle coding style: four-space indent, short array syntax, lower-case names; variable names
  run words together with no underscores (`$courseid`, not `$course_id`).

## Tests

- PHPUnit tests extend `\advanced_testcase`, are `final`, and call `$this->resetAfterTest()`.
- Tests never make real HTTP calls. API responses come from JSON fixtures in
  `tests/fixtures/mockapi/v1/`, served by a mock client.
- New behaviour, especially permission checks and API error handling, needs a test. Test what
  the code does, not how it does it.
